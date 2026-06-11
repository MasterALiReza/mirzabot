<?php
ob_start();
session_start();
require '../inc/config.php';

// Clean any unexpected output before setting JSON header
ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'نشست منقضی شده است']);
    exit;
}

$agent_id = $_SESSION['agent_id'];

// Get action
$action = $_GET['action'] ?? 'get_users';

if ($action === 'get_users') {
    // Pagination and search
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';

    $where = "(id_user = :agent_id OR refral = :agent_id)";
    $params = [':agent_id' => $agent_id];

    if (!empty($search)) {
        $where .= " AND (username LIKE :search OR name_product LIKE :search OR Service_location LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // Count total
    $countSql = "SELECT COUNT(*) as total FROM invoice WHERE $where";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $totalPages = ceil($total / $limit);

    // Get Data
    $sql = "SELECT * FROM invoice WHERE $where ORDER BY time_sell DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require_once __DIR__ . '/../../jdf.php'; // ensure jdate is loaded

    $users = [];
    foreach ($invoices as $inv) {
        $days = (int)$inv['Service_time'];
        $time_sell = (int)$inv['time_sell']; // timestamp
        
        // Calculate remaining days
        $expire_ts = $time_sell + ($days * 86400);
        $rem_days = 0;
        if ($days > 0) {
            $rem_days = ceil(($expire_ts - time()) / 86400);
            if ($rem_days < 0) $rem_days = 0;
        }

        // Format dates
        $start_date = $time_sell > 0 ? jdate('Y/m/d H:i', $time_sell) : 'نامشخص';
        $exp_date = $days > 0 ? jdate('Y/m/d', $expire_ts) : 'نامحدود';

        global $textbotlang;
        $vol_total = (float)$inv['Volume'];
        $name_product = $inv['name_product'];
        $is_test = ($name_product == $textbotlang['Admin']['adminphp']['db_test_service_name']);

        if ($is_test) {
            $formatted_time = $inv['Service_time'] . ' ' . $textbotlang['Admin']['adminphp']['btn_hour'];
            $formatted_vol = $vol_total . ' ' . $textbotlang['Admin']['adminphp']['btn_8'];
        } else {
            $formatted_time = $inv['Service_time'] . ' ' . $textbotlang['Admin']['adminphp']['btn_day_1'];
            $formatted_vol = $vol_total == 0 ? 'نامحدود' : $vol_total . ' ' . $textbotlang['Admin']['adminphp']['btn_9'];
        }

        $status_label = '';
        if ($inv['Status'] === 'active') {
            if ($rem_days === 0 && $days > 0) $status_label = 'منقضی شده';
            else $status_label = 'فعال';
        } else if ($inv['Status'] === 'end_of_time') {
            $status_label = 'پایان زمان';
        } else if ($inv['Status'] === 'end_of_volume') {
            $status_label = 'پایان حجم';
        } else {
            $status_label = 'غیرفعال';
        }

        $users[] = [
            'id' => $inv['id_invoice'],
            'username' => $inv['username'],
            'status' => $inv['Status'] === 'active' ? 'active' : 'inactive',
            'status_label' => $status_label,
            'plan_name' => $inv['name_product'],
            'location' => $inv['Service_location'],
            'created_at' => $start_date,
            'expires_at' => $exp_date,
            'total_gb' => $formatted_vol,
            'service_time_str' => $formatted_time,
            'price' => number_format((float)($inv['price_product'] ?? 0)) . ' تومان'
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $users,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages
        ]
    ]);
    exit;
}

if ($action === 'get_stats') {
    // Basic stats for header
    $params = [':agent_id' => $agent_id];
    
    // Total users
    $stmt1 = $pdo->prepare("SELECT COUNT(*) as c FROM invoice WHERE id_user = :agent_id OR refral = :agent_id");
    $stmt1->execute($params);
    $total_users = $stmt1->fetchColumn();

    // Active users
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as c FROM invoice WHERE (id_user = :agent_id OR refral = :agent_id) AND Status = 'active'");
    $stmt2->execute($params);
    $active_users = $stmt2->fetchColumn();

    // Total income
    $stmt3 = $pdo->prepare("SELECT SUM(price_product) as c FROM invoice WHERE refral = :agent_id");
    $stmt3->execute($params);
    $total_income = (float)($stmt3->fetchColumn() ?: 0);

    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total_users' => $total_users,
            'active_users' => $active_users,
            'total_income' => number_format($total_income) . ' تومان'
        ]
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
