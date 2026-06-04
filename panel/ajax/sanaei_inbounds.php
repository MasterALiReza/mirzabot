<?php
require_once __DIR__ . '/../inc/config.php';
require_auth();
header('Content-Type: application/json');

$url = trim($_POST['url_panel'] ?? '');
$username = trim($_POST['username_panel'] ?? '');
$password = trim($_POST['password_panel'] ?? '');

if (empty($url) || empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'msg' => 'اطلاعات ورود (آدرس، نام کاربری، رمز عبور) ناقص است.']);
    exit;
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

if ($http_code !== 200) {
    echo json_encode(['success' => false, 'msg' => 'ورود ناموفق بود. کد خطا: ' . $http_code]);
    exit;
}

$loginData = json_decode($response, true);
if (!$loginData || !isset($loginData['success']) || !$loginData['success']) {
    echo json_encode(['success' => false, 'msg' => 'نام کاربری یا رمز عبور اشتباه است یا پاسخ پنل معتبر نیست.']);
    exit;
}

// 2. Fetch Inbounds
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

echo json_encode(['success' => false, 'msg' => 'مشکلی در دریافت اینباندها به وجود آمد.']);
