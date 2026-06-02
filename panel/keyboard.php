<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$keyboard = json_decode(file_get_contents("php://input"), true);
$method = $_SERVER['REQUEST_METHOD'];
if ($method == "POST" && is_array($keyboard)) {
    $keyboardmain = ['keyboard' => []];
    $keyboardmain['keyboard'] = $keyboard;
    update("setting", "keyboardmain", json_encode($keyboardmain), null, null);
    exit;
} else {
    $keyboardmain_default = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend"}],[{"text":"text_usertest"},{"text":"text_wheel_luck"}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"text_affiliates"},{"text":"text_Tariff_list"}],[{"text":"text_support"},{"text":"text_help"}]]}';
    $action = filter_input(INPUT_GET, 'action');
    if ($action === "reaset") {
        update("setting", "keyboardmain", $keyboardmain_default, null, null);
        header('Location: keyboard.php');
        exit;
    }
}

require_once __DIR__ . '/../function.php'; // Required for languagechange()

$textbotlang = languagechange();
$keyboardmain_db = json_decode(select("setting", "keyboardmain", null, null, "select")['keyboardmain'], true);

$list_keyboard = array(
    'text_sell', 'text_extend', 'text_usertest', 'text_wheel_luck',
    'text_Purchased_services', 'accountwallet', 'text_affiliates',
    'text_Tariff_list', 'text_support', 'text_help'
);

$text_dict = [
    'text_sell' => $textbotlang['textbot']['sell'],
    'text_extend' => $textbotlang['textbot']['extend'],
    'text_usertest' => $textbotlang['textbot']['userTest'],
    'text_wheel_luck' => $textbotlang['textbot']['wheelLuck'],
    'text_Purchased_services' => $textbotlang['textbot']['purchasedServices'],
    'accountwallet' => $textbotlang['textbot']['accountWallet'],
    'text_affiliates' => $textbotlang['textbot']['affiliates'],
    'text_Tariff_list' => $textbotlang['textbot']['tariffList'],
    'text_support' => $textbotlang['textbot']['support'],
    'text_help' => $textbotlang['textbot']['help'],
];

foreach ($keyboardmain_db['keyboard'] as $row) {
    foreach ($row as $arrkey) {
        if (in_array($arrkey['text'], $list_keyboard)) {
            $index_number = array_search($arrkey['text'], $list_keyboard);
            unset($list_keyboard[$index_number]);
        }
    }
}
$list_keyboard = array_values($list_keyboard);
$unused_keys = [];
foreach ($list_keyboard as $key) {
    $unused_keys[] = [['text' => $key]];
}

$initial_data = [
    'keylist' => $unused_keys,
    'userlist' => $keyboardmain_db['keyboard'],
    'text' => $text_dict
];
?>

<!doctype html>
<html lang="FA">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $textbotlang['panel']['keyboardManageTitle'] ?></title>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        window.KEYBOARD_INITIAL_DATA = <?= json_encode($initial_data) ?>;
    </script>
    <link rel="stylesheet" href="css/keyboard_builder.css">
    <script src="js/keyboard_builder.js" defer></script>
    
    <style>
        @import url(https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap);

        * {
            font-family: 'Vazirmatn', sans-serif !important;
        }

        button {
            font-family: 'Vazirmatn', sans-serif;
        }

        .btnback {
            position: fixed;
            top: 10px;
            left: 10px;
            padding: 7px 15px;
            background-color: #334155;
            color: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            z-index: 100;
        }
        
        .btnback:hover {
            background-color: #1e293b;
        }

        .btndefult {
            position: fixed;
            top: 10px;
            left: 150px;
            padding: 7px 15px;
            background-color: #fff;
            border: 1px solid #cbd5e1;
            color: #334155;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            z-index: 100;
        }
        
        .btndefult:hover {
            background-color: #f1f5f9;
        }
        
        body {
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
        }
    </style>
</head>

<body>
    <a class="btnback" href="index.php"><?= $textbotlang['panel']['keyboardSortHint'] ?? 'بازگشت به پنل کاربری' ?></a>
    <a class="btndefult" href="keyboard.php?action=reaset"><?= $textbotlang['panel']['keyboardSaveBtn'] ?? 'بازگشت به حالت پیشفرض' ?></a>
    
    <div class="keyboard-app-container">
        
        <!-- Unused Keys Section -->
        <div class="board-section">
            <div class="container-header">دکمه‌های در دسترس (غیرفعال)</div>
            <p style="font-size: 13px; color: #64748b; margin-top:-10px; margin-bottom:15px;">برای حذف دکمه از کیبورد، آن را بگیرید و اینجا رها کنید.</p>
            <div id="unused-keys">
                <!-- Unused buttons will be injected here -->
            </div>
        </div>

        <!-- Active Keyboard Section -->
        <div class="board-section">
            <div class="container-header">چیدمان کیبورد ربات (فعال)</div>
            <p style="font-size: 13px; color: #64748b; margin-top:-10px; margin-bottom:15px;">دکمه‌ها را بگیرید و جابجا کنید. ردیف‌های خالی خودکار حذف می‌شوند.</p>
            <div id="telegram-board" class="telegram-board">
                <!-- Rows will be injected here -->
            </div>
            
            <div class="actions-bar">
                <button id="save-keyboard-btn" class="save-keyboard-btn">ذخیره تغییرات</button>
            </div>
        </div>
        
    </div>

    <!-- Add Button Modal -->
    <div id="addBtnModalVeil" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:20px; border-radius:12px; width:90%; max-width:350px; text-align:center;">
            <h4 style="margin-top:0;">انتخاب دکمه جدید</h4>
            <p style="font-size:12px; color:#64748b;">یک دکمه از لیست زیر انتخاب کنید:</p>
            <div id="addBtnList" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:15px; justify-content:center; max-height:250px; overflow-y:auto; padding-bottom:10px;">
            </div>
            <button onclick="document.getElementById('addBtnModalVeil').style.display='none'" style="margin-top:20px; width:100%; padding:10px; border-radius:8px; border:none; background:#e2e8f0; font-family:inherit; cursor:pointer; font-weight:bold;">انصراف</button>
        </div>
    </div>
</body>

</html>