<?php
require_once 'config.php';
require_once 'request.php';

function request_MHSanaei($url, $method, $token, $data = null) {
    $curl = curl_init();
    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT_MS => 10000,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ),
    );
    
    if ($data !== null) {
        $options[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data) : $data;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return array('success' => false, 'msg' => $error);
    }
    
    return json_decode($response, true);
}

function get_client_MHSanaei($email, $namepanel) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = $panel['url_panel'] . '/panel/api/clients/get/' . urlencode($email);
    return request_MHSanaei($url, 'GET', $panel['password_panel']);
}

function addClient_MHSanaei($namepanel, $usernameac, $Expire, $Total, $subid, $inboundid, $name_product, $note = "") {
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

    $data = array(
        "client" => array(
            "email" => $usernameac,
            "enable" => true,
            "totalGB" => $Total,
            "expiryTime" => $timeservice,
            "subId" => $subid,
            "comment" => $note,
            "reset" => 0
        ),
        "inboundIds" => array(intval($inboundid))
    );
    
    $url = $panel['url_panel'] . '/panel/api/clients/add';
    return request_MHSanaei($url, 'POST', $panel['password_panel'], $data);
}

function ResetUserDataUsage_MHSanaei($email, $namepanel) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = $panel['url_panel'] . '/panel/api/clients/resetTraffic/' . urlencode($email);
    return request_MHSanaei($url, 'POST', $panel['password_panel']);
}

function removeClient_MHSanaei($namepanel, $email) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = $panel['url_panel'] . '/panel/api/clients/del/' . urlencode($email);
    return request_MHSanaei($url, 'POST', $panel['password_panel']);
}

function get_online_MHSanaei($namepanel, $email) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = $panel['url_panel'] . '/panel/api/clients/onlines';
    $response = request_MHSanaei($url, 'POST', $panel['password_panel']);
    
    if (isset($response['success']) && $response['success']) {
        if (in_array($email, $response['obj'])) {
            return "online";
        }
    }
    return "offline";
}

function get_subLinks_MHSanaei($namepanel, $subid) {
    $panel = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = $panel['url_panel'] . '/panel/api/clients/subLinks/' . urlencode($subid);
    return request_MHSanaei($url, 'GET', $panel['password_panel']);
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
            
            $data_Output = addClient_MHSanaei($name_panel, $usernameC, $expire, $data_limit, $subId, $inbounds, $Get_Data_Product['name_product'], $note);
            if (isset($data_Output['msg']) && !$data_Output['success']) {
                return array('status' => 'Unsuccessful', 'msg' => $data_Output['msg']);
            } elseif (isset($data_Output['success']) && !$data_Output['success']) {
                return array('status' => 'Unsuccessful', 'msg' => 'Panel Error');
            } else {
                $Output = ['status' => 'successful', 'username' => $usernameC];
                $subLinksRes = get_subLinks_MHSanaei($name_panel, $subId);
                $Output['configs'] = (isset($subLinksRes['success']) && $subLinksRes['success']) ? $subLinksRes['obj'] : [];
                $Output['subscription_url'] = $Get_Data_Panel['url_panel'] . '/sub/' . $subId;
                
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
                $expire = $user_data['expiryTime'] / 1000;
                $status_user = $user_data['enable'] ? "active" : "disabled";
                
                if ((intval($user_data['total'])) != 0) {
                    if ((intval($user_data['total']) - ($user_data['up'] + $user_data['down'])) <= 0) $status_user = "limited";
                }
                if (intval($user_data['expiryTime']) != 0) {
                    if ($expire - time() <= 0) $status_user = "expired";
                }
                
                $subLinksRes = get_subLinks_MHSanaei($name_panel, $user_data['subId']);
                $links_user = (isset($subLinksRes['success']) && $subLinksRes['success']) ? $subLinksRes['obj'] : [];
                
                $linksub = $Get_Data_Panel['url_panel'] . "/sub/" . $user_data['subId'];
                $inoice = (isset($Get_Data_Panel['subvip']) && $Get_Data_Panel['subvip'] == "onsubvip") ? select("invoice", "*", "username", $username, "select") : false;
                if ($inoice != false) $linksub = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                
                $is_online = get_online_MHSanaei($name_panel, $username);
                return array(
                    'status' => $status_user,
                    'username' => $user_data['email'],
                    'data_limit' => $user_data['total'],
                    'expire' => $expire,
                    'online_at' => $is_online,
                    'used_traffic' => $user_data['up'] + $user_data['down'],
                    'links' => $links_user,
                    'subscription_url' => $linksub,
                    'sub_updated_at' => null,
                    'sub_last_user_agent' => null,
                );
            }
            
        case 'Revoke_sub':
            list($name_panel, $username) = $args;
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => 'Unsuccessful', 'msg' => 'Unsuccessful');
            $user_data = $user_data_res['obj'];
            $user_data['subId'] = bin2hex(random_bytes(8)); // Update subId to revoke
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $url = $panel['url_panel'] . '/panel/api/clients/update/' . urlencode($username);
            $update = request_MHSanaei($url, 'POST', $panel['password_panel'], $user_data);
            if (isset($update['success']) && $update['success']) {
                return array(
                    'status' => 'successful',
                    'configs' => [ $panel['url_panel'] . "/sub/" . $user_data['subId'] ],
                    'subscription_url' => $panel['url_panel'] . "/sub/" . $user_data['subId']
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
            if (isset($config['settings'])) {
                $sets = json_decode($config['settings'], true)['clients'][0];
                if (isset($sets['enable'])) $user_data['enable'] = $sets['enable'];
                if (isset($sets['totalGB'])) $user_data['total'] = $sets['totalGB'];
                if (isset($sets['expiryTime'])) $user_data['expiryTime'] = $sets['expiryTime'];
            }
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $url = $panel['url_panel'] . '/panel/api/clients/update/' . urlencode($username);
            $update = request_MHSanaei($url, 'POST', $panel['password_panel'], $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => true, 'data' => $update);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'Change_status':
            list($username, $name_panel) = $args;
            $user_data_res = get_client_MHSanaei($username, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => 'Unsuccessful', 'msg' => 'User not found');
            $user_data = $user_data_res['obj'];
            $user_data['enable'] = !$user_data['enable']; // Toggle status
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $url = $panel['url_panel'] . '/panel/api/clients/update/' . urlencode($username);
            $update = request_MHSanaei($url, 'POST', $panel['password_panel'], $user_data);
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
            if ($Method_extend == "change") {
                $user_data['total'] = $new_limit;
                $user_data['expiryTime'] = $time_day;
            } else {
                $user_data['total'] = $user_data['total'] + $new_limit;
                if ($user_data['expiryTime'] == 0 || $user_data['expiryTime'] < 0) {
                    $user_data['expiryTime'] = $time_day;
                } else {
                    $user_data['expiryTime'] = $user_data['expiryTime'] + ($time_day - time()*1000); // Rough approximation
                }
            }
            $user_data['enable'] = true;
            $panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
            $url = $panel['url_panel'] . '/panel/api/clients/update/' . urlencode($username);
            $update = request_MHSanaei($url, 'POST', $panel['password_panel'], $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => true);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'extra_volume':
            list($username_account, $code_panel, $limit_volume_new) = $args;
            $Get_Data_Panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
            $name_panel = $Get_Data_Panel['name_panel'];
            $user_data_res = get_client_MHSanaei($username_account, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => false, 'msg' => 'User not found');
            $user_data = $user_data_res['obj'];
            $user_data['total'] += ($limit_volume_new * pow(1024, 3));
            $user_data['enable'] = true;
            $url = $Get_Data_Panel['url_panel'] . '/panel/api/clients/update/' . urlencode($username_account);
            $update = request_MHSanaei($url, 'POST', $Get_Data_Panel['password_panel'], $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => true);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');
            
        case 'extra_time':
            list($username_account, $code_panel, $limit_time_new) = $args;
            $Get_Data_Panel = select("marzban_panel", "*", "code_panel", $code_panel, "select");
            $name_panel = $Get_Data_Panel['name_panel'];
            $user_data_res = get_client_MHSanaei($username_account, $name_panel);
            if (!isset($user_data_res['obj'])) return array('status' => false, 'msg' => 'User not found');
            $user_data = $user_data_res['obj'];
            $addedTime = $limit_time_new * 86400 * 1000;
            if ($user_data['expiryTime'] == 0 || $user_data['expiryTime'] < 0) {
                $user_data['expiryTime'] = (time() * 1000) + $addedTime;
            } else {
                $user_data['expiryTime'] += $addedTime;
            }
            $user_data['enable'] = true;
            $url = $Get_Data_Panel['url_panel'] . '/panel/api/clients/update/' . urlencode($username_account);
            $update = request_MHSanaei($url, 'POST', $Get_Data_Panel['password_panel'], $user_data);
            if (isset($update['success']) && $update['success']) return array('status' => true);
            return array('status' => false, 'msg' => $update['msg'] ?? 'Error');

        default:
            return array('status' => false, 'msg' => 'Method not supported');
    }
}
