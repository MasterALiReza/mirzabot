<?php
include('config.php');
ini_set('error_log', 'error_log');


function get_userwg($username, $namepanel)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $marzban_list_get['url_panel'] . '/api/getWireguardConfigurationInfo?configurationName=' . $marzban_list_get['inboundid'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'wg-dashboard-apikey: ' . $marzban_list_get['password_panel']
        ),
    ));
    $response = json_decode(curl_exec($curl), true);
    if (!$response['status'])
        return $response;
    $configurationPeers = $response['data']['configurationPeers'];
    $configurationRestrictedPeers = $response['data']['configurationRestrictedPeers'];
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
    foreach ($ipconfig_body['data'] as $subnet => $ips) {
        if (is_array($ips) && !empty($ips)) {
            $ipToAssign = $ips[0];
            break;
        } elseif (is_string($ips) && !empty($ips)) {
            $ipToAssign = $ips;
            break;
        }
    }
    
    if (empty($ipToAssign)) {
        return array(
            'status' => false,
            'msg' => 'No available IPs left in the assigned subnets on WGDashboard.'
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