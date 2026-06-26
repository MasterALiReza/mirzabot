<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// We simulate a basic Telegram update JSON
$fakeUpdate = [
    "update_id" => 123456,
    "message" => [
        "message_id" => 1,
        "from" => [
            "id" => 123456789,
            "is_bot" => false,
            "first_name" => "Test",
            "username" => "testuser"
        ],
        "chat" => [
            "id" => 123456789,
            "first_name" => "Test",
            "username" => "testuser",
            "type" => "private"
        ],
        "date" => time(),
        "text" => "/start"
    ]
];

file_put_contents("php://memory", json_encode($fakeUpdate));

// Since we cannot overwrite php://input easily from CLI, let's just mock file_get_contents inside botapi.php?
// Actually, botapi.php uses file_get_contents("php://input"). 
// In CLI, php://input is empty. 
// We can temporarily modify botapi.php to read from a local file instead if it's empty, or just write a small test script that modifies botapi.php.
