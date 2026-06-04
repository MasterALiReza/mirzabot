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

function get_client_traffic_MHSanaei($email, $namepanel) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/traffic/' . urlencode($email);
    return request_MHSanaei($url, 'GET', $panel);
}

function mhsanaei_to_int($value, $default = 0)
{
    if ($value === null || $value === '') {
        return $default;
    }
    return is_numeric($value) ? (int)round((float)$value) : $default;
}

function mhsanaei_to_bool($value, $default = true)
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_numeric($value)) {
        return ((int)$value) == 1;
    }
    $value = strtolower(trim((string)$value));
    if (in_array($value, array('true', 'on', 'yes', 'enable', 'enabled'), true)) {
        return true;
    }
    if (in_array($value, array('false', 'off', 'no', 'disable', 'disabled'), true)) {
        return false;
    }
    return $default;
}

function mhsanaei_expiry_ms($panel, $Expire, $name_product)
{
    if ($Expire == 0) {
        return 0;
    }
    if (($name_product == "usertest" && $panel['on_hold_test'] == "1") || ($name_product != "usertest" && $panel['conecton'] == "onconecton")) {
        $timelast = $Expire - time();
        return -intval(($timelast / 86400) * 86400000);
    }
    return mhsanaei_to_int($Expire) * 1000;
}

function mhsanaei_collect_inbound_ids($value)
{
    $ids = array();
    $walk = function ($item) use (&$walk, &$ids) {
        if (is_array($item)) {
            if (isset($item['id']) && is_numeric($item['id'])) {
                $ids[] = (int)$item['id'];
            } elseif (isset($item['inboundId']) && is_numeric($item['inboundId'])) {
                $ids[] = (int)$item['inboundId'];
            } else {
                foreach ($item as $child) {
                    $walk($child);
                }
            }
        } elseif (is_numeric($item)) {
            $ids[] = (int)$item;
        } elseif (is_string($item)) {
            foreach (preg_split('/[,\s]+/', $item) as $part) {
                if (is_numeric($part)) {
                    $ids[] = (int)$part;
                }
            }
        }
    };
    $walk($value);
    return array_values(array_unique(array_filter($ids, function ($id) {
        return $id > 0;
    })));
}

function mhsanaei_resolve_inbound_ids($panel, $inboundid)
{
    $raw = is_string($inboundid) ? trim($inboundid) : $inboundid;
    if ($raw === '' || $raw === null) {
        $raw = $panel['inboundid'] ?? '';
    }

    if ($raw === "all" || $raw === "0" || $raw === 0) {
        $url_inbounds = rtrim($panel['url_panel'], '/') . '/panel/api/inbounds/options';
        $res_inbounds = request_MHSanaei($url_inbounds, 'GET', $panel);
        $ids = array();
        if (isset($res_inbounds['success']) && $res_inbounds['success'] && is_array($res_inbounds['obj'])) {
            foreach ($res_inbounds['obj'] as $inb) {
                if (isset($inb['id'])) {
                    $ids[] = (int)$inb['id'];
                }
            }
        }
        return $ids;
    }

    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return mhsanaei_collect_inbound_ids($decoded);
        }
    }
    return mhsanaei_collect_inbound_ids($raw);
}

function mhsanaei_extract_client_context($response)
{
    if (!isset($response['success']) || !$response['success'] || !isset($response['obj']) || !is_array($response['obj'])) {
        return array('success' => false, 'msg' => $response['msg'] ?? 'User not found');
    }
    $obj = $response['obj'];
    $client = isset($obj['client']) && is_array($obj['client']) ? $obj['client'] : $obj;
    $inboundIds = isset($obj['inboundIds']) ? mhsanaei_collect_inbound_ids($obj['inboundIds']) : (isset($client['inboundIds']) ? mhsanaei_collect_inbound_ids($client['inboundIds']) : array());
    return array('success' => true, 'client' => $client, 'inboundIds' => $inboundIds);
}

function mhsanaei_client_payload($client, $patch = array())
{
    $client = is_array($client) ? $client : array();
    $merged = array_replace($client, $patch);
    $payload = array(
        'email' => trim((string)($merged['email'] ?? '')),
        'subId' => (string)($merged['subId'] ?? ''),
        'id' => (string)($merged['id'] ?? ($merged['uuid'] ?? '')),
        'password' => (string)($merged['password'] ?? ''),
        'auth' => (string)($merged['auth'] ?? ''),
        'flow' => (string)($merged['flow'] ?? ''),
        'security' => (string)($merged['security'] ?? 'auto'),
        'totalGB' => mhsanaei_to_int($merged['totalGB'] ?? ($merged['total'] ?? 0)),
        'expiryTime' => mhsanaei_to_int($merged['expiryTime'] ?? 0),
        'reset' => mhsanaei_to_int($merged['reset'] ?? 0),
        'limitIp' => mhsanaei_to_int($merged['limitIp'] ?? 0),
        'tgId' => mhsanaei_to_int($merged['tgId'] ?? 0),
        'group' => (string)($merged['group'] ?? ''),
        'comment' => (string)($merged['comment'] ?? ''),
        'enable' => mhsanaei_to_bool($merged['enable'] ?? true, true)
    );
    if ($payload['subId'] === '') {
        $payload['subId'] = bin2hex(random_bytes(8));
    }
    if ($payload['security'] === '') {
        $payload['security'] = 'auto';
    }
    if (isset($merged['reverse']) && is_array($merged['reverse'])) {
        $payload['reverse'] = $merged['reverse'];
    }
    return $payload;
}

function mhsanaei_patch_from_config($config)
{
    $patch = array();
    if (!is_array($config)) {
        return $patch;
    }
    if (isset($config['settings'])) {
        $settings = is_array($config['settings']) ? $config['settings'] : json_decode($config['settings'], true);
        if (is_array($settings) && isset($settings['clients'][0]) && is_array($settings['clients'][0])) {
            $patch = array_replace($patch, $settings['clients'][0]);
        }
    }
    $map = array(
        'data_limit' => 'totalGB',
        'total' => 'totalGB',
        'totalGB' => 'totalGB',
        'expire' => 'expiryTime',
        'expiryTime' => 'expiryTime',
        'enable' => 'enable',
        'subId' => 'subId',
        'limitIp' => 'limitIp',
        'tgId' => 'tgId',
        'group' => 'group',
        'comment' => 'comment',
        'reset' => 'reset',
    );
    foreach ($map as $from => $to) {
        if (array_key_exists($from, $config)) {
            $patch[$to] = ($from === 'expire') ? mhsanaei_to_int($config[$from]) * 1000 : $config[$from];
        }
    }
    return $patch;
}

function mhsanaei_update_client_payload($panel, $email, $client, $patch = array())
{
    $payload = mhsanaei_client_payload($client, $patch);
    $url = rtrim($panel['url_panel'], '/') . '/panel/api/clients/update/' . urlencode($email);
    return request_MHSanaei($url, 'POST', $panel, $payload);
}

function addClient_MHSanaei($namepanel, $usernameac, $Expire, $Total, $subid, $inboundid, $name_product, $note = "", $tgId = "", $group = "") {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (empty($group) && !empty($panel['sanaei_group'])) {
        $group = $panel['sanaei_group'];
    }
    $timeservice = mhsanaei_expiry_ms($panel, $Expire, $name_product);
    $limitIp = isset($panel['limit_panel']) && is_numeric($panel['limit_panel']) ? intval($panel['limit_panel']) : 0;
    $inbounds_array = mhsanaei_resolve_inbound_ids($panel, $inboundid);
    if (empty($inbounds_array)) {
        return array('success' => false, 'msg' => 'No inbound selected');
    }

    $data = array(
        "client" => mhsanaei_client_payload(array(
            "email" => $usernameac,
            "enable" => true,
            "totalGB" => $Total,
            "expiryTime" => $timeservice,
            "subId" => $subid,
            "comment" => $note,
            "reset" => 0,
            "tgId" => $tgId,
            "limitIp" => $limitIp,
            "group" => $group,
            "security" => "auto"
        )),
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

function mhsanaei_subscription_url($panel, $subid) {
    $raw_base = (!empty($panel['linksubx']) && $panel['linksubx'] != "none") ? $panel['linksubx'] : ($panel['url_panel'] ?? '');
    $base = rtrim(trim((string)$raw_base), "/ \t\n\r\0\x0B");
    if ($base === '') {
        return '/sub/' . rawurlencode($subid);
    }
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $base)) {
        $base = 'https://' . ltrim($base, '/');
    }

    $parts = parse_url($base);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $base . '/sub/' . rawurlencode($subid);
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    $path = isset($parts['path']) ? trim($parts['path'], '/') : '';
    $segments = ($path === '') ? array() : explode('/', $path);
    $sub_index = array_search('sub', $segments, true);

    if ($sub_index !== false) {
        $segments = array_slice($segments, 0, $sub_index + 1);
    } else {
        $segments[] = 'sub';
    }

    return $origin . '/' . implode('/', $segments) . '/' . rawurlencode($subid);
}

function MHSanaei_router($methodName, $args) {
    global $domainhosts, $pdo, $textbotlang;

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
            if (!is_array($Get_Data_Product)) {
                return array('status' => 'Unsuccessful', 'msg' => 'Product not found');
            }
            $expire = $Data_Config['expire'];
            $data_limit = $Data_Config['data_limit'];
            $note = "{$Data_Config['from_id']} | {$Data_Config['username']} | {$Data_Config['type']}";
            
            $subId = bin2hex(random_bytes(8));
            $inbounds = (!empty($Get_Data_Product['inbounds'])) ? $Get_Data_Product['inbounds'] : $Get_Data_Panel['inboundid'];
            $group = (!empty($Get_Data_Product['category'])) ? $Get_Data_Product['category'] : "";
            
            $data_Output = addClient_MHSanaei($name_panel, $usernameC, $expire, $data_limit, $subId, $inbounds, $Get_Data_Product['name_product'], $note, isset($Data_Config['from_id']) ? $Data_Config['from_id'] : "", $group);
            if (isset($data_Output['success']) && !$data_Output['success']) {
                return array('status' => 'Unsuccessful', 'msg' => $data_Output['msg']);
            } elseif (!isset($data_Output['success'])) {
                return array('status' => 'Unsuccessful', 'msg' => 'Panel Error');
            } else {
                $Output = ['status' => 'successful', 'username' => $usernameC];
                $subLinksRes = get_subLinks_MHSanaei($name_panel, $subId);
                $Output['configs'] = (isset($subLinksRes['success']) && $subLinksRes['success']) ? $subLinksRes['obj'] : [];
                $Output['subscription_url'] = mhsanaei_subscription_url($Get_Data_Panel, $subId);
                
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
            $context = mhsanaei_extract_client_context($user_data_res);
            if (!$context['success']) {
                return array('status' => 'Unsuccessful', 'msg' => $context['msg']);
            }

            $user_ptr = $context['client'];
            $traffic_res = get_client_traffic_MHSanaei($username, $name_panel);
            $traffic = (isset($traffic_res['success']) && $traffic_res['success'] && isset($traffic_res['obj']) && is_array($traffic_res['obj'])) ? $traffic_res['obj'] : array();
            $up = mhsanaei_to_int($traffic['up'] ?? ($user_ptr['up'] ?? 0));
            $down = mhsanaei_to_int($traffic['down'] ?? ($user_ptr['down'] ?? 0));
            $used = $up + $down;
            $current_total = mhsanaei_to_int($user_ptr['totalGB'] ?? ($traffic['total'] ?? ($user_ptr['total'] ?? 0)));
            $expiry_ms = mhsanaei_to_int($user_ptr['expiryTime'] ?? ($traffic['expiryTime'] ?? 0));
            $expire = $expiry_ms > 0 ? intval($expiry_ms / 1000) : $expiry_ms;
            $status_user = mhsanaei_to_bool($user_ptr['enable'] ?? ($traffic['enable'] ?? true), true) ? "active" : "disabled";

            if ($current_total != 0 && ($current_total - $used) <= 0) {
                $status_user = "limited";
            }
            if ($expiry_ms > 0 && $expire - time() <= 0) {
                $status_user = "expired";
            }

            $subid = (string)($user_ptr['subId'] ?? '');
            $subLinksRes = $subid !== '' ? get_subLinks_MHSanaei($name_panel, $subid) : array('success' => false);
            $links_user = (isset($subLinksRes['success']) && $subLinksRes['success']) ? $subLinksRes['obj'] : [];
            $subscription_url = mhsanaei_subscription_url($Get_Data_Panel, $subid);
            $inoice = (isset($Get_Data_Panel['subvip']) && $Get_Data_Panel['subvip'] == "onsubvip") ? select("invoice", "*", "username", $username, "select") : false;
            if ($inoice != false) $subscription_url = "https://$domainhosts/sub/" . $inoice['id_invoice'];

            $is_online = get_online_MHSanaei($name_panel, $username);
            return array(
                'status' => $status_user,
                'username' => $user_ptr['email'],
                'data_limit' => $current_total,
                'expire' => $expire,
                'online_at' => $is_online,
                'used_traffic' => $used,
                'links' => $links_user,
                'subscription_url' => $subscription_url,
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
            );
            
        case 'Revoke_sub':
            list($name_panel, $username) = $args;
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            $context = mhsanaei_extract_client_context($user_data_res);
            if (!$context['success']) return array('status' => 'Unsuccessful', 'msg' => $context['msg']);
            $newSubId = bin2hex(random_bytes(8));
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $update = mhsanaei_update_client_payload($panel, $username, $context['client'], array('subId' => $newSubId));
            if (isset($update['success']) && $update['success']) {
                $subscription_url = mhsanaei_subscription_url($panel, $newSubId);
                return array(
                    'status' => 'successful',
                    'configs' => [ $subscription_url ],
                    'subscription_url' => $subscription_url
                );
            }
            return array('status' => 'Unsuccessful', 'msg' => $update['msg'] ?? 'Unsuccessful');
            
        case 'RemoveUser':
            list($name_panel, $username) = $args;
            $res = removeClient_MHSanaei($name_panel, $username);
            if (!$res['success']) return array('status' => 'Unsuccessful', 'msg' => $res['msg'] ?? 'Error');
            return array('status' => 'successful', 'username' => $username);
            
        case 'Modifyuser':
            list($username, $name_panel, $config) = $args;
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            $context = mhsanaei_extract_client_context($user_data_res);
            if (!$context['success']) return array('status' => false, 'msg' => $context['msg']);
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $update = mhsanaei_update_client_payload($panel, $username, $context['client'], mhsanaei_patch_from_config($config));
            if (isset($update['success']) && $update['success']) return array('status' => true, 'data' => $update);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'Change_status':
            list($username, $name_panel) = $args;
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            $context = mhsanaei_extract_client_context($user_data_res);
            if (!$context['success']) return array('status' => 'Unsuccessful', 'msg' => $context['msg']);
            $newEnable = !mhsanaei_to_bool($context['client']['enable'] ?? true, true);
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $update = mhsanaei_update_client_payload($panel, $username, $context['client'], array('enable' => $newEnable));
            if (isset($update['success']) && $update['success']) return array('status' => 'successful', 'msg' => null);
            return array('status' => 'Unsuccessful', 'msg' => $update['msg'] ?? 'Error');
            
        case 'ResetUserDataUsage':
            list($username, $name_panel) = $args;
            $res = ResetUserDataUsage_MHSanaei($username, $name_panel);
            if (isset($res['success']) && $res['success']) return array('status' => true, 'msg' => 'successful');
            return array('status' => false, 'msg' => 'Error');
            
        case 'extend':
            list($Method_extend, $new_limit, $time_day, $username, $code_product, $name_panel) = $args;
            $panel = select("marzban_panel", "*", "code_panel", $name_panel, "select");
            if ($panel == false) return array('status' => false, 'msg' => 'data not found');
            $data_user = MHSanaei_router('DataUser', array($panel['name_panel'], $username));
            if ($data_user['status'] == "Unsuccessful") return array('status' => false, 'msg' => $data_user['msg']);

            $data_limit_old = mhsanaei_to_int($data_user['data_limit'] ?? 0);
            $time_old = mhsanaei_to_int($data_user['expire'] ?? 0);
            $time_old = time() - $time_old > 0 ? time() : $time_old;
            $data_limit_new = $new_limit == 0 ? 0 : mhsanaei_to_int($new_limit * pow(1024, 3));
            $data_limit_new_add = $new_limit == 0 ? 0 : $data_limit_old + mhsanaei_to_int($new_limit * pow(1024, 3));
            $time_new = $time_day == 0 ? 0 : time() + mhsanaei_to_int($time_day) * 86400;
            $time_old = $time_old == 0 ? time() : $time_old;
            $time_new_add = $time_day == 0 ? 0 : $time_old + mhsanaei_to_int($time_day) * 86400;

            if ($Method_extend == ($textbotlang['keyboard']['resetVolumeTime'] ?? '')) {
                $reset = ResetUserDataUsage_MHSanaei($username, $panel['name_panel']);
                if (!isset($reset['success']) || !$reset['success']) {
                    return array('status' => false, 'msg' => 'error reset : ' . ($reset['msg'] ?? 'Error'));
                }
            } elseif ($Method_extend == ($textbotlang['keyboard']['addTimeVolumeNextMonth'] ?? '')) {
                $data_limit_new = $data_limit_new_add;
                $time_new = $time_new_add;
            } elseif ($Method_extend == ($textbotlang['keyboard']['resetTimeAddVolume'] ?? '')) {
                $data_limit_new = $data_limit_new_add;
            } elseif ($Method_extend == ($textbotlang['keyboard']['resetVolumeAddTime'] ?? '')) {
                $reset = ResetUserDataUsage_MHSanaei($username, $panel['name_panel']);
                if (!isset($reset['success']) || !$reset['success']) {
                    return array('status' => false, 'msg' => 'error reset : ' . ($reset['msg'] ?? 'Error'));
                }
                $time_new = $time_new_add;
            } elseif ($Method_extend == ($textbotlang['keyboard']['addTimeConvertVolume'] ?? '')) {
                $reset = ResetUserDataUsage_MHSanaei($username, $panel['name_panel']);
                if (!isset($reset['success']) || !$reset['success']) {
                    return array('status' => false, 'msg' => 'error reset : ' . ($reset['msg'] ?? 'Error'));
                }
                $time_new = $time_new_add;
                $data_limit_last = $data_limit_old - mhsanaei_to_int($data_user['used_traffic'] ?? 0);
                $data_limit_last = $data_limit_last < 0 ? 0 : $data_limit_last;
                $data_limit_new = $data_limit_new + $data_limit_last;
            }

            $user_data_res = get_client_MHSanaei($username, $panel['name_panel']);
            $context = mhsanaei_extract_client_context($user_data_res);
            if (!$context['success']) return array('status' => false, 'msg' => $context['msg']);
            $update = mhsanaei_update_client_payload($panel, $username, $context['client'], array(
                'totalGB' => $data_limit_new,
                'expiryTime' => $time_new * 1000,
                'enable' => true
            ));
            if (isset($update['success']) && $update['success']) return array('status' => true);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'extra_volume':
            list($username_account, $code_panel, $limit_volume_new) = $args;
            $Get_Data_Panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
            $name_panel = $Get_Data_Panel['name_panel'];
            $user_data_res = get_client_MHSanaei($username_account, $name_panel);
            $context = mhsanaei_extract_client_context($user_data_res);
            if (!$context['success']) return array('status' => false, 'msg' => $context['msg']);
            $current_total = mhsanaei_to_int($context['client']['totalGB'] ?? ($context['client']['total'] ?? 0));
            $new_total = $limit_volume_new == 0 ? 0 : $current_total + mhsanaei_to_int($limit_volume_new * pow(1024, 3));
            $update = mhsanaei_update_client_payload($Get_Data_Panel, $username_account, $context['client'], array('totalGB' => $new_total, 'enable' => true));
            if (isset($update['success']) && $update['success']) return array('status' => true);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'extra_time':
            list($username_account, $code_panel, $limit_time_new) = $args;
            $Get_Data_Panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
            $name_panel = $Get_Data_Panel['name_panel'];
            $user_data_res = get_client_MHSanaei($username_account, $name_panel);
            $context = mhsanaei_extract_client_context($user_data_res);
            if (!$context['success']) return array('status' => false, 'msg' => $context['msg']);
            $expiry = mhsanaei_to_int($context['client']['expiryTime'] ?? 0);
            $base = $expiry > time() * 1000 ? $expiry : time() * 1000;
            $new_expiry = $limit_time_new == 0 ? 0 : $base + mhsanaei_to_int($limit_time_new) * 86400 * 1000;
            $update = mhsanaei_update_client_payload($Get_Data_Panel, $username_account, $context['client'], array('expiryTime' => $new_expiry, 'enable' => true));
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
