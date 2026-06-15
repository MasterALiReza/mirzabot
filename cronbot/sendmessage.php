<?php
ignore_user_abort(true);
set_time_limit(0);
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();
$infoFile = __DIR__ . '/info';
$usersFile = __DIR__ . '/users.json';
$cancelFile = __DIR__ . '/cancel_broadcast';
$lockFile = __DIR__ . '/sendmessage.lock';

$lockFp = fopen($lockFile, 'w+');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Another instance is already running, prevent duplicate execution
    exit;
}

function broadcast_cleanup_files(string $infoFile, string $usersFile, string $cancelFile): void
{
    if (is_file($infoFile)) {
        @unlink($infoFile);
    }
    if (is_file($usersFile)) {
        @unlink($usersFile);
    }
    if (is_file($cancelFile)) {
        @unlink($cancelFile);
    }
}

function broadcast_mark_cancelled(PDO $pdo): void
{
    $pdo->query("UPDATE broadcast_history SET status = 'cancelled' WHERE status IN ('in_progress', 'pending', 'cancelling')");
}

function broadcast_cancel_requested(string $cancelFile): bool
{
    return is_file($cancelFile);
}

if (broadcast_cancel_requested($cancelFile)) {
    if (is_file($infoFile) || is_file($usersFile)) {
        broadcast_mark_cancelled($pdo);
        broadcast_cleanup_files($infoFile, $usersFile, $cancelFile);
    } else {
        @unlink($cancelFile);
    }
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return;
}

if(!is_file($infoFile)) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return;
}
if(!is_file($usersFile)) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return;
}

function broadcast_user_id($entry): ?string
{
    if (is_array($entry) && isset($entry['id'])) {
        return (string) $entry['id'];
    }
    if (is_object($entry) && isset($entry->id)) {
        return (string) $entry->id;
    }
    if (is_scalar($entry)) {
        return (string) $entry;
    }
    return null;
}


// Load administrative and owner Telegram IDs
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN") ?: [];
global $adminnumber;
if (isset($adminnumber) && $adminnumber !== '') {
    $admin_ids[] = (string)$adminnumber;
}
$admin_ids = array_values(array_unique(array_filter($admin_ids)));

$info = json_decode(file_get_contents($infoFile), true);
if (!is_array($info)) {
    $info = [];
}


// Intercept new broadcast runs (e.g. from admin.php) and merge admins/owner
if (!isset($info['admin_appended'])) {
$raw_userid = json_decode(file_get_contents($usersFile), true) ?: [];

    $existing_ids = [];

    foreach ($raw_userid as $u) {
        if (is_array($u) && isset($u['id'])) {
            $existing_ids[] = (string)$u['id'];
        } elseif (is_object($u) && isset($u->id)) {
            $existing_ids[] = (string)$u->id;
        } elseif (is_scalar($u)) {
            $existing_ids[] = (string)$u;
        }
    }
    
    $merged_ids = array_merge($existing_ids, $admin_ids);
    $merged_ids = array_values(array_unique(array_filter($merged_ids)));
    
    $new_userid_list = [];
    foreach ($merged_ids as $id) {
        $new_userid_list[] = ['id' => $id];
    }
    
    file_put_contents($usersFile, json_encode($new_userid_list));
    $info['admin_appended'] = true;
    file_put_contents($infoFile, json_encode($info));


}

$userid = json_decode(file_get_contents($usersFile), true);
if (!is_array($userid)) {
    $userid = [];
}


$count = 0;
if (count($userid) == 0) {
    if(isset($info['id_admin'])){
        deletemessage($info['id_admin'], $info['id_message']);
        sendmessage($info['id_admin'], $textbotlang['hardcoded']['bulkMessageDone'], null, 'HTML');
    }
    $stmt = $pdo->prepare("UPDATE broadcast_history SET status = 'completed' WHERE status IN ('in_progress', 'pending')");
    $stmt->execute();
    broadcast_cleanup_files($infoFile, $usersFile, $cancelFile);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return;
}
$count_remein = count($userid);
$textprocces = sprintf($textbotlang['hardcoded']['bulkMessageProgress'], $count_remein);
$cancelmessage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['cancelOperation'], 'callback_data' => 'cancel_sendmessage'],
            ],
        ]
    ]);
Editmessagetext($info['id_admin'], $info['id_message'],$textprocces, $cancelmessage);
$keyboardbuy = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['sell'], 'callback_data' => 'buy_broadcast'],
            ],
        ]
    ]);
$keyboardstart = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['start'], 'callback_data' => 'start_broadcast'],
            ],
        ]
    ]);
$keyboardusertest = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['userTest'], 'callback_data' => 'usertestbtn_broadcast'],
            ],
        ]
    ]);
$keyboardhelpbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['help'], 'callback_data' => 'helpbtn_broadcast'],
            ],
        ]
    ]);
$keyboardaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['affiliates'], 'callback_data' => 'affiliatesbtn_broadcast'],
            ],
        ]
    ]);
$keyboardaddbalance = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['addBalance'], 'callback_data' => 'Add_Balance_broadcast'],
            ],
        ]
    ]);
$custom_keyboard = null;
if (isset($info['btnmessage']) && $info['btnmessage'] !== "none") {
    if ($info['btnmessage'] == "buy") {
        $custom_keyboard = $keyboardbuy;
    } elseif ($info['btnmessage'] == "start") {
        $custom_keyboard = $keyboardstart;
    } elseif ($info['btnmessage'] == "usertestbtn") {
        $custom_keyboard = $keyboardusertest;
    } elseif ($info['btnmessage'] == "helpbtn") {
        $custom_keyboard = $keyboardhelpbtn;
    } elseif ($info['btnmessage'] == "affiliatesbtn") {
        $custom_keyboard = $keyboardaffiliates;
    } elseif ($info['btnmessage'] == "addbalance") {
        $custom_keyboard = $keyboardaddbalance;
    } elseif ($info['btnmessage'] == "custom_url") {
        $custom_keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $info['custom_btn_text_url'], 'url' => $info['custom_btn_link']],
                ],
            ]
        ]);
    } elseif ($info['btnmessage'] == "custom_url_dynamic") {
        $dynamic_buttons = json_decode($info['custom_btn_dynamic'], true) ?: [];
        $keyboard_rows = [];
        foreach ($dynamic_buttons as $btn) {
            $btn_arr = ['text' => $btn['text'], 'url' => $btn['url']];
            if (isset($btn['color']) && $btn['color'] !== 'default') {
                $btn_arr['color'] = $btn['color'];
            }
            $keyboard_rows[] = [$btn_arr];
        }
        $custom_keyboard = json_encode(['inline_keyboard' => $keyboard_rows]);
    } elseif ($info['btnmessage'] == "custom_product") {
        $custom_keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $info['custom_btn_text_prod'], 'callback_data' => $info['custom_btn_callback'] . '_broadcast'],
                ],
            ]
        ]);
    }
}

for ($i = 0; $i < 150; $i++) {
    if (broadcast_cancel_requested($cancelFile)) {
        broadcast_mark_cancelled($pdo);
        broadcast_cleanup_files($infoFile, $usersFile, $cancelFile);
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        return;
    }

    if (empty($userid)) {
        break;
    }
    $iduser = broadcast_user_id(array_shift($userid));
    if ($iduser === null || $iduser === '') {
        continue;
    }
    
    $isAdminOrOwner = in_array((string)$iduser, $admin_ids);

    if ($info['type'] == "unpinmessage") {
        unpinmessage($iduser);
    } elseif ($info['type'] == "sendmessage" or $info['type'] == "xdaynotmessage") {
        $meesage = sendmessage($iduser, $info['message'], $custom_keyboard, 'HTML');

        if (isset($meesage['ok']) && $meesage['ok'] == false and $meesage['description'] == "Forbidden: bot was blocked by the user") {
            $invoicecount = select("invoice", "*", "id_user", $iduser, "count");
            $userinfo = select("user", "Balance", "id", $iduser, "select");
            if ($invoicecount == 0 and $userinfo['Balance'] == 0) {
                $stmt = $pdo->prepare("DELETE FROM user WHERE id = ?");
                $stmt->execute([$iduser]);
            }
        }

        if (isset($meesage['ok']) && $meesage['ok'] and ($info['pingmessage'] == "yes")) {
            pinmessage($iduser, $meesage['result']['message_id']);
        }
    } elseif ($info['type'] == "forwardmessage") {
        $meesage = forwardMessage($info['id_admin'], $info['message'], $iduser);
        if (isset($meesage['ok']) && $meesage['ok'] and ($info['pingmessage'] == "yes")) {
            pinmessage($iduser, $meesage['result']['message_id']);
        }
    } elseif ($info['type'] == "forwardlink") {
        $link = $info['message'];
        $from_chat_id = '';
        $message_id = '';
        
        if (preg_match('/t\.me\/c\/(\d+)\/(\d+)/', $link, $matches)) {
            $from_chat_id = '-100' . $matches[1];
            $message_id = $matches[2];
        } elseif (preg_match('/t\.me\/([a-zA-Z0-9_]+)\/(\d+)/', $link, $matches)) {
            $from_chat_id = '@' . $matches[1];
            $message_id = $matches[2];
        }
        
        if ($from_chat_id && $message_id) {
            $copy_params = [
                'chat_id' => $iduser,
                'from_chat_id' => $from_chat_id,
                'message_id' => $message_id
            ];
            if ($custom_keyboard) {
                $copy_params['reply_markup'] = $custom_keyboard;
            }
            $meesage = telegram('copyMessage', $copy_params);
            
            if (isset($meesage['ok']) && $meesage['ok'] == false && strpos($meesage['description'], 'Forbidden: bot was blocked by the user') !== false) {
                $invoicecount = select("invoice", "*", "id_user", $iduser, "count");
                $userinfo = select("user", "Balance", "id", $iduser, "select");
                if ($invoicecount == 0 && $userinfo['Balance'] == 0) {
                    $stmt = $pdo->prepare("DELETE FROM user WHERE id = ?");
                    $stmt->execute([$iduser]);
                }
            }
            
            if (isset($meesage['ok']) && $meesage['ok'] && ($info['pingmessage'] == "yes")) {
                pinmessage($iduser, $meesage['result']['message_id']);
            }
        }
    }
    
    usleep(35000);
}

if (broadcast_cancel_requested($cancelFile)) {
    broadcast_mark_cancelled($pdo);
    broadcast_cleanup_files($infoFile, $usersFile, $cancelFile);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return;
}

file_put_contents($usersFile, json_encode($userid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

flock($lockFp, LOCK_UN);
fclose($lockFp);
