<?php
/**
 * Agent Panel — User Data API
 * Returns JSON for agent's invoice list and stats
 */

// Buffer all output (including any warnings from includes)
ob_start();
require '../inc/config.php';
// Discard any HTML/text output from config (including its header())
ob_end_clean();

$old_cwd = getcwd();
chdir(__DIR__ . '/../../');
require_once 'function.php';
require_once 'panels.php';
chdir($old_cwd);

$ManagePanel = new ManagePanel();

// Force JSON content-type after clearing buffer
header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'نشست منقضی شده است. لطفاً مجدداً وارد شوید.']);
    exit;
}

$agent_id = (int) $_SESSION['agent_id'];
session_write_close(); // Release session lock to allow concurrent AJAX requests

$action   = $_GET['action'] ?? 'get_users';

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────
function json_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function format_bytes_fa($bytes)
{
    if ($bytes <= 0) return '۰ مگ';
    $mb = $bytes / pow(1024, 2);
    if ($mb < 1024) {
        return round($mb, 1) . ' مگ';
    } else {
        $gb = $bytes / pow(1024, 3);
        return round($gb, 2) . ' گیگ';
    }
}

try {

    // ──────────────────────────────────────────────────────────────────────
    // ACTION: get_users
    // ──────────────────────────────────────────────────────────────────────
    if ($action === 'get_users') {

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 10;
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        // Base WHERE clause — agent only sees their own invoices
        $where  = '(i.id_user = :aid1 OR i.refral = :aid2)';
        $params = [':aid1' => $agent_id, ':aid2' => $agent_id];

        if ($search !== '') {
            $where .= ' AND (i.username LIKE :s OR i.name_product LIKE :s OR i.Service_location LIKE :s)';
            $params[':s'] = "%{$search}%";
        }

        // Total count
        $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM invoice i WHERE {$where}");
        $stmtCount->execute($params);
        $total      = (int) ($stmtCount->fetchColumn() ?: 0);
        $totalPages = (int) ceil($total / $limit);

        // Fetch rows
        $stmtRows = $pdo->prepare(
            "SELECT * FROM invoice i WHERE {$where} ORDER BY i.time_sell DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmtRows->execute($params);
        $invoices = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

        // Load Jalali library if not already loaded
        if (!function_exists('jdate')) {
            require_once __DIR__ . '/../../jdf.php';
        }

        global $textbotlang;

        $users = [];
        foreach ($invoices as $inv) {
            $days      = (int) ($inv['Service_time'] ?? 0);
            $time_sell = (int) ($inv['time_sell'] ?? 0);
            $expire_ts = $time_sell + ($days * 86400);

            // Remaining days
            $rem_days = 0;
            if ($days > 0) {
                $rem_days = (int) ceil(($expire_ts - time()) / 86400);
                if ($rem_days < 0) $rem_days = 0;
            }

            // Jalali dates
            $start_date = $time_sell > 0 ? jdate('Y/m/d H:i', $time_sell) : 'نامشخص';
            $exp_date   = $days > 0 ? jdate('Y/m/d', $expire_ts) : 'نامحدود';

            // Volume & time formatting
            $vol_total    = (float) ($inv['Volume'] ?? 0);
            $name_product = $inv['name_product'] ?? '';
            $test_name    = $textbotlang['Admin']['adminphp']['db_test_service_name'] ?? '__test__';
            $is_test      = ($name_product === $test_name);

            $formatted_time = $days . ' ' . (
                $is_test
                    ? ($textbotlang['Admin']['adminphp']['btn_hour'] ?? 'ساعت')
                    : ($textbotlang['Admin']['adminphp']['btn_day_1'] ?? 'روز')
            );
            $formatted_vol = ($vol_total == 0)
                ? 'نامحدود'
                : $vol_total . ' گیگ';

            $raw_status = $inv['Status'] ?? 'inactive';
            $status_label = ($raw_status === 'active' && $rem_days > 0) ? 'فعال' : 'غیرفعال';

            $users[] = [
                'id'               => $inv['id_invoice'],
                'username'         => $inv['username'] ?? '—',
                'status'           => $raw_status,
                'status_label'     => $status_label,
                'plan_name'        => $name_product,
                'location'         => $inv['Service_location'] ?? '—',
                'created_at'       => $start_date,
                'expires_at'       => $exp_date,
                'rem_days'         => $rem_days,
                'total_gb'         => $formatted_vol,
                'service_time_str' => $formatted_time,
                'price'            => number_format((float) ($inv['price_product'] ?? 0)) . ' تومان',
            ];
        }

        json_out([
            'status' => 'success',
            'data'   => $users,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // ACTION: get_user_live
    // ──────────────────────────────────────────────────────────────────────
    if ($action === 'get_user_live') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_out(['status' => 'error', 'message' => 'شناسه نامعتبر است.']);
        }

        // Fetch invoice
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :aid1 OR refral = :aid2)");
        $stmt->execute([':id' => $id, ':aid1' => $agent_id, ':aid2' => $agent_id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inv) {
            json_out(['status' => 'error', 'message' => 'سرویس یافت نشد.']);
        }

        // Load Jalali library if not already loaded
        if (!function_exists('jdate')) {
            require_once __DIR__ . '/../../jdf.php';
        }

        // Get live data
        $used_traffic = 0;
        $data_limit = (float) ($inv['Volume'] ?? 0) * pow(1024, 3);
        $raw_status = $inv['Status'] ?? 'inactive';
        $online_at = null;
        $is_online = 'offline';

        if (!empty($inv['Service_location']) && !empty($inv['username']) && $inv['username'] !== 'none') {
            $DataUserOut = $ManagePanel->DataUser($inv['Service_location'], $inv['username']);
            if (is_array($DataUserOut) && !isset($DataUserOut['status']) && isset($DataUserOut['msg'])) {
                // Keep defaults if failed
            } elseif (is_array($DataUserOut)) {
                if (isset($DataUserOut['used_traffic'])) {
                    $used_traffic = (float) $DataUserOut['used_traffic'];
                }
                if (isset($DataUserOut['data_limit'])) {
                    $data_limit = (float) $DataUserOut['data_limit'];
                }
                if (isset($DataUserOut['status'])) {
                    $raw_status = $DataUserOut['status'];
                }
                if (isset($DataUserOut['online_at'])) {
                    $online_at = $DataUserOut['online_at'];
                }
            }
        }

        // Expiry calculation
        $days = (int) ($inv['Service_time'] ?? 0);
        $time_sell = (int) ($inv['time_sell'] ?? 0);
        $expire_ts = $time_sell + ($days * 86400);

        if (isset($DataUserOut['expire']) && $DataUserOut['expire'] > 0) {
            $expire_ts = $DataUserOut['expire'];
        }

        $rem_days = 0;
        if ($expire_ts > time()) {
            $rem_days = (int) ceil(($expire_ts - time()) / 86400);
        }

        $exp_date = $expire_ts > 0 ? jdate('Y/m/d', $expire_ts) : 'نامحدود';

        // Online Status
        if ($online_at === 'online') {
            $is_online = 'online';
        } elseif ($online_at === 'offline') {
            $is_online = 'offline';
        } elseif (!empty($online_at)) {
            if (is_numeric($online_at)) {
                $is_online = (time() - $online_at < 300) ? 'online' : 'offline';
            } else {
                $ts = strtotime($online_at);
                $is_online = ($ts && (time() - $ts < 300)) ? 'online' : 'offline';
            }
        }

        // Status Label
        if ($raw_status === 'active') {
            $status_label = ($rem_days === 0 && $days > 0) ? 'منقضی شده' : 'فعال';
            $status_key   = ($rem_days === 0 && $days > 0) ? 'inactive' : 'active';
        } elseif ($raw_status === 'end_of_time') {
            $status_label = 'پایان زمان';
            $status_key   = 'inactive';
        } elseif ($raw_status === 'end_of_volume' || $raw_status === 'limited') {
            $status_label = 'پایان حجم';
            $status_key   = 'inactive';
        } elseif ($raw_status === 'disabled') {
            $status_label = 'غیرفعال';
            $status_key   = 'inactive';
        } else {
            $status_label = 'غیرفعال';
            $status_key   = 'inactive';
        }

        $used_formatted = format_bytes_fa($used_traffic);
        $limit_formatted = ($data_limit == 0) ? 'نامحدود' : format_bytes_fa($data_limit);
        
        $usage_percent = ($data_limit > 0) ? min(100, round(($used_traffic / $data_limit) * 100)) : 0;
        if ($data_limit == 0) $usage_percent = 0;

        json_out([
            'status' => 'success',
            'live' => [
                'id' => $id,
                'status' => $status_key,
                'status_label' => $status_label,
                'is_online' => $is_online,
                'online_label' => ($is_online === 'online') ? 'آنلاین' : 'آفلاین',
                'used_traffic' => $used_traffic,
                'used_formatted' => $used_formatted,
                'limit_formatted' => $limit_formatted,
                'usage_percent' => $usage_percent,
                'expires_at' => $exp_date,
                'rem_days' => $rem_days,
            ]
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // ACTION: get_stats
    // ──────────────────────────────────────────────────────────────────────
    if ($action === 'get_stats') {
        $p = [':aid1' => $agent_id, ':aid2' => $agent_id];

        $s1 = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = :aid1 OR refral = :aid2");
        $s1->execute($p);
        $total_users = (int) $s1->fetchColumn();

        $s2 = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE (id_user = :aid1 OR refral = :aid2) AND Status = 'active'");
        $s2->execute($p);
        $active_users = (int) $s2->fetchColumn();

        $s3 = $pdo->prepare("SELECT COALESCE(SUM(price_product), 0) FROM invoice WHERE refral = :aid1");
        $s3->execute([':aid1' => $agent_id]);
        $total_income = (float) $s3->fetchColumn();

        json_out([
            'status' => 'success',
            'stats'  => [
                'total_users'  => $total_users,
                'active_users' => $active_users,
                'total_income' => number_format($total_income) . ' تومان',
            ],
        ]);
    }

    // Unknown action
    json_out(['status' => 'error', 'message' => 'Action نامعتبر است.']);

} catch (PDOException $e) {
    json_out(['status' => 'error', 'message' => 'خطای دیتابیس: ' . $e->getMessage()]);
} catch (Throwable $e) {
    json_out([
        'status'  => 'error',
        'message' => 'خطای سرور: ' . $e->getMessage() . ' (خط ' . $e->getLine() . ' در ' . basename($e->getFile()) . ')',
    ]);
}
