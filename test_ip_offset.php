<?php
$ipconfig = [
    'status' => true,
    'data' => [
        "10.0.0.2",
        "10.0.0.3"
    ]
];
$key = array_keys($ipconfig['data'])[0];
echo "Key: $key\n";
$ip = $ipconfig['data'][$key][0];
echo "IP: $ip\n";
