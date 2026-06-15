<?php
require_once '../baseInfo.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!function_exists('imagecreatetruecolor')) {
    echo json_encode(['success' => false, 'error' => 'PHP GD extension is not enabled on this server.']);
    exit;
}

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing user_id']);
    exit;
}

$user_id = preg_replace('/[^0-9]/', '', $_GET['user_id']);
$ref_link = "https://t.me/" . $usernamebot . "?start=" . $user_id;

$banners_dir = __DIR__ . '/../assets/banners';
if (!is_dir($banners_dir)) {
    mkdir($banners_dir, 0777, true);
}
$banners_dir = realpath($banners_dir);
$output_path = $banners_dir . DIRECTORY_SEPARATOR . 'user_' . $user_id . '.jpg';

// Helper function to fetch URL using cURL or file_get_contents
function fetch_url($url) {
    if (ini_get('allow_url_fopen')) {
        return @file_get_contents($url);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// Check for custom base banner
$base_banner_path = __DIR__ . '/../assets/banner_base.jpg';

// If base banner doesn't exist, create a generic placeholder
if (!file_exists($base_banner_path)) {
    $img = imagecreatetruecolor(800, 600);
    $bg_color = imagecolorallocate($img, 30, 30, 30);
    $text_color = imagecolorallocate($img, 255, 255, 255);
    $accent_color = imagecolorallocate($img, 0, 150, 255);
    
    imagefilledrectangle($img, 0, 0, 800, 600, $bg_color);
    
    imagestring($img, 5, 250, 50, "Invite Friends and Earn!", $text_color);
    imagestring($img, 5, 250, 100, "Your ID: " . $user_id, $accent_color);
} else {
    $img = @imagecreatefromjpeg($base_banner_path);
    if (!$img) {
        // Fallback if the uploaded JPEG is invalid or corrupted
        $img = imagecreatetruecolor(800, 600);
        $bg_color = imagecolorallocate($img, 30, 30, 30);
        $text_color = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, 800, 600, $bg_color);
        imagestring($img, 5, 250, 50, "Invite Friends and Earn!", $text_color);
    }
}

if ($img) {
    // Generate QR Code from qrserver
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($ref_link);
    $qr_data = fetch_url($qr_url);
    if ($qr_data) {
        $qr_img = @imagecreatefromstring($qr_data);
        if ($qr_img) {
            $img_width = imagesx($img);
            $img_height = imagesy($img);
            
            $qr_x = ($img_width / 2) - 100;
            $qr_y = $img_height - 250;
            
            // Adjust position if it's too small
            if ($qr_y < 0) {
                $qr_y = 10;
            }
            if ($qr_x < 0) {
                $qr_x = 10;
            }
            
            imagecopy($img, $qr_img, $qr_x, $qr_y, 0, 0, 200, 200);
            imagedestroy($qr_img);
        }
    }

    imagejpeg($img, $output_path, 90);
    imagedestroy($img);
    
    echo json_encode([
        'success' => true,
        'banner_path' => $output_path,
        'ref_link' => $ref_link
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to create image']);
}
