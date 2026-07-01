<?php
ob_start();
require '../inc/config.php';
require_once __DIR__ . '/../../botapi.php';
require_once __DIR__ . '/../../MHSanaei-3.2.php';

ob_end_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'نشست منقضی شده است']);
    exit;
}

$agent_id = $_SESSION['agent_id'];
$action = $_POST['action'] ?? '';

// Check agent access level
$stmtAgent = $pdo->prepare("SELECT Balance, agent FROM user WHERE id = :id");
$stmtAgent->execute([':id' => $agent_id]);
$agentUser = $stmtAgent->fetch(PDO::FETCH_ASSOC);

if (!$agentUser || !in_array($agentUser['agent'], ['n', 'n2', 'all'])) {
    echo json_encode(['status' => 'error', 'message' => 'شما دسترسی نمایندگی ندارید']);
    exit;
}

$agentType = $agentUser['agent'];
$wallet = (float)($agentUser['Balance'] ?? 0);

if ($action === 'create_user') {
    $location = $_POST['location'] ?? '';
    $product_id = $_POST['product_id'] ?? '';
    $username_req = trim($_POST['username'] ?? '');

    if (empty($location) || empty($product_id)) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    // Check product access
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :id AND (agent = :agent OR agent = 'all') AND (Location = :loc OR Location = '/all') LIMIT 1");
    $stmt->execute([':id' => $product_id, ':agent' => $agentType, ':loc' => $location]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'پلن نامعتبر یا عدم دسترسی']);
        exit;
    }

    $price = (float)$product['price_product'];
    if ($wallet < $price) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست']);
        exit;
    }

    // Determine Username
    $username = $username_req;
    if (empty($username)) {
        $username = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 5) . rand(1000, 9999);
    }

    $Data_Config = [
        'expire' => $product['Service_time'] == 0 ? 0 : strtotime('+' . $product['Service_time'] . ' days'),
        'data_limit' => $product['Volume_constraint'] == 0 ? 0 : $product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $agent_id,
        'username' => 'AgentPanel',
        'type' => 'buy'
    ];

    // --- ATOMIC WALLET DEDUCTION FIRST (PREVENT RACE CONDITIONS) ---
    if ($price > 0) {
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND Balance >= :p");
        $stmtW->execute([':p' => $price, ':id' => $agent_id]);
        if ($stmtW->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست یا تراکنش همزمان رخ داده است.']);
            exit;
        }
    }

    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $location]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $location;

    // Call Creation API
    $args = [$name_panel, $product['code_product'], $username, $Data_Config];
    $response = MHSanaei_router('createUser', $args);

    if (isset($response['status']) && $response['status'] === 'successful') {

        // Insert Invoice
        $randomString = bin2hex(random_bytes(4));
        $notifctions = json_encode(['volume' => false, 'time' => false]);
        $link_sub = $response['subscription_url'] ?? '';

        if (empty($link_sub) && !empty($response['configs'])) {
            $link_sub = is_array($response['configs']) ? implode("\n", $response['configs']) : $response['configs'];
        }

        $stmtInv = $pdo->prepare("INSERT IGNORE INTO invoice 
            (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status, notifctions, refral, link_sub) 
            VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status, :notifctions, :refral, :link_sub)");

        $stmtInv->execute([
            ':id_user' => $agent_id,
            ':id_invoice' => $randomString,
            ':username' => $username,
            ':time_sell' => time(),
            ':Service_location' => $location,
            ':name_product' => $product['name_product'],
            ':price_product' => $price,
            ':Volume' => $product['Volume_constraint'],
            ':Service_time' => $product['Service_time'],
            ':Status' => 'active',
            ':notifctions' => $notifctions,
            ':refral' => $agent_id,
            ':link_sub' => $link_sub
        ]);

        echo json_encode(['status' => 'success', 'message' => 'سرویس ساخته شد']);
    } else {
        // --- REFUND WALLET IF API FAILED ---
        if ($price > 0) {
            $stmtR = $pdo->prepare("UPDATE user SET Balance = Balance + :p WHERE id = :id");
            $stmtR->execute([':p' => $price, ':id' => $agent_id]);
        }
        echo json_encode(['status' => 'error', 'message' => 'خطا در ارتباط با پنل: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'renew_user') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    $product_id = $_POST['product_id'] ?? '';

    if (empty($invoice_id) || empty($product_id)) {
        echo json_encode(['status' => 'error', 'message' => 'اطلاعات ناقص است']);
        exit;
    }

    // Verify Invoice Ownership
    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid OR refral = :uid) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر یا عدم دسترسی']);
        exit;
    }

    // Check product access
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = :id AND (agent = :agent OR agent = 'all') AND (Location = :loc OR Location = '/all') LIMIT 1");
    $stmt->execute([':id' => $product_id, ':agent' => $agentType, ':loc' => $invoice['Service_location']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'پلن نامعتبر']);
        exit;
    }

    $price = (float)$product['price_product'];
    if ($wallet < $price) {
        echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست']);
        exit;
    }

    // --- ATOMIC WALLET DEDUCTION FIRST ---
    if ($price > 0) {
        $stmtW = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND Balance >= :p");
        $stmtW->execute([':p' => $price, ':id' => $agent_id]);
        if ($stmtW->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'موجودی کیف پول شما کافی نیست یا تراکنش همزمان رخ داده است.']);
            exit;
        }
    }

    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $invoice['Service_location']]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $invoice['Service_location'];

    // Call Renew API (extend)
    // extend args: list($Method_extend, $new_limit, $time_day, $username, $code_product, $name_panel) = $args;
    global $textbotlang;
    $method = $textbotlang['keyboard']['resetVolumeAddTime'] ?? 'ریست حجم زمان قبلی و اضافه شدن حجم زمان جدید';
    $args = [$method, $product['Volume_constraint'], $product['Service_time'], $invoice['username'], $product['code_product'], $name_panel];
    $response = MHSanaei_router('extend', $args);

    if (isset($response['status']) && $response['status'] === true) {

        // Update Invoice
        $stmtU = $pdo->prepare("UPDATE invoice SET Status = 'active', time_sell = :time_sell, price_product = :p, Volume = :v, Service_time = :st WHERE id_invoice = :id");
        $stmtU->execute([
            ':time_sell' => time(),
            ':p' => $price,
            ':v' => $product['Volume_constraint'],
            ':st' => $product['Service_time'],
            ':id' => $invoice_id
        ]);

        echo json_encode(['status' => 'success', 'message' => 'سرویس تمدید شد']);
    } else {
        // --- REFUND WALLET IF API FAILED ---
        if ($price > 0) {
            $stmtR = $pdo->prepare("UPDATE user SET Balance = Balance + :p WHERE id = :id");
            $stmtR->execute([':p' => $price, ':id' => $agent_id]);
        }
        echo json_encode(['status' => 'error', 'message' => 'خطا در ارتباط با پنل: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'delete_user') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    if (empty($invoice_id)) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر']);
        exit;
    }

    // Verify Invoice
    $stmtInv = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = :id AND (id_user = :uid OR refral = :uid) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // Fetch Panel Name
    $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
    $stmtPnl->execute([':code' => $invoice['Service_location']]);
    $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
    $name_panel = $panelData ? $panelData['name_panel'] : $invoice['Service_location'];

    // Call Delete API
    $args = [$invoice['username'], $name_panel];
    $response = MHSanaei_router('RemoveUser', $args);

    if (isset($response['status']) && $response['status'] === true) {
        // Delete or mark inactive
        $stmtD = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = :id");
        $stmtD->execute([':id' => $invoice_id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف: ' . ($response['msg'] ?? 'ناشناخته')]);
    }
    exit;
}

if ($action === 'get_link') {
    $invoice_id = $_POST['invoice_id'] ?? '';
    
    $stmtInv = $pdo->prepare("SELECT link_sub, username, Service_location FROM invoice WHERE id_invoice = :id AND (id_user = :uid OR refral = :uid) LIMIT 1");
    $stmtInv->execute([':id' => $invoice_id, ':uid' => $agent_id]);
    $invoice = $stmtInv->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'فاکتور نامعتبر']);
        exit;
    }

    $link = $invoice['link_sub'];
    
    // Fallback if link is not in DB but can be retrieved via DataUser
    if (empty($link)) {
        // Fetch Panel Name
        $stmtPnl = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE code_panel = :code LIMIT 1");
        $stmtPnl->execute([':code' => $invoice['Service_location']]);
        $panelData = $stmtPnl->fetch(PDO::FETCH_ASSOC);
        $name_panel = $panelData ? $panelData['name_panel'] : $invoice['Service_location'];

        $res = MHSanaei_router('DataUser', [$name_panel, $invoice['username']]);
        if (isset($res['status']) && $res['status'] !== 'Unsuccessful') {
            $link = $res['subscription_url'] ?? '';
            if (empty($link) && !empty($res['configs'])) {
                $link = is_array($res['configs']) ? implode("\n", $res['configs']) : $res['configs'];
            }
        }
    }

    echo json_encode(['status' => 'success', 'link' => $link]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'عملیات نامعتبر']);
