<?php
require_once 'config.php';
require_once 'request.php';
ini_set('error_log', 'error_log');
function panel_login_cookie($code_panel)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $base_url = rtrim($panel['url_panel'], '/');
    
    // MHSanaei 3.2+ requires a CSRF token for the /login endpoint
    $csrf_token = '';
    $curl_csrf = curl_init();
    curl_setopt_array($curl_csrf, array(
        CURLOPT_URL => $base_url . '/csrf-token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT_MS => 5000,
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ),
        CURLOPT_COOKIEJAR => 'cookie.txt',
        CURLOPT_COOKIEFILE => 'cookie.txt',
    ));
    $csrf_resp = curl_exec($curl_csrf);
    $csrf_code = curl_getinfo($curl_csrf, CURLINFO_HTTP_CODE);
    if ($csrf_code == 200) {
        $csrf_dec = json_decode($csrf_resp, true);
        if (isset($csrf_dec['success']) && $csrf_dec['success'] && isset($csrf_dec['obj'])) {
            $csrf_token = $csrf_dec['obj'];
        }
    }
    curl_close($curl_csrf);
    
    // We try with /login first (standard), then without /login (for custom MHSanaei paths)
    $login_urls = [
        $base_url . '/login',
        $base_url
    ];
    
    $payload_json = json_encode(array(
        'username' => $panel['username_panel'],
        'password' => $panel['password_panel'],
        'twoFactorCode' => ''
    ));
    $payload_form = "username={$panel['username_panel']}&password=" . urlencode($panel['password_panel']);
    
    $debug_info = [];
    $debug_info[] = "CSRF fetch HTTP code: " . $csrf_code;
    if ($csrf_token) {
        $debug_info[] = "CSRF Fetched";
    }

    foreach ($login_urls as $url) {
        $headers_json = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        );
        if ($csrf_token) {
            $headers_json[] = 'X-CSRF-Token: ' . $csrf_token;
        }

        // Attempt 1: JSON payload (supported by MHSanaei 3.2+ and modern x-ui)
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT_MS => 10000,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload_json,
            CURLOPT_HTTPHEADER => $headers_json,
            CURLOPT_COOKIEJAR => 'cookie.txt',
        ));
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($curl);
        if (!$curl_err && $http_code == 200) {
            $decoded = json_decode($response, true);
            if (isset($decoded['success'])) {
                if ($decoded['success']) {
                    curl_close($curl);
                    return $response;
                } else {
                    // Valid panel response but login failed (e.g., wrong password)
                    curl_close($curl);
                    return $response;
                }
            }
        }
        $debug_info[] = "JSON [$url]: HTTP $http_code" . ($curl_err ? " ($curl_err)" : "");
        curl_close($curl);
        
        // Attempt 2: urlencoded form POST (fallback for old x-ui panels)
        $headers_form = array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        );
        if ($csrf_token) {
            $headers_form[] = 'X-CSRF-Token: ' . $csrf_token;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT_MS => 10000,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload_form,
            CURLOPT_HTTPHEADER => $headers_form,
            CURLOPT_COOKIEJAR => 'cookie.txt',
        ));
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($curl);
        if (!$curl_err && $http_code == 200) {
            $decoded = json_decode($response, true);
            if (isset($decoded['success'])) {
                if ($decoded['success']) {
                    curl_close($curl);
                    return $response;
                } else {
                    // Valid panel response but login failed (e.g., wrong password)
                    curl_close($curl);
                    return $response;
                }
            }
        }
        $debug_info[] = "Form [$url]: HTTP $http_code" . ($curl_err ? " ($curl_err)" : "");
        curl_close($curl);
    }
    
    return json_encode(array(
        'success' => false,
        'msg' => 'Login failed. Details: ' . implode(" | ", $debug_info)
    ));
}
function login($code_panel, $verify = true)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    if ($panel['datelogin'] != null && $verify) {
        $date = json_decode($panel['datelogin'], true);
        if (isset($date['time'])) {
            $timecurrent = time();
            $start_date = time() - strtotime($date['time']);
            if ($start_date <= 3000) {
                file_put_contents('cookie.txt', $date['access_token']);
                return;
            }
        }
    }
    $response = panel_login_cookie($panel['code_panel']);
    $time = date('Y/m/d H:i:s');
    $data = json_encode(array(
        'time' => $time,
        'access_token' => file_get_contents('cookie.txt')
    ));
    update("marzban_panel", "datelogin", $data, 'name_panel', $panel['name_panel']);
    if (!is_string($response))
        return array('success' => false);
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        return array('success' => false, 'msg' => 'Invalid panel response');
    }
    return $decoded;
}

function get_clinets($username, $namepanel)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    $url = rtrim($marzban_list_get['url_panel'], '/') . "/panel/api/inbounds/getClientTraffics/$username";
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie('cookie.txt');
    $response = $req->get();

    if (isset($response['body'])) {
        $decodedBody = json_decode($response['body'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBody)) {
            if (isset($decodedBody['success']) && $decodedBody['success'] === false) {
                $response['error'] = $decodedBody['msg'] ?? 'Unknown panel error';
            }
        }
    }

    if (!empty($response['error'])) {
        error_log(json_encode($response));
    }

    if (is_file('cookie.txt')) {
        @unlink('cookie.txt');
    }

    return $response;
}
function addClient($namepanel, $usernameac, $Expire, $Total, $Uuid, $Flow, $subid, $inboundid, $name_product, $note = "")
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    if ($name_product == "usertest") {
        if ($marzban_list_get['on_hold_test'] == "1") {
            if ($Expire == 0) {
                $timeservice = 0;
            } else {
                $timelast = $Expire - time();
                $timeservice = -intval(($timelast / 86400) * 86400000);
            }
        } else {
            $timeservice = $Expire * 1000;
        }
    } else {
        if ($marzban_list_get['conecton'] == "onconecton") {
            if ($Expire == 0) {
                $timeservice = 0;
            } else {
                $timelast = $Expire - time();
                $timeservice = -intval(($timelast / 86400) * 86400000);
            }
        } else {
            $timeservice = $Expire * 1000;
        }
    }
    $config = array(
        "id" => intval($inboundid),
        'settings' => json_encode(array(
            'clients' => array(
                array(
                    "id" => $Uuid,
                    "flow" => $Flow,
                    "email" => $usernameac,
                    "totalGB" => $Total,
                    "expiryTime" => $timeservice,
                    "enable" => true,
                    "tgId" => "",
                    "subId" => $subid,
                    "reset" => 0,
                    "comment" => $note
                )
            ),
            'decryption' => 'none',
            'fallbacks' => array(),
        ))
    );
    if (!isset($usernameac))
        return array(
            'status' => 500,
            'msg' => 'username is null'
        );
    $configpanel = json_encode($config, true);
    $url = rtrim($marzban_list_get['url_panel'], '/') . '/panel/api/inbounds/addClient';
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie('cookie.txt');
    $response = $req->post($configpanel);
    unlink('cookie.txt');
    return $response;
}
function updateClient($namepanel, $uuid, array $config)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    $configpanel = json_encode($config, true);
    $url = rtrim($marzban_list_get['url_panel'], '/') . '/panel/api/inbounds/updateClient/' . $uuid;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie('cookie.txt');
    $response = $req->post($configpanel);
    unlink('cookie.txt');
    return $response;
}
function ResetUserDataUsagex_uisin($usernamepanel, $namepanel)
{
    $data_user = get_clinets($usernamepanel, $namepanel);
    $data_user = json_decode($data_user['body'], true)['obj'];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    login($marzban_list_get['code_panel']);
    $url = rtrim($marzban_list_get['url_panel'], '/') . "/panel/api/inbounds/{$data_user['inboundId']}/resetClientTraffic/" . $usernamepanel;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie('cookie.txt');
    $response = $req->post(array());
    unlink('cookie.txt');
    return $response;
}
function removeClient($location, $username)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    login($marzban_list_get['code_panel']);
    $url = rtrim($marzban_list_get['url_panel'], '/') . "/panel/api/inbounds/{$marzban_list_get['inboundid']}/delClientByEmail/" . $username;
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie('cookie.txt');
    $response = $req->post(array());
    unlink('cookie.txt');
    return $response;
}