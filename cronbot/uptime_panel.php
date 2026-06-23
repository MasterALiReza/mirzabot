<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();



$admin_ids = select("admin", "id_admin",null,null,"FETCH_COLUMN");
$marzbanlist = select("marzban_panel", "*",null ,null ,"fetchAll");
$setting = select("setting", "*");
$status_cron = json_decode($setting['cron_status'],true);
if(!$status_cron['uptime_panel'])return;
$inbounds = [];
foreach($marzbanlist as $location){
    $url = rtrim($location['url_panel'], '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch);
    curl_close($ch);

    if ($httpCode == 0 && $curlError != 0) {
        foreach ($admin_ids as $admin) {
            $textnode = sprintf($textbotlang['hardcoded']['panelDownNotice'], $location['name_panel']);
            sendmessage($admin, $textnode, null, 'html');
        }
    }
}
