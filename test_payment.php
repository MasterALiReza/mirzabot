<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include 'config.php';
    include 'function.php';
    include 'jdf.php';
    
    // Simulate payment callback data
    $from_id = '123456';
    $message_id = 111;
    $datain = 'confirmandgetservice';
    
    // Create fake user in DB for testing
    $pdo->exec("DELETE FROM user WHERE id = '123456'");
    $pdo->exec("INSERT INTO user (id, step, Processing_value, Processing_value_one, Processing_value_tow, Processing_value_four, agent, Balance) 
                VALUES ('123456', 'payment', '{\"name_panel\":\"test_panel\", \"nameconfig\":\"test_note\"}', 'test_product', 'test_user', '', '0', 1000000)");
                
    // Create fake panel and product
    $pdo->exec("DELETE FROM marzban_panel WHERE name_panel = 'test_panel'");
    $pdo->exec("INSERT INTO marzban_panel (name_panel, status, type, MethodUsername) VALUES ('test_panel', 'active', 'marzban', 'NumericId')");
    
    $pdo->exec("DELETE FROM product WHERE code_product = 'test_product'");
    $pdo->exec("INSERT INTO product (code_product, Location, agent, price_product, name_product, Volume_constraint, Service_time) 
                VALUES ('test_product', 'test_panel', '0', 1000, 'Test Product', 10, 30)");
                
    // Emulate index.php payment block execution
    $user = ['step' => 'payment', 'Processing_value' => '{"name_panel":"test_panel", "nameconfig":"test_note"}', 'Processing_value_one' => 'test_product', 'Processing_value_tow' => 'test_user', 'Processing_value_four' => '', 'agent' => '0', 'Balance' => 1000000, 'pricediscount' => 0, 'affiliates' => ''];
    $userdate = json_decode($user['Processing_value'], true);
    
    echo "1. Decode OK\n";
    
    // Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $parts = explode("_", $user['Processing_value_one']);
    $partsdic = explode("_", $user['Processing_value_four']);
    
    echo "2. Explode OK\n";
    
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    
    echo "3. Panel select OK\n";
    
    $eextraprice = json_decode($marzban_list_get['pricecustomvolume'] ?? '{}', true) ?: [];
    $custompricevalue = $eextraprice[$user['agent']] ?? 0;
    $eextraprice = json_decode($marzban_list_get['pricecustomtime'] ?? '{}', true) ?: [];
    $customtimevalueprice = $eextraprice[$user['agent']] ?? 0;
    
    echo "4. Custom price OK\n";
    
    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND agent = :agent LIMIT 1");
    $stmt->execute([
        ':code_product' => $user['Processing_value_one'],
        ':location' => $userdate['name_panel'],
        ':agent' => $user['agent']
    ]);
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "5. Product select OK\n";
    
    $priceproduct = $info_product['price_product'];
    $username_ac = strtolower($user['Processing_value_tow']);
    
    echo "6. username OK\n";
    
    include 'panel/ManagePanel.php';
    $ManagePanel = new ManagePanel();
    // mock the api response
    // $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    
    echo "7. ManagePanel OK\n";
    
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    
    $notifctions = json_encode(['limit' => 'no', 'expire' => 'no']);
    
    echo "8. Before Insert Invoice\n";
    
    $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,note,refral,notifctions) VALUES (?,  ?, ?, ?, ?, ?, ?,?,?,?,?,?,?)");
    $Status = "unpaid";
    $stmt->bind_param("sssssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $priceproduct, $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $userdate['nameconfig'], $user['affiliates'], $notifctions);
    $stmt->execute();
    
    echo "9. Insert Invoice OK\n";
    
    if (!deduct_balance_atomic($pdo, $from_id, $priceproduct)) {
        echo "Insufficient balance\n";
    }
    
    echo "10. deduct balance OK\n";
    
} catch (Throwable $e) {
    echo "CRASH: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile() . "\n";
}
