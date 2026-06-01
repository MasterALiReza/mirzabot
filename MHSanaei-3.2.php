<?php
require_once 'config.php';
require_once 'request.php';


function request_MHSanaei($url, $method, $panel, $data = null) {
    login($panel['code_panel']);
    
    $base_url = rtrim($panel['url_panel'], '/');
    $csrf_token = '';
    
    // MHSanaei 3.2+ API requires CSRF token for unsafe methods
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
    if (curl_getinfo($curl_csrf, CURLINFO_HTTP_CODE) == 200) {
        $csrf_dec = json_decode($csrf_resp, true);
        if (isset($csrf_dec['success']) && $csrf_dec['success'] && isset($csrf_dec['obj'])) {
            $csrf_token = $csrf_dec['obj'];
        }
    }
    curl_close($curl_csrf);

    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    );
    if ($csrf_token) {
        $headers[] = 'X-CSRF-Token: ' . $csrf_token;
    }
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setCookie('cookie.txt');
    
    if ($method == 'POST') {
        $postData = is_array($data) ? json_encode($data) : $data;
        $response = $req->post($postData);
    } else {
        $response = $req->get();
    }
    
    if (is_file('cookie.txt')) {
        @unlink('cookie.txt');
    }
    
    if (isset($response['body'])) {
        $decoded = json_decode($response['body'], true);
        if ($decoded !== null && !(isset($decoded['success']) && $decoded['success'] === false && strpos($response['body'], 'Unauthorized') !== false)) {
            return $decoded;
        }
    }
    
    // Fallback: If cookie failed (maybe user actually put an API token in password field)
    $authHeader = 'Authorization: Bearer ' . $panel['password_panel'];
    $req = new CurlRequest($url);
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        $authHeader
    );
    $req->setHeaders($headers);
    if ($method == 'POST') {
        $postData = is_array($data) ? json_encode($data) : $data;
        $response = $req->post($postData);
    } else {
        $response = $req->get();
    }

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
    // Use same login as x-ui_single
    $res = login($code_panel, false);
    if (isset($res['success']) && $res['success']) {
        return $res;
    }
    return $res ?? array('success' => false, 'msg' => 'Login failed');
}
