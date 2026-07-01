<?php
include('config.php');
ini_set('error_log', 'error_log');


function get_userwg($username, $namepanel)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = $marzban_list_get['url_panel'] . '/api/getWireguardConfigurationInfo?configurationName=' . $marzban_list_get['inboundid'];
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $api_res = $req->get();
    
    if (!empty($api_res['error'])) {
        return ['status' => false, 'msg' => 'API Connection Error: ' . $api_res['error']];
    }
    
    if (empty($api_res['status']) || $api_res['status'] != 200) {
        return ['status' => false, 'msg' => 'API Error: HTTP ' . ($api_res['status'] ?? 'unknown')];
    }
    
    $response_str = $api_res['body'];
    
    $response = json_decode($response_str, true);
    if (!is_array($response)) {
        return ['status' => false, 'msg' => 'Invalid JSON from WGDashboard'];
    }
    
    if (empty($response['status'])) {
        if (isset($response['message'])) {
            $response['msg'] = $response['message'];
        }
        return $response;
    }
    $configurationPeers = $response['data']['configurationPeers'] ?? [];
    $configurationRestrictedPeers = $response['data']['configurationRestrictedPeers'] ?? [];
    $output = [];
    foreach ($configurationPeers as $userinfo) {
        if ($userinfo['name'] == $username) {
            $output = $userinfo;
            break;
        }
    }
    if (count($output) != 0) {
        $output['id'] = $output['id'] ?? $output['publicKey'] ?? $output['name'] ?? null;
        return $output;
    }
    foreach ($configurationRestrictedPeers as $userinfo) {
        if ($userinfo['name'] == $username) {
            $output = $userinfo;
            $output['configuration']['Status'] = false;
            break;
        }
    }
    if (count($output) != 0) {
        $output['id'] = $output['id'] ?? $output['publicKey'] ?? $output['name'] ?? null;
    }
    return $output;
}

function ipslast($namepanel)
{

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = $marzban_list_get['url_panel'] . '/api/getAvailableIPs/' . $marzban_list_get['inboundid'];
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    return $response;
}
function downloadconfig($namepanel, $publickey)
{

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $url = $marzban_list_get['url_panel'] . "/api/downloadPeer/{$marzban_list_get['inboundid']}?id=" . urlencode($publickey);
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    return $response;
}
function addpear($namepanel, $usernameac)
{

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $pubandprivate = publickey();
    if ($pubandprivate === false) {
        return array(
            'status' => false,
            'msg' => 'PHP sodium extension is missing. Cannot generate WireGuard keys.'
        );
    }
    $ipconfig = ipslast($namepanel);
    if (!empty($ipconfig['status']) && $ipconfig['status'] != 200) {
        return array(
            'status' => false,
            'msg' => 'error code : ' . $ipconfig['status']
        );
    }
    if (!empty($ipconfig['error'])) {
        return array(
            'status' => false,
            'msg' => $ipconfig['error']
        );
    }
    $ipconfig_body = json_decode($ipconfig['body'], true);
    if (!is_array($ipconfig_body)) {
        return array(
            'status' => false,
            'msg' => 'Invalid JSON from WGDashboard: ' . $ipconfig['body']
        );
    }
    if (!empty($ipconfig_body['status']) && $ipconfig_body['status'] == false) {
        return $ipconfig_body;
    }
    if (empty($ipconfig_body['data']) || !is_array($ipconfig_body['data'])) {
        return array(
            'status' => false,
            'msg' => 'No available IPs found in WGDashboard or invalid data format'
        );
    }
    
    // Find the first available IP from the available subnets safely
    $ipToAssign = null;
    $subnet_found = null;
    foreach ($ipconfig_body['data'] as $subnet => $ips) {
        $subnet_found = $subnet;
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                $clean_ip = explode('/', $ip)[0];
                if (filter_var($clean_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipToAssign = $clean_ip;
                    break 2;
                }
            }
        } elseif (is_string($ips) && !empty($ips)) {
            $clean_ip = explode('/', $ips)[0];
            if (filter_var($clean_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipToAssign = $clean_ip;
                break;
            }
        }
    }
    
    // --- SMART CAPACITY LIMIT LAYER ---
    $all_used_ips = array_merge(
        getUsedIPs($namepanel),
        getUsedIPsFromDb($namepanel)
    );
    
    if (!empty($subnet_found) && isSubnetFull($subnet_found, $all_used_ips)) {
        return array(
            'status' => false,
            'msg' => 'Server capacity is full'
        );
    }
    // -----------------------------------

    // Fallback: if WGDashboard returns empty/null available IPs, calculate the next IP
    if (empty($ipToAssign) && !empty($subnet_found)) {
        $ipToAssign = getNextAvailableIP($subnet_found, $all_used_ips);
    }
    
    // STRICT DEFENSIVE SHIELD: Validate IP address before sending request to WGDashboard panel API
    if (empty($ipToAssign) || !filter_var($ipToAssign, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return array(
            'status' => false,
            'msg' => 'Aborted: Invalid or empty IP address detected (' . var_export($ipToAssign, true) . ') to prevent WGDashboard infinite loop.'
        );
    }
    
    $config = array(
        'name' => $usernameac,
        'allowed_ips' => [$ipToAssign],
        'private_key' => $pubandprivate['private_key'],
        'public_key' => $pubandprivate['public_key'],
        'preshared_key' => $pubandprivate['preshared_key'],
    );
    $configpanel = json_encode($config);
    $url = $marzban_list_get['url_panel'] . '/api/addPeers/' . $marzban_list_get['inboundid'];
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($configpanel);
    $result_response = $response['body'];
    $response['body'] = $config;
    $response['body']['response'] = $result_response;
    return $response;
}
function setjob($namepanel, $type, $value, $publickey)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $data = json_encode(array(
        "Job" => array(
            "JobID" => generateUUID(),
            "Configuration" => $marzban_list_get['inboundid'],
            "Peer" => $publickey,
            "Field" => $type,
            "Operator" => "lgt",
            "Value" => strval($value),
            "CreationDate" => "",
            "ExpireDate" => null,
            "Action" => "restrict"
        )
    ));
    $url = $marzban_list_get['url_panel'] . '/api/savePeerScheduleJob';
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($data);
    return $response;

}
function updatepear($namepanel, array $config)
{
    // STRICT DEFENSIVE SHIELD: Validate IP address before sending request to WGDashboard panel API
    if (isset($config['allowed_ips']) && is_array($config['allowed_ips'])) {
        foreach ($config['allowed_ips'] as $ip) {
            $clean_ip = explode('/', $ip)[0];
            if (!filter_var($clean_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return array(
                    'status' => false,
                    'msg' => 'Aborted: Invalid IP address in update config (' . var_export($ip, true) . ') to prevent WGDashboard infinite loop.'
                );
            }
        }
    }

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $configpanel = json_encode($config);
    $url = $marzban_list_get['url_panel'] . '/api/updatePeerSettings/' . $marzban_list_get['inboundid'];
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel']
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($configpanel);
    return $response;
}
function deletejob($namepanel, array $config)
{

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $configpanel = json_encode($config);
    $url = $marzban_list_get['url_panel'] . '/api/deletePeerScheduleJob';
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($configpanel);
    return $response;
}
function ResetUserDataUsagewg($publickey, $namepanel)
{

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $config = array(
        "id" => $publickey,
        "type" => "total"
    );
    $configpanel = json_encode($config, true);
    $url = $marzban_list_get['url_panel'] . '/api/resetPeerData/' . $marzban_list_get['inboundid'];
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($configpanel);
    return $response;
}
function remove_userwg($location, $username)
{
    allowAccessPeers($location, $username);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $invoice = select("invoice", "user_info", "username", $username, "select");
    $user_info = $invoice ? json_decode($invoice['user_info'], true) : null;
    $data_user = is_array($user_info) ? ($user_info['public_key'] ?? $user_info['id'] ?? null) : null;
    if (empty($data_user)) {
        return false;
    }
    $url = $marzban_list_get['url_panel'] . '/api/deletePeers/' . $marzban_list_get['inboundid'];
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post(json_encode(array(
        "peers" => array(
            $data_user
        )
    )));
    return $response;
}
function allowAccessPeers($location, $username)
{

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $invoice = select("invoice", "user_info", "username", $username, "select");
    $user_info = $invoice ? json_decode($invoice['user_info'], true) : null;
    $data_user = is_array($user_info) ? ($user_info['public_key'] ?? $user_info['id'] ?? null) : null;
    if (empty($data_user)) {
        return false;
    }
    $url = $marzban_list_get['url_panel'] . '/api/allowAccessPeers/' . $marzban_list_get['inboundid'];
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel'],
        'Content-Type: application/json',
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post(json_encode(array(
        "peers" => array(
            $data_user
        )
    )));
    return $response;
}
function restrictPeers($location, $username)
{

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $invoice = select("invoice", "user_info", "username", $username, "select");
    $user_info = $invoice ? json_decode($invoice['user_info'], true) : null;
    $data_user = is_array($user_info) ? ($user_info['public_key'] ?? $user_info['id'] ?? null) : null;
    if (empty($data_user)) {
        return false;
    }
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $marzban_list_get['url_panel'] . '/api/restrictPeers/' . $marzban_list_get['inboundid'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_COOKIEFILE => 'cookiewg.txt',
        CURLOPT_POSTFIELDS => json_encode(array(
            "peers" => array(
                $data_user
            )
        )),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'wg-dashboard-apikey: ' . $marzban_list_get['password_panel']
        ),
    ));
    $response = json_decode(curl_exec($curl), true);
    return $response;
}

function getUsedIPs($namepanel)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (!$marzban_list_get) {
        return [];
    }
    $url = $marzban_list_get['url_panel'] . '/api/getWireguardConfigurationInfo?configurationName=' . $marzban_list_get['inboundid'];
    $headers = array(
        'Accept: application/json',
        'wg-dashboard-apikey: ' . $marzban_list_get['password_panel']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $api_res = $req->get();
    
    if (empty($api_res['status']) || $api_res['status'] != 200 || empty($api_res['body'])) {
        return [];
    }
    
    $response = json_decode($api_res['body'], true);
    if (!is_array($response) || empty($response['status'])) {
        return [];
    }
    
    $peers = array_merge(
        $response['data']['configurationPeers'] ?? [],
        $response['data']['configurationRestrictedPeers'] ?? []
    );
    
    $used_ips = [];
    foreach ($peers as $peer) {
        if (isset($peer['allowed_ips']) && is_array($peer['allowed_ips'])) {
            foreach ($peer['allowed_ips'] as $ip) {
                $used_ips[] = $ip;
            }
        }
    }
    return $used_ips;
}

function getUsedIPsFromDb($namepanel)
{
    global $pdo;
    $used_ips = [];
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->prepare("SELECT user_info FROM invoice WHERE Service_location = :location");
        $stmt->execute([':location' => $namepanel]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (!empty($row['user_info'])) {
                $info = json_decode($row['user_info'], true);
                if (is_array($info)) {
                    if (isset($info['allowed_ips']) && is_array($info['allowed_ips'])) {
                        foreach ($info['allowed_ips'] as $ip) {
                            $used_ips[] = $ip;
                        }
                    }
                }
            }
        }
    } catch (\Exception $e) {
        error_log("Failed to get used IPs from DB: " . $e->getMessage());
    }
    return $used_ips;
}

function getNextAvailableIP($subnet_cidr, $used_ips)
{
    if (strpos($subnet_cidr, '/') === false) {
        $subnet_cidr .= '/24';
    }
    list($subnet_ip, $cidr) = explode('/', $subnet_cidr);
    $cidr = intval($cidr);
    if ($cidr < 0 || $cidr > 32) {
        $cidr = 24;
    }
    
    $subnet_long = ip2long($subnet_ip);
    if ($subnet_long === false) {
        return null;
    }
    
    $num_ips = 1 << (32 - $cidr);
    $mask = ~($num_ips - 1);
    $network_long = $subnet_long & $mask;
    
    $used_longs = [];
    foreach ($used_ips as $ip) {
        $clean_ip = explode('/', $ip)[0];
        $long_ip = ip2long($clean_ip);
        if ($long_ip !== false) {
            $used_longs[$long_ip] = true;
        }
    }
    
    // Check hosts from .2 to the end of subnet
    for ($i = 2; $i < $num_ips - 1; $i++) {
        $candidate_long = $network_long + $i;
        
        // Skip .0 and .255 addresses to prevent OS networking quirks across all subnet sizes
        $last_octet = $candidate_long & 0xFF;
        if ($last_octet === 0 || $last_octet === 255) {
            continue;
        }
        
        if (!isset($used_longs[$candidate_long])) {
            $candidate_ip = long2ip($candidate_long);
            if ($candidate_ip !== false && filter_var($candidate_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $candidate_ip;
            }
        }
    }
    
    return null;
}

function isSubnetFull($subnet_cidr, $used_ips_array)
{
    if (empty($subnet_cidr) || strpos($subnet_cidr, '/') === false) {
        $cidr = 24;
    } else {
        $cidr = intval(explode('/', $subnet_cidr)[1]);
    }

    if ($cidr < 0 || $cidr > 32) {
        $cidr = 24;
    }

    // Mathematically account for skipped .0 and .255 IPs in subnets <= /24
    if ($cidr <= 24) {
        $capacity = pow(2, 32 - $cidr) - pow(2, 25 - $cidr) - 1;
    } else {
        $capacity = pow(2, 32 - $cidr) - 3;
    }

    $unique_ips = [];
    foreach ($used_ips_array as $ip) {
        $clean_ip = explode('/', $ip)[0];
        $unique_ips[$clean_ip] = true;
    }
    
    $used_count = count($unique_ips);

    return $used_count >= $capacity;
}