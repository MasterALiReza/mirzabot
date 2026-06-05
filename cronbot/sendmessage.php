<?php
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();
if(!is_file('info'))return;
if(!is_file('users.json'))return;

// Load administrative and owner Telegram IDs
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN") ?: [];
global $adminnumber;
if (isset($adminnumber) && $adminnumber !== '') {
    $admin_ids[] = (string)$adminnumber;
}
$admin_ids = array_values(array_unique(array_filter($admin_ids)));

$info = json_decode(file_get_contents('info'), true);
if (!is_array($info)) {
    $info = [];
}

// Intercept new broadcast runs (e.g. from admin.php) and merge admins/owner
if (!isset($info['admin_appended'])) {
    $raw_userid = json_decode(file_get_contents('users.json'), true) ?: [];
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
    
    file_put_contents('users.json', json_encode($new_userid_list));
    $info['admin_appended'] = true;
    file_put_contents('info', json_encode($info));
}

$userid = json_decode(file_get_contents('users.json'));
$count = 0;
if(count($userid) == 0){
    if(isset($info['id_admin'])){
    deletemessage($info['id_admin'], $info['id_message']);
    sendmessage($info['id_admin'], $textbotlang['hardcoded']['bulkMessageDone'], null, 'HTML');
    
    $pdo->query("UPDATE broadcast_history SET status = 'completed' WHERE status IN ('in_progress', 'pending')");
    
    unlink('info');
    unlink('users.json');
    }
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
                ['text' => $textbotlang['textbot']['sell'], 'callback_data' => 'buy'],
            ],
        ]
    ]);
$keyboardstart = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['start'], 'callback_data' => 'start'],
            ],
        ]
    ]);
$keyboardusertest = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['userTest'], 'callback_data' => 'usertestbtn'],
            ],
        ]
    ]);
$keyboardhelpbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['help'], 'callback_data' => 'helpbtn'],
            ],
        ]
    ]);
$keyboardaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['affiliates'], 'callback_data' => 'affiliatesbtn'],
            ],
        ]
    ]);
$keyboardaddbalance = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['addBalance'], 'callback_data' => 'Add_Balance'],
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
    } elseif ($info['btnmessage'] == "custom_product") {
        $custom_keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $info['custom_btn_text_prod'], 'callback_data' => $info['custom_btn_callback']],
                ],
            ]
        ]);
    }
}

for ($i = 0; $i < 150; $i++) {
    if (empty($userid)) {
        break;
    }
    $iduser = array_shift($userid);
    if (!isset($iduser->id)) {
        continue;
    }
    
    $isAdminOrOwner = in_array((string)$iduser->id, $admin_ids);

    if ($info['type'] == "unpinmessage") {
        unpinmessage($iduser->id);
    } elseif ($info['type'] == "sendmessage" or $info['type'] == "xdaynotmessage") {
        $meesage = sendmessage($iduser->id, $info['message'], $custom_keyboard, 'HTML');

        if (isset($meesage['ok']) && $meesage['ok'] == false and $meesage['description'] == "Forbidden: bot was blocked by the user") {
            $invoicecount = select("invoice", "*", "id_user", $iduser->id, "count");
            $userinfo = select("user", "Balance", "id", $iduser->id, "select");
            if ($invoicecount == 0 and $userinfo['Balance'] == 0) {
                $Id_user = $iduser->id;
                $stmt = $pdo->prepare("DELETE FROM user WHERE id = '$Id_user'");
                $stmt->execute();
            }
        }

        if (isset($meesage['ok']) && $meesage['ok'] and ($info['pingmessage'] == "yes" or $isAdminOrOwner)) {
            pinmessage($iduser->id, $meesage['result']['message_id']);
        }
    } elseif ($info['type'] == "forwardmessage") {
        $meesage = forwardMessage($info['id_admin'], $info['message'], $iduser->id);
        if (isset($meesage['ok']) && $meesage['ok'] and ($info['pingmessage'] == "yes" or $isAdminOrOwner)) {
            pinmessage($iduser->id, $meesage['result']['message_id']);
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
                'chat_id' => $iduser->id,
                'from_chat_id' => $from_chat_id,
                'message_id' => $message_id
            ];
            if ($custom_keyboard) {
                $copy_params['reply_markup'] = $custom_keyboard;
            }
            $meesage = telegram('copyMessage', $copy_params);
            
            if (isset($meesage['ok']) && $meesage['ok'] == false && strpos($meesage['description'], 'Forbidden: bot was blocked by the user') !== false) {
                $invoicecount = select("invoice", "*", "id_user", $iduser->id, "count");
                $userinfo = select("user", "Balance", "id", $iduser->id, "select");
                if ($invoicecount == 0 && $userinfo['Balance'] == 0) {
                    $Id_user = $iduser->id;
                    $stmt = $pdo->prepare("DELETE FROM user WHERE id = '$Id_user'");
                    $stmt->execute();
                }
            }
            
            if (isset($meesage['ok']) && $meesage['ok'] && ($info['pingmessage'] == "yes" || $isAdminOrOwner)) {
                pinmessage($iduser->id, $meesage['result']['message_id']);
            }
        }
    }
    
    usleep(35000);
}

file_put_contents('users.json',json_encode($userid,true));