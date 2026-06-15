<?php
require_once '../baseInfo.php';
require_once '../config.php';
require_once '../function.php';

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing user_id']);
    exit;
}

$user_id = preg_replace('/[^0-9]/', '', $_GET['user_id']);
$ref_link = "https://t.me/" . $usernamebot . "?start=" . $user_id;

$banner_path = generateUserBanner($user_id);

if ($banner_path) {
    echo json_encode([
        'success' => true,
        'banner_path' => $banner_path,
        'ref_link' => $ref_link
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to generate banner image. Make sure PHP GD extension is installed and assets folder is writable.']);
}
