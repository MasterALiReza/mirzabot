<?php
require_once 'config.php';
require_once 'request.php';

function mhsanaei_cookie_file($code_panel)
{
    $safe_code = preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string)$code_panel);
    $dir = sys_get_temp_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = __DIR__;
    }
    return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'mirzabot_mhsanaei_' . md5(__DIR__) . '_' . $safe_code . '.txt';
}

function mhsanaei_prepare_cookie_file($cookie_file, $reset = false)
{
    $dir = dirname($cookie_file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    if ($reset && is_file($cookie_file)) {
        @unlink($cookie_file);
    }
    if (!is_file($cookie_file) && @file_put_contents($cookie_file, '') === false) {
        return false;
    }
    return is_writable($cookie_file);
}

function mhsanaei_base_url($panel)
{
    return rtrim(trim($panel['url_panel']), '/');
}

function mhsanaei_origin($base_url)
{
    $parts = parse_url($base_url);
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }
    return preg_replace('/^(https?:\/\/[^\/]+).*$/i', '$1', $base_url);
}

function mhsanaei_json_error($msg)
{
    return json_encode(array('success' => false, 'msg' => $msg), JSON_UNESCAPED_UNICODE);
}

function mhsanaei_fetch_csrf($base_url, $cookie_file)
{
    if (!mhsanaei_prepare_cookie_file($cookie_file)) {
        return array('success' => false, 'msg' => 'Cookie file is not writable');
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $base_url . '/csrf-token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT_MS => 10000,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'X-Requested-With: XMLHttpRequest'
        ),
        CURLOPT_COOKIEJAR => $cookie_file,
        CURLOPT_COOKIEFILE => $cookie_file,
    ));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($curl_error) {
        return array('success' => false, 'msg' => $curl_error);
    }
    if ($http_code != 200) {
        return array('success' => false, 'msg' => 'CSRF HTTP Error ' . $http_code);
    }

    $decoded = json_decode($response, true);
    if (isset($decoded['success']) && $decoded['success'] && !empty($decoded['obj'])) {
        return array('success' => true, 'token' => $decoded['obj']);
    }

    return array('success' => false, 'msg' => 'CSRF token not received');
}

function mhsanaei_send_request($url, $method, array $headers, $cookie_file = null, $data = null)
{
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    if ($cookie_file && is_file($cookie_file)) {
        $req->setCookie($cookie_file);
    }

    $method = strtoupper($method);
    $postData = is_array($data) ? json_encode($data) : $data;
    if ($method == 'POST') {
        return $req->post($postData);
    }
    if ($method == 'PUT') {
        return $req->put($postData);
    }
    if ($method == 'DELETE') {
        return $req->delete($postData);
    }
    if ($method == 'PATCH') {
        return $req->PATCH($postData);
    }
    return $req->get();
}

function mhsanaei_decode_panel_response($response)
{
    if (!isset($response['body'])) {
        return null;
    }
    return json_decode($response['body'], true);
}

function mhsanaei_bearer_request($url, $method, $panel, $data = null)
{
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $panel['password_panel']
    );
    return mhsanaei_send_request($url, $method, $headers, null, $data);
}

function panel_login_cookie_MHSanaei($code_panel)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $base_url = mhsanaei_base_url($panel);
    $cookie_file = mhsanaei_cookie_file($code_panel);

    if (!mhsanaei_prepare_cookie_file($cookie_file, true)) {
        return mhsanaei_json_error('Cookie file is not writable');
    }

    $csrf = mhsanaei_fetch_csrf($base_url, $cookie_file);
    if (empty($csrf['success'])) {
        return mhsanaei_json_error('CSRF failed: ' . ($csrf['msg'] ?? 'Unknown error'));
    }

    $origin = mhsanaei_origin($base_url);
    $headers_form = array(
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'X-Requested-With: XMLHttpRequest',
        'Origin: ' . $origin,
        'Referer: ' . $base_url . '/login',
        'X-CSRF-Token: ' . $csrf['token']
    );

    $payload_form = http_build_query(array(
        'username' => trim((string)$panel['username_panel']),
        'password' => (string)$panel['password_panel'],
        'twoFactorCode' => ''
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $base_url . '/login',
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
        CURLOPT_COOKIEJAR => $cookie_file,
        CURLOPT_COOKIEFILE => $cookie_file,
    ));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        return mhsanaei_json_error($curl_error . ' | HTTP: ' . $http_code);
    }
    
    if ($http_code != 200) {
        return mhsanaei_json_error('HTTP Error ' . $http_code);
    }
    
    return $response;
}

function login_MHSanaei($code_panel, $verify = true)
{
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $cookie_file = mhsanaei_cookie_file($panel['code_panel']);

    if ($panel['datelogin'] != null && $verify) {
        $date = json_decode($panel['datelogin'], true);
        if (isset($date['time'], $date['access_token']) && trim($date['access_token']) !== '') {
            $time = $date['time'];
            $time = strtotime($time);
            $time = $time + 60 * 60;
            if ($time > time()) {
                if (mhsanaei_prepare_cookie_file($cookie_file, true) && @file_put_contents($cookie_file, $date['access_token']) !== false) {
                    return array('success' => true);
                }
            }
        }
    }

    $response = panel_login_cookie_MHSanaei($panel['code_panel']);

    if (!is_string($response)) {
        return array('success' => false);
    }

    $decoded = json_decode($response, true);
    if (isset($decoded['success']) && $decoded['success'] && is_file($cookie_file) && filesize($cookie_file) > 0) {
        $data = json_encode(array(
            'time' => date('Y/m/d H:i:s'),
            'access_token' => file_get_contents($cookie_file)
        ));
        update("marzban_panel", "datelogin", $data, 'name_panel', $panel['name_panel']);
    } elseif (isset($decoded['success']) && !$decoded['success']) {
        update("marzban_panel", "datelogin", null, 'name_panel', $panel['name_panel']);
    }

    return is_array($decoded) ? $decoded : array('success' => false, 'msg' => 'Invalid panel response');
}

function request_MHSanaei($url, $method, $panel, $data = null) {
    $login = login_MHSanaei($panel['code_panel']);
    $base_url = mhsanaei_base_url($panel);
    $cookie_file = mhsanaei_cookie_file($panel['code_panel']);
    $csrf_token = '';

    if (empty($login['success'])) {
        $bearer_response = mhsanaei_bearer_request($url, $method, $panel, $data);
        $bearer_decoded = mhsanaei_decode_panel_response($bearer_response);
        if ($bearer_decoded !== null) {
            return $bearer_decoded;
        }
        return $login;
    }

    if (!in_array(strtoupper($method), array('GET', 'HEAD', 'OPTIONS', 'TRACE'))) {
        $csrf = mhsanaei_fetch_csrf($base_url, $cookie_file);
        if (!empty($csrf['success'])) {
            $csrf_token = $csrf['token'];
        } else {
            $bearer_response = mhsanaei_bearer_request($url, $method, $panel, $data);
            $bearer_decoded = mhsanaei_decode_panel_response($bearer_response);
            if ($bearer_decoded !== null) {
                return $bearer_decoded;
            }
            return array('success' => false, 'msg' => 'CSRF failed: ' . ($csrf['msg'] ?? 'Unknown error'));
        }
    }

    $origin = mhsanaei_origin($base_url);
    $headers = array(
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'X-Requested-With: XMLHttpRequest',
        'Origin: ' . $origin,
        'Referer: ' . $base_url . '/'
    );
    if ($csrf_token) {
        $headers[] = 'X-CSRF-Token: ' . $csrf_token;
    }

    $response = mhsanaei_send_request($url, $method, $headers, $cookie_file, $data);
    
    if (isset($response['body'])) {
        $decoded = json_decode($response['body'], true);
        if ($decoded !== null && !(isset($decoded['success']) && $decoded['success'] === false && strpos($response['body'], 'Unauthorized') !== false)) {
            return $decoded;
        }
    }
    
    // Fallback: If cookie failed (maybe user actually put an API token in password field)
    $response = mhsanaei_bearer_request($url, $method, $panel, $data);

    if (isset($response['body'])) {
        $decoded = json_decode($response['body'], true);
        if ($decoded !== null) {
            return $decoded;
        }
    }

    return array('success' => false, 'msg' => 'Invalid panel response');
}

function get_client_MHSanaei($email, $namepanel) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/get/' . urlencode($email);
    return request_MHSanaei($url, 'GET', $panel);
}

function addClient_MHSanaei($namepanel, $usernameac, $Expire, $Total, $subid, $inboundid, $name_product, $note = "", $tgId = "") {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    
    if ($name_product == "usertest") {
        if ($panel['on_hold_test'] == "1") {
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
        if ($panel['conecton'] == "onconecton") {
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

    $limitIp = isset($panel['limit_panel']) && is_numeric($panel['limit_panel']) ? intval($panel['limit_panel']) : 0;
    
    $inbounds_array = [];
    if (!empty($inboundid)) {
        if ($inboundid == "all" || $inboundid == "0") {
            $url_inbounds = rtrim($panel['url_panel'], '/') . '/panel/api/inbounds/options';
            $res_inbounds = request_MHSanaei($url_inbounds, 'GET', $panel);
            if (isset($res_inbounds['success']) && $res_inbounds['success'] && is_array($res_inbounds['obj'])) {
                foreach ($res_inbounds['obj'] as $inb) {
                    $inbounds_array[] = intval($inb['id']);
                }
            }
        } else {
            $inbounds_array = array_map('intval', explode(',', $inboundid));
        }
    }

    $data = array(
        "client" => array(
            "email" => $usernameac,
            "enable" => true,
            "totalGB" => $Total,
            "expiryTime" => $timeservice,
            "subId" => $subid,
            "comment" => $note,
            "reset" => 0,
            "tgId" => (string)$tgId,
            "limitIp" => $limitIp
        ),
        "inboundIds" => $inbounds_array
    );
    
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/add';
    return request_MHSanaei($url, 'POST', $panel, $data);
}

function ResetUserDataUsage_MHSanaei($email, $namepanel) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/resetTraffic/' . urlencode($email);
    return request_MHSanaei($url, 'POST', $panel);
}

function removeClient_MHSanaei($namepanel, $email) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/del/' . urlencode($email);
    return request_MHSanaei($url, 'POST', $panel);
}

function get_online_MHSanaei($namepanel, $email) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/onlines';
    $response = request_MHSanaei($url, 'POST', $panel);
    
    if (isset($response['success']) && $response['success']) {
        if (in_array($email, $response['obj'])) {
            return "online";
        }
    }
    return "offline";
}

function get_subLinks_MHSanaei($namepanel, $subid) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/subLinks/' . urlencode($subid);
    return request_MHSanaei($url, 'GET', $panel);
}

function MHSanaei_router($methodName, $args) {
    global $domainhosts, $pdo;

    switch ($methodName) {
        case 'createUser':
            list($name_panel, $code_product, $usernameC, $Data_Config) = $args;
            $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $Get_Data_Product = array('name_product' => null, 'inbounds' => null);
            if (!in_array($code_product, ["usertest", "customvolume"])) {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :name_panel OR Location = '/all')  AND code_product = :code_product");
                $stmt->bindParam(':name_panel', $name_panel);
                $stmt->bindParam(':code_product', $code_product);
                $stmt->execute();
                $Get_Data_Product = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($code_product == "usertest") {
                $Get_Data_Product['name_product'] = "usertest";
            }
            $expire = $Data_Config['expire'];
            $data_limit = $Data_Config['data_limit'];
            $note = "{$Data_Config['from_id']} | {$Data_Config['username']} | {$Data_Config['type']}";
            
            $subId = bin2hex(random_bytes(8));
            $inbounds = ($Get_Data_Product['inbounds'] != null) ? $Get_Data_Product['inbounds'] : $Get_Data_Panel['inboundid'];
            
            $data_Output = addClient_MHSanaei($name_panel, $usernameC, $expire, $data_limit, $subId, $inbounds, $Get_Data_Product['name_product'], $note, isset($Data_Config['from_id']) ? $Data_Config['from_id'] : "");
            if (isset($data_Output['msg']) && !$data_Output['success']) {
                return array('status' => 'Unsuccessful', 'msg' => $data_Output['msg']);
            } elseif (isset($data_Output['success']) && !$data_Output['success']) {
                return array('status' => 'Unsuccessful', 'msg' => 'Panel Error');
            } else {
                $Output = ['status' => 'successful', 'username' => $usernameC];
                $subLinksRes = get_subLinks_MHSanaei($name_panel, $subId);
                $Output['configs'] = (isset($subLinksRes['success']) && $subLinksRes['success']) ? $subLinksRes['obj'] : [];
                $domain = (!empty($Get_Data_Panel['linksubx']) && $Get_Data_Panel['linksubx'] != "none") ? rtrim($Get_Data_Panel['linksubx'], '/') : rtrim($Get_Data_Panel['url_panel'], '/');
                $Output['subscription_url'] = $domain . '/sub/' . $subId;
                
                $inoice = ($Get_Data_Panel['subvip'] == "onsubvip") ? select("invoice", "*", "username", $usernameC, "select") : false;
                if ($inoice != false) {
                    $Output['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                return $Output;
            }
            
        case 'DataUser':
            list($name_panel, $username) = $args;
            $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            if (isset($user_data_res['success']) && !$user_data_res['success']) {
                return array('status' => 'Unsuccessful', 'msg' => isset($user_data_res['msg']) ? $user_data_res['msg'] : 'User not found');
            } elseif (!isset($user_data_res['obj'])) {
                return array('status' => 'Unsuccessful', 'msg' => "User not found");
            } else {
                $user_data = $user_data_res['obj'];
            $user_ptr = &$user_data;
            if (isset($user_data['client']) && is_array($user_data['client'])) $user_ptr = &$user_data['client'];
                $expire = $user_ptr['expiryTime'] / 1000;
                $status_user = $user_ptr['enable'] ? "active" : "disabled";
                
                $current_total = isset($user_ptr['totalGB']) ? $user_ptr['totalGB'] : (isset($user_ptr['total']) ? $user_ptr['total'] : 0);
                if (intval($current_total) != 0) {
                    if ((intval($current_total) - ($user_ptr['up'] + $user_ptr['down'])) <= 0) $status_user = "limited";
                }
                if (intval($user_ptr['expiryTime']) != 0) {
                    if ($expire - time() <= 0) $status_user = "expired";
                }
                
                $subLinksRes = get_subLinks_MHSanaei($name_panel, $user_ptr['subId']);
                $links_user = (isset($subLinksRes['success']) && $subLinksRes['success']) ? $subLinksRes['obj'] : [];
                $domain = (!empty($Get_Data_Panel['linksubx']) && $Get_Data_Panel['linksubx'] != "none") ? rtrim($Get_Data_Panel['linksubx'], '/') : rtrim($Get_Data_Panel['url_panel'], '/');
                $subscription_url = $domain . '/sub/' . $user_ptr['subId'];
                $inoice = (isset($Get_Data_Panel['subvip']) && $Get_Data_Panel['subvip'] == "onsubvip") ? select("invoice", "*", "username", $username, "select") : false;
                if ($inoice != false) $subscription_url = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                
                $is_online = get_online_MHSanaei($name_panel, $username);
                return array(
                    'status' => $status_user,
                    'username' => $user_ptr['email'],
                    'data_limit' => $current_total,
                    'expire' => $expire,
                    'online_at' => $is_online,
                    'used_traffic' => $user_ptr['up'] + $user_ptr['down'],
                    'links' => $links_user,
                    'subscription_url' => $subscription_url,
                    'sub_updated_at' => null,
                    'sub_last_user_agent' => null,
                );
            }
            
        case 'Revoke_sub':
            list($name_panel, $username) = $args;
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => 'Unsuccessful', 'msg' => 'Unsuccessful');
            $user_data = $user_data_res['obj'];
            $user_ptr = &$user_data;
            if (isset($user_data['client']) && is_array($user_data['client'])) $user_ptr = &$user_data['client'];
            $user_ptr['subId'] = bin2hex(random_bytes(8)); // Update subId to revoke
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/update/' . urlencode($username);
            $update = request_MHSanaei($url, 'POST', $panel, $user_data);
            if (isset($update['success']) && $update['success']) {
                $domain = (!empty($panel['linksubx']) && $panel['linksubx'] != "none") ? rtrim($panel['linksubx'], '/') : rtrim($panel['url_panel'], '/');
                return array(
                    'status' => 'successful',
                    'configs' => [ $domain . "/sub/" . $user_ptr['subId'] ],
                    'subscription_url' => $domain . "/sub/" . $user_ptr['subId']
                );
            }
            return array('status' => 'Unsuccessful', 'msg' => 'Unsuccessful');
            
        case 'RemoveUser':
            list($name_panel, $username) = $args;
            $res = removeClient_MHSanaei($name_panel, $username);
            if (!$res['success']) return array('status' => 'Unsuccessful', 'msg' => $res['msg'] ?? 'Error');
            return array('status' => 'successful', 'username' => $username);
            
        case 'Modifyuser':
            list($username, $name_panel, $config) = $args;
            // config contains settings like enable, totalGB etc. We translate this to 3x-ui fields.
            // But since panels.php for x-ui_single passes complex JSON, we must adapt.
            // Actually, we can fetch the user, patch it, and update.
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => false, 'msg' => 'User not found');
            $user_data = $user_data_res['obj'];
            $user_ptr = &$user_data;
            if (isset($user_data['client']) && is_array($user_data['client'])) $user_ptr = &$user_data['client'];
            if (isset($config['settings'])) {
                $sets_decoded = json_decode($config['settings'], true);
                if (isset($sets_decoded['clients'][0])) {
                    $sets = $sets_decoded['clients'][0];
                    if (isset($sets['enable'])) $user_ptr['enable'] = $sets['enable'];
                    if (isset($sets['totalGB'])) $user_ptr['totalGB'] = $sets['totalGB'];
                    if (isset($sets['expiryTime'])) $user_ptr['expiryTime'] = $sets['expiryTime'];
                }
            }
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/update/' . urlencode($username);
            $update = request_MHSanaei($url, 'POST', $panel, $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => true, 'data' => $update);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'Change_status':
            list($username, $name_panel) = $args;
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => 'Unsuccessful', 'msg' => 'User not found');
            $user_data = $user_data_res['obj'];
            $user_ptr = &$user_data;
            if (isset($user_data['client']) && is_array($user_data['client'])) $user_ptr = &$user_data['client'];
            $user_ptr['enable'] = !$user_ptr['enable']; // Toggle status
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/update/' . urlencode($username);
            $update = request_MHSanaei($url, 'POST', $panel, $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => 'successful', 'msg' => null);
            return array('status' => 'Unsuccessful', 'msg' => 'Error');
            
        case 'ResetUserDataUsage':
            list($username, $name_panel) = $args;
            $res = ResetUserDataUsage_MHSanaei($username, $name_panel);
            if (isset($res['success']) && $res['success']) return array('status' => true, 'msg' => 'successful');
            return array('status' => false, 'msg' => 'Error');
            
        case 'extend':
            list($Method_extend, $new_limit, $time_day, $username, $code_product, $name_panel) = $args;
            // Similar to Modifyuser, we patch the user data.
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            if (!isset($user_data_res['obj'])) return false;
            $user_data = $user_data_res['obj'];
            $user_ptr = &$user_data;
            if (isset($user_data['client']) && is_array($user_data['client'])) $user_ptr = &$user_data['client'];
            if ($Method_extend == "change") {
                $user_ptr['totalGB'] = $new_limit;
                $user_ptr['expiryTime'] = $time_day;
            } else {
                $user_ptr['totalGB'] = (isset($user_ptr['totalGB']) ? $user_ptr['totalGB'] : (isset($user_ptr['total']) ? $user_ptr['total'] : 0)) + $new_limit;
                if ($user_ptr['expiryTime'] == 0 || $user_ptr['expiryTime'] < 0) {
                    $user_ptr['expiryTime'] = $time_day;
                } else {
                    $user_ptr['expiryTime'] = $user_ptr['expiryTime'] + ($time_day - time()*1000); // Rough approximation
                }
            }
            $user_ptr['enable'] = true;
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/update/' . urlencode($username);
            $update = request_MHSanaei($url, 'POST', $panel, $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => true);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'extra_volume':
            list($username_account, $code_panel, $limit_volume_new) = $args;
            $Get_Data_Panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
            $name_panel = $Get_Data_Panel['name_panel'];
            $user_data_res = get_client_MHSanaei($username_account, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => false, 'msg' => 'User not found');
            $user_data = $user_data_res['obj'];
            $user_ptr = &$user_data;
            if (isset($user_data['client']) && is_array($user_data['client'])) $user_ptr = &$user_data['client'];
            $current_total = isset($user_ptr['totalGB']) ? $user_ptr['totalGB'] : (isset($user_ptr['total']) ? $user_ptr['total'] : 0);
            $user_ptr['totalGB'] = $current_total + ($limit_volume_new * pow(1024, 3));
            $user_ptr['enable'] = true;
            $url = rtrim($Get_Data_Panel['url_panel'], '/') . '/panel/api/clients/update/' . urlencode($username_account);
            $update = request_MHSanaei($url, 'POST', $Get_Data_Panel, $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => true);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'extra_time':
            list($username_account, $code_panel, $limit_time_new) = $args;
            $Get_Data_Panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
            $name_panel = $Get_Data_Panel['name_panel'];
            $user_data_res = get_client_MHSanaei($username_account, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => false, 'msg' => 'User not found');
            $user_data = $user_data_res['obj'];
            $user_ptr = &$user_data;
            if (isset($user_data['client']) && is_array($user_data['client'])) $user_ptr = &$user_data['client'];
            $addedTime = $limit_time_new * 86400 * 1000;
            if ($user_ptr['expiryTime'] == 0 || $user_ptr['expiryTime'] < 0) {
                $user_ptr['expiryTime'] = (time() * 1000) + $addedTime;
            } else {
                $user_ptr['expiryTime'] += $addedTime;
            }
            $user_ptr['enable'] = true;
            $url = rtrim($Get_Data_Panel['url_panel'], '/') . '/panel/api/clients/update/' . urlencode($username_account);
            $update = request_MHSanaei($url, 'POST', $Get_Data_Panel, $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => true);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');

        default:
            return array('status' => false, 'msg' => 'Method not supported');
    }
}

// === New Features for MHSanaei 3.2 ===

function restartXray_MHSanaei($namepanel) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/server/restartXrayService';
    return request_MHSanaei($url, 'POST', $panel);
}

function delDepleted_MHSanaei($namepanel) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/delDepleted';
    return request_MHSanaei($url, 'POST', $panel);
}

function resetAllTraffics_MHSanaei($namepanel) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/resetAllTraffics';
    return request_MHSanaei($url, 'POST', $panel);
}

function check_connection_MHSanaei($code_panel) {
    $panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
    $res = login_MHSanaei($code_panel, false);
    if (isset($res['success']) && $res['success']) {
        return $res;
    }

    $status_url = mhsanaei_base_url($panel) . '/panel/api/server/status';
    $bearer_response = mhsanaei_bearer_request($status_url, 'GET', $panel);
    $bearer_decoded = mhsanaei_decode_panel_response($bearer_response);
    if (isset($bearer_decoded['success']) && $bearer_decoded['success']) {
        return array('success' => true, 'msg' => 'Connected with API token');
    }

    $errorMsg = isset($res['msg']) ? $res['msg'] : json_encode($res);
    return array('success' => false, 'msg' => 'Login failed: ' . $errorMsg);
}
