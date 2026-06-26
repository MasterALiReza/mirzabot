<?php
require_once 'config.php';
$pdo = new PDO('mysql:host='.$Database['host'].';dbname='.$Database['dbname'], $Database['username'], $Database['password']);
$stmt = $pdo->query("SELECT * FROM marzban_panel WHERE type = 'WGDashboard'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    echo "Panel: {$row['name_panel']} | URL: {$row['url_panel']} | Inbound: {$row['inboundid']} \n";
}
