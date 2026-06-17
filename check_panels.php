<?php
require_once "baseinfo.php";
$stmt = $pdo->query("SELECT DISTINCT Service_location FROM invoice");
$locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach($locations as $loc) {
    echo "'" . $loc . "' (Length: " . mb_strlen($loc) . ", Hex: " . bin2hex($loc) . ")\n";
}
