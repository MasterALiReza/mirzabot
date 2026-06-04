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

$pageTitle = $textbotlang['panel']['keyboardManageTitle'] ?? 'تنظیمات کیبورد';
$activeNav = 'keyboard';
include __DIR__ . '/inc/layout_head.php';
?>

<script src="js/sortable.min.js"></script>
<script>
    window.KEYBOARD_INITIAL_DATA = <?= json_encode($initial_data) ?>;
</script>
<link rel="stylesheet" href="css/keyboard_builder.css">

<div class="card fade-up">
    <div class="card-head" style="flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 class="card-title"><?= $textbotlang['panel']['keyboardManageTitle'] ?? 'چیدمان کیبورد' ?></h3>
            <p class="card-subtitle">با کشیدن و رها کردن (Drag & Drop) دکمه‌ها را جابجا کنید.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a class="btn btn-ghost btn-sm" href="keyboard.php?action=reaset" data-confirm="آیا از بازگردانی کیبورد به حالت پیش‌فرض مطمئن هستید؟">
                <?= icon('refresh-cw', 14) ?> <?= $textbotlang['panel']['keyboardSaveBtn'] ?? 'بازگشت به حالت پیش‌فرض' ?>
            </a>
        </div>
    </div>
    
    <div class="card-body" style="padding: 24px;">
        <div class="keyboard-app-container" style="max-width: 100%;">
            
            <!-- Unused Keys Section -->
            <div class="board-section">
                <div class="container-header">دکمه‌های در دسترس (غیرفعال)</div>
                <p style="font-size: 13px; color: var(--text-muted, #64748b); margin-top:-10px; margin-bottom:15px;">برای حذف دکمه از کیبورد، آن را بگیرید و اینجا رها کنید.</p>
                <div id="unused-keys">
                    <!-- Unused buttons will be injected here -->
                </div>
            </div>

            <!-- Active Keyboard Section -->
            <div class="board-section">
                <div class="container-header">چیدمان کیبورد ربات (فعال)</div>
                <p style="font-size: 13px; color: var(--text-muted, #64748b); margin-top:-10px; margin-bottom:15px;">دکمه‌ها را بگیرید و جابجا کنید. ردیف‌های خالی خودکار حذف می‌شوند.</p>
                <div id="telegram-board" class="telegram-board">
                    <!-- Rows will be injected here -->
                </div>
                
                <div class="actions-bar">
                    <button id="save-keyboard-btn" class="btn btn-ok" style="width: 100%; border-radius: 8px; padding: 12px; font-weight: bold; font-family: 'Vazirmatn', sans-serif;">ذخیره تغییرات</button>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Add Button Modal -->
<div id="addBtnModalVeil" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--card-bg, #fff); color:var(--text-color, #334155); padding:20px; border-radius:12px; width:90%; max-width:350px; text-align:center; border: 1px solid var(--border-color);">
        <h4 style="margin-top:0;">انتخاب دکمه جدید</h4>
        <p style="font-size:12px; color:var(--text-muted, #64748b);">یک دکمه از لیست زیر انتخاب کنید:</p>
        <div id="addBtnList" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:15px; justify-content:center; max-height:250px; overflow-y:auto; padding-bottom:10px;">
        </div>
        <button onclick="document.getElementById('addBtnModalVeil').style.display='none'" style="margin-top:20px; width:100%; padding:10px; border-radius:8px; border:none; background:var(--input-bg, #e2e8f0); color:inherit; font-family:inherit; cursor:pointer; font-weight:bold;">انصراف</button>
    </div>
</div>

<!-- Color Picker Modal -->
<div id="colorPickerModalVeil" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--card-bg, #fff); color:var(--text-color, #334155); padding:20px; border-radius:12px; width:90%; max-width:350px; text-align:center; border: 1px solid var(--border-color);">
        <h4 style="margin-top:0;">تغییر رنگ دکمه</h4>
        <p style="font-size:12px; color:var(--text-muted, #64748b); margin-bottom:15px;">یک رنگ تلگرامی برای این دکمه انتخاب کنید:</p>
        
        <div style="display:flex; flex-direction:column; gap:10px;">
            <button class="kb-btn btn-primary" onclick="setBtnStyle('primary')" style="width:100%; border-radius:8px; border:none; padding:12px; cursor:pointer; font-family: inherit;">آبی (Primary)</button>
            <button class="kb-btn btn-success" onclick="setBtnStyle('success')" style="width:100%; border-radius:8px; border:none; padding:12px; cursor:pointer; font-family: inherit;">سبز (Success)</button>
            <button class="kb-btn btn-danger" onclick="setBtnStyle('danger')" style="width:100%; border-radius:8px; border:none; padding:12px; cursor:pointer; font-family: inherit;">قرمز (Danger)</button>
            <button class="kb-btn btn-secondary" onclick="setBtnStyle('default')" style="width:100%; border-radius:8px; border:1px solid #cbd5e1; padding:12px; cursor:pointer; font-family: inherit; color: #333;">پیش‌فرض (Default)</button>
        </div>

        <button onclick="document.getElementById('colorPickerModalVeil').style.display='none'" style="margin-top:20px; width:100%; padding:10px; border-radius:8px; border:none; background:var(--input-bg, #e2e8f0); color:inherit; font-family:inherit; cursor:pointer; font-weight:bold;">انصراف</button>
    </div>
</div>

<script src="js/keyboard_builder.js" defer></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
