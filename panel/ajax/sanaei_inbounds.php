<?php
require_once __DIR__ . '/../inc/config.php';
require_auth();
header('Content-Type: application/json');

$url = trim($_POST['url_panel'] ?? '');
$username = trim($_POST['username_panel'] ?? '');
$password = trim($_POST['password_panel'] ?? '');
$panel_type = trim($_POST['panel_type'] ?? '');
$inboundid = trim($_POST['inboundid'] ?? '');

if (empty($url) || empty($password)) {
    echo json_encode(['success' => false, 'msg' => 'اطلاعات ورود (آدرس و رمزعبور/توکن) ناقص است.']);
    exit;
}

if ($panel_type === 'WGDashboard') {
    $base_url = rtrim($url, '/');
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $base_url . '/api/getWireguardConfigurations',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'wg-dashboard-apikey: ' . $password
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10
    ));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $resData = json_decode($response, true);
    $is_success = ($http_code === 200 && (isset($resData['status']) ? $resData['status'] : true));
    
    if ($is_success && isset($resData['data']) && is_array($resData['data'])) {
        $inbounds = [];
        foreach ($resData['data'] as $item) {
            $name = $item['Name'] ?? '';
            if (!empty($name)) {
                $inbounds[] = [
                    'id' => $name,
                    'remark' => $name,
                    'port' => $item['ListenPort'] ?? '',
                    'protocol' => 'wireguard'
                ];
            }
        }
        echo json_encode(['success' => true, 'msg' => 'اتصال موفق', 'inbounds' => $inbounds]);
        exit;
    } else {
        $msg = $resData['message'] ?? ($resData['msg'] ?? 'توکن API اشتباه است یا پنل در دسترس نیست.');
        echo json_encode(['success' => false, 'msg' => 'اتصال ناموفق: ' . $msg . ' (کد: ' . $http_code . ')']);
        exit;
    }
}

// Temporary cookie file for this session
$cookie_file = sys_get_temp_dir() . '/mhsanaei_' . md5($url . $username . time()) . '.txt';

$base_url = rtrim($url, '/');
$origin_parts = parse_url($base_url);
if (!isset($origin_parts['scheme']) || !isset($origin_parts['host'])) {
    echo json_encode(['success' => false, 'msg' => 'آدرس پنل نامعتبر است.']);
    exit;
}
$origin = $origin_parts['scheme'] . '://' . $origin_parts['host'] . (isset($origin_parts['port']) ? ':' . $origin_parts['port'] : '');

$login_success = false;
$inboundsResponse = '';

// Only try cookie login if username is provided
if (!empty($username)) {
    // 1. Perform Login
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $base_url . '/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => $username,
            'password' => $password,
            'twoFactorCode' => ''
        ]),
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'X-Requested-With: XMLHttpRequest',
            'Origin: ' . $origin,
            'Referer: ' . $base_url . '/login',
        ),
        CURLOPT_COOKIEJAR => $cookie_file,
        CURLOPT_COOKIEFILE => $cookie_file,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10
    ));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code === 200) {
        $loginData = json_decode($response, true);
        if ($loginData && isset($loginData['success']) && $loginData['success']) {
            $login_success = true;
        }
    }
}

if ($login_success) {
    // 2. Fetch Inbounds using cookies
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $base_url . '/panel/api/inbounds/list',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'X-Requested-With: XMLHttpRequest',
            'Origin: ' . $origin,
            'Referer: ' . $base_url . '/panel/inbounds'
        ),
        CURLOPT_COOKIEFILE => $cookie_file,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10
    ));
    $inboundsResponse = curl_exec($curl);
    curl_close($curl);
    @unlink($cookie_file);
} else {
    // Attempt Bearer Token fallback using the password as API token
    @unlink($cookie_file);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $base_url . '/panel/api/inbounds/list',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $password,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'X-Requested-With: XMLHttpRequest',
            'Origin: ' . $origin
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10
    ));
    $inboundsResponse = curl_exec($curl);
    curl_close($curl);
}

$inboundsData = json_decode($inboundsResponse, true);
if (isset($inboundsData['success']) && $inboundsData['success'] && is_array($inboundsData['obj'])) {
    $inbounds = array();
    foreach ($inboundsData['obj'] as $inb) {
        $inbounds[] = [
            'id' => $inb['id'],
            'remark' => $inb['remark'] ?? '',
            'port' => $inb['port'] ?? '',
            'protocol' => $inb['protocol'] ?? ''
        ];
    }
    echo json_encode(['success' => true, 'inbounds' => $inbounds]);
    exit;
}

$error_msg = 'مشکلی در دریافت اینباندها به وجود آمد.';
if (isset($inboundsData['msg'])) {
    $error_msg = $inboundsData['msg'];
} elseif (!$login_success && !empty($username)) {
    $error_msg = 'ورود ناموفق بود. نام کاربری/رمز عبور اشتباه است یا توکن API نامعتبر است (کد خطا: 403).';
}

echo json_encode(['success' => false, 'msg' => $error_msg]);
