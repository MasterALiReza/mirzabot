<?php
include('config.php');
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/WGDashboard.php';

$namepanel = 'کالاف موبایل 1'; // I should pick a valid panel name
$marzban_list_get = select("marzban_panel", "*", "type", "WGDashboard", "select");
if ($marzban_list_get) {
    echo "Panel Name: " . $marzban_list_get['name_panel'] . "\n";
    $ipconfig = ipslast($marzban_list_get['name_panel']);
    echo "Raw ipslast response: \n";
    print_r($ipconfig);
    if (!empty($ipconfig['body'])) {
        $body = json_decode($ipconfig['body'], true);
        echo "Decoded body: \n";
        print_r($body);
    }
} else {
    echo "No WGDashboard panel found.";
}
