<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../../panels.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'شما دسترسی لازم را ندارید']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر است']);
    exit;
}

// CSRF check
$csrf = $_POST['_csrf'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    echo json_encode(['success' => false, 'message' => 'خطای نشست (CSRF Token)']);
    exit;
}

$id_user = (int)($_POST['id_user'] ?? 0);
$config_name = strtolower(trim($_POST['config_name'] ?? ''));
$panel_name = trim($_POST['panel_name'] ?? '');
$product_name = trim($_POST['product_name'] ?? '');

if (!$id_user || !$config_name || !$panel_name || !$product_name) {
    echo json_encode(['success' => false, 'message' => 'لطفا تمام فیلدها را به دقت پر کنید.']);
    exit;
}

if (!preg_match('/^\w{3,32}$/', $config_name)) {
    echo json_encode(['success' => false, 'message' => 'نام کانفیگ نامعتبر است. فقط حروف انگلیسی و اعداد بین 3 تا 32 کاراکتر مجاز است.']);
    exit;
}

// Check if username exists across invoices in DB
$stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE username = ?");
$stmt->execute([$config_name]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'این نام کانفیگ قبلا در دیتابیس ثبت شده است. نام دیگری انتخاب کنید.']);
    exit;
}

// Find selected product info
$stmt = $pdo->prepare("SELECT * FROM product WHERE name_product = ? AND (Location = ? OR Location = '/all') LIMIT 1");
$stmt->execute([$product_name, $panel_name]);
$info_product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$info_product) {
    echo json_encode(['success' => false, 'message' => 'سرویس انتخابی برای این سرور یافت نشد.']);
    exit;
}

$ManagePanel = new ManagePanel();
$DataUserOut = $ManagePanel->DataUser($panel_name, $config_name);

if ($DataUserOut['status'] == "Unsuccessful") {
    // Config doesn't exist on server, so we can create it
    $datetimestep = strtotime("+" . $info_product['Service_time'] . " days");
    if ($info_product['Service_time'] == 0) {
        $datetimestep = 0;
    } else {
        $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }

    $datac = array(
        'expire' => $datetimestep,
        'data_limit' => $info_product['Service_volume']
    );

    if ($info_product['protocol'] == "wireguard") {
        $datac['protocol'] = [
            'wireguard' => [
                'profile_name' => 'default',
                'activation_date' => date("Y-m-d H:i:s")
            ]
        ];
    } else {
        $datac['proxies'] = [
            'vless' => [
                'flow' => 'xtls-rprx-vision'
            ]
        ];
    }

    $add_user = $ManagePanel->AddUser($panel_name, $config_name, $datac);

    if ($add_user['status'] == "Successful") {
        $username_number = rand(1000000, 9999999);
        $time_sell = time();

        $stmtInsert = $pdo->prepare("INSERT INTO invoice 
            (id_invoice, id_user, price_product, name_product, Service_location, Service_time, Service_volume, username, Service_number, status, time_sell)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
        
        $stmtInsert->execute([
            $username_number,
            $id_user,
            $info_product['price_product'],
            $info_product['name_product'],
            $panel_name,
            $info_product['Service_time'],
            $info_product['Service_volume'],
            $config_name,
            0,
            $time_sell
        ]);

        echo json_encode(['success' => true, 'message' => 'کانفیگ با موفقیت روی سرور ایجاد و در حساب کاربر ثبت شد.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'سرور مرزبان پاسخ ناموفق برگرداند، خطا در ساخت اکانت.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'این نام کانفیگ از قبل در سرور مرزبان وجود دارد و اشغال شده است!']);
}
