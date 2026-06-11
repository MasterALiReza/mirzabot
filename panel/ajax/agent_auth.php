<?php
session_start();
require '../inc/config.php';
require_once __DIR__ . '/../../botapi.php';

header('Content-Type: application/json');

if (!isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'درخواست نامعتبر']);
    exit;
}

$action = $_POST['action'];

if ($action === 'send_otp') {
    $telegram_id = trim($_POST['telegram_id'] ?? '');
    
    if (empty($telegram_id) || !is_numeric($telegram_id)) {
        echo json_encode(['status' => 'error', 'message' => 'آیدی تلگرام نامعتبر است']);
        exit;
    }

    // Check if user is an agent
    $stmt = $pdo->prepare("SELECT id, agent, step FROM user WHERE id = :id");
    $stmt->execute([':id' => $telegram_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'کاربری با این آیدی یافت نشد']);
        exit;
    }

    if (!in_array($user['agent'], ['n', 'n2', 'all'])) {
        echo json_encode(['status' => 'error', 'message' => 'شما دسترسی نمایندگی ندارید']);
        exit;
    }

    // Generate OTP
    $otp = rand(10000, 99999);
    
    // Save in session
    $_SESSION['agent_otp_code'] = $otp;
    $_SESSION['agent_otp_time'] = time();
    $_SESSION['agent_otp_target_id'] = $telegram_id;

    // Send via bot
    $text = "🔐 کد ورود به پنل نمایندگی شما:\n\n";
    $text .= "<code>$otp</code>\n\n";
    $text .= "⚠️ این کد تنها ۲ دقیقه اعتبار دارد. اگر شما درخواست ورود نداده‌اید، این پیام را نادیده بگیرید.";

    $res = telegram('sendmessage', [
        'chat_id' => $telegram_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ]);

    echo json_encode(['status' => 'success', 'message' => 'کد با موفقیت ارسال شد']);
    exit;
}

if ($action === 'verify_otp') {
    $otp_code = trim($_POST['otp_code'] ?? '');
    
    if (empty($otp_code)) {
        echo json_encode(['status' => 'error', 'message' => 'کد را وارد کنید']);
        exit;
    }

    if (!isset($_SESSION['agent_otp_code']) || !isset($_SESSION['agent_otp_target_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'درخواستی یافت نشد، مجددا تلاش کنید']);
        exit;
    }

    // Check time (120 seconds limit)
    if (time() - $_SESSION['agent_otp_time'] > 120) {
        unset($_SESSION['agent_otp_code']);
        echo json_encode(['status' => 'error', 'message' => 'کد منقضی شده است']);
        exit;
    }

    if ((string)$otp_code !== (string)$_SESSION['agent_otp_code']) {
        echo json_encode(['status' => 'error', 'message' => 'کد وارد شده اشتباه است']);
        exit;
    }

    // Success login
    $_SESSION['agent_id'] = $_SESSION['agent_otp_target_id'];
    
    // Clear OTP
    unset($_SESSION['agent_otp_code']);
    unset($_SESSION['agent_otp_target_id']);
    unset($_SESSION['agent_otp_time']);

    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'عملیات نامعتبر']);
