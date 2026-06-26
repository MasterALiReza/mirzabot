<?php
require_once __DIR__ . '/config.php';
try {
    $stmt = $pdo->query("DESCRIBE user");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in user table:\n";
    print_r($columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
