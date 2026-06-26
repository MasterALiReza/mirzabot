<?php
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        file_put_contents('payment_fatal.log', "FATAL: " . print_r($error, true));
    } else {
        file_put_contents('payment_fatal.log', "NO FATAL ERROR.");
    }
});
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';
include 'function.php';
include 'jdf.php';
// We don't include index.php because it requires Telegram inputs.
// We will mock the variables and require panels.php

$from_id = '123456';
$message_id = 111;
$datain = 'confirmandgetservice';

// Mock user array
$user = [
    'step' => 'payment',
    'Processing_value' => '{"name_panel":"test_panel", "nameconfig":"test_note"}',
    'Processing_value_one' => 'test_product',
    'Processing_value_tow' => 'test_user',
    'Processing_value_four' => '',
    'agent' => '0',
    'Balance' => 1000000,
    'pricediscount' => 0,
    'affiliates' => ''
];
$userdate = json_decode($user['Processing_value'], true);

$parts = explode("_", $user['Processing_value_one']);
$partsdic = explode("_", $user['Processing_value_four']);

// Get panel
$marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
if (!$marzban_list_get) {
    // Insert mock panel
    $pdo->exec("INSERT IGNORE INTO marzban_panel (name_panel, status, type, MethodUsername) VALUES ('test_panel', 'active', 'marzban', 'NumericId')");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
}

// Get product
$stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND agent = :agent LIMIT 1");
$stmt->execute([
    ':code_product' => $user['Processing_value_one'],
    ':location' => $userdate['name_panel'],
    ':agent' => $user['agent']
]);
$info_product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$info_product) {
    $pdo->exec("INSERT IGNORE INTO product (code_product, Location, agent, price_product, name_product, Volume_constraint, Service_time) 
                VALUES ('test_product', 'test_panel', '0', 1000, 'Test Product', 10, 30)");
    $stmt->execute([
        ':code_product' => $user['Processing_value_one'],
        ':location' => $userdate['name_panel'],
        ':agent' => $user['agent']
    ]);
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
}

$priceproduct = $info_product['price_product'];
$username_ac = strtolower($user['Processing_value_tow']);

require_once 'panels.php';
$ManagePanel = new ManagePanel();

$date = time();
$randomString = bin2hex(random_bytes(4));
$notifctions = json_encode(['limit' => 'no', 'expire' => 'no']);

$stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,note,refral,notifctions) VALUES (?,  ?, ?, ?, ?, ?, ?,?,?,?,?,?,?)");
$Status = "unpaid";
$stmt->bind_param("sssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $priceproduct, $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $userdate['nameconfig'], $user['affiliates'], $notifctions);
$stmt->execute();

echo "FINISHED";
