<?php
/**
 * Agent Panel — User Data API
 * Returns JSON for agent's invoice list and stats
 */

// Buffer all output (including any warnings from includes)
ob_start();
session_start();
require '../inc/config.php';
// Discard any HTML/text output from config (including its header())
ob_end_clean();

// Force JSON content-type after clearing buffer
header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'نشست منقضی شده است. لطفاً مجدداً وارد شوید.']);
    exit;
}

$agent_id = (int) $_SESSION['agent_id'];
$action   = $_GET['action'] ?? 'get_users';

// ──────────────────────────────────────────────────────────────────────────────
// Helper to safely encode and output JSON (handles UTF-8)
// ──────────────────────────────────────────────────────────────────────────────
function json_out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
        $where  = '(i.id_user = :aid OR i.refral = :aid)';
        $params = [':aid' => $agent_id];

        if ($search !== '') {
            $where .= ' AND (i.username LIKE :s OR i.name_product LIKE :s OR i.Service_location LIKE :s)';
            $params[':s'] = "%{$search}%";
        }

        // Total count
        $total = (int) $pdo
            ->prepare("SELECT COUNT(*) FROM invoice i WHERE {$where}")
            ->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM invoice i WHERE {$where}")->execute($params) : 0;

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
                : $vol_total . ' ' . ($textbotlang['Admin']['adminphp']['btn_9'] ?? 'گیگ');

            // Status label
            $raw_status = $inv['Status'] ?? '';
            if ($raw_status === 'active') {
                $status_label = ($rem_days === 0 && $days > 0) ? 'منقضی شده' : 'فعال';
                $status_key   = ($rem_days === 0 && $days > 0) ? 'inactive' : 'active';
            } elseif ($raw_status === 'end_of_time') {
                $status_label = 'پایان زمان';
                $status_key   = 'inactive';
            } elseif ($raw_status === 'end_of_volume') {
                $status_label = 'پایان حجم';
                $status_key   = 'inactive';
            } else {
                $status_label = 'غیرفعال';
                $status_key   = 'inactive';
            }

            $users[] = [
                'id'               => $inv['id_invoice'],
                'username'         => $inv['username'] ?? '—',
                'status'           => $status_key,
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
    // ACTION: get_stats
    // ──────────────────────────────────────────────────────────────────────
    if ($action === 'get_stats') {
        $p = [':aid' => $agent_id];

        $s1 = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = :aid OR refral = :aid");
        $s1->execute($p);
        $total_users = (int) $s1->fetchColumn();

        $s2 = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE (id_user = :aid OR refral = :aid) AND Status = 'active'");
        $s2->execute($p);
        $active_users = (int) $s2->fetchColumn();

        $s3 = $pdo->prepare("SELECT COALESCE(SUM(price_product), 0) FROM invoice WHERE refral = :aid");
        $s3->execute($p);
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
