<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Fetch settings
try {
    $row = db_fetch($pdo, "SELECT * FROM setting LIMIT 1");
} catch (Exception $e) {
    $row = [];
}

$pay_settings = [];
try {
    $stmt = $pdo->query("SELECT NamePay, ValuePay FROM PaySetting");
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($r['NamePay'] === 'minbalance' || $r['NamePay'] === 'maxbalance') {
            $decoded = json_decode($r['ValuePay'], true);
            if (is_array($decoded)) {
                $pay_settings[$r['NamePay']] = $decoded['n'] ?? ($decoded['allusers'] ?? '');
                $pay_settings[$r['NamePay'] . 'paynotverify_from_json'] = $decoded['f'] ?? '';
            } else {
                $pay_settings[$r['NamePay']] = $r['ValuePay'];
            }
        } else {
            $pay_settings[$r['NamePay']] = $r['ValuePay'];
        }
    }
    if (isset($pay_settings['minbalancepaynotverify_from_json']) && $pay_settings['minbalancepaynotverify_from_json'] !== '') {
        $pay_settings['minbalancepaynotverify'] = $pay_settings['minbalancepaynotverify_from_json'];
    }
    if (isset($pay_settings['maxbalancepaynotverify_from_json']) && $pay_settings['maxbalancepaynotverify_from_json'] !== '') {
        $pay_settings['maxbalancepaynotverify'] = $pay_settings['maxbalancepaynotverify_from_json'];
    }
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT cardnumber, namecard FROM card_number LIMIT 1");
    if($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pay_settings['cardnumber'] = $r['cardnumber'];
        $pay_settings['namecard'] = $r['namecard'];
    }
} catch (Exception $e) {}

$shop_settings = [];
try {
    $stmt = $pdo->query("SELECT Namevalue, value FROM shopSetting");
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($r['Namevalue'] === 'chashbackextend_agent') {
            $decoded = json_decode($r['value'], true);
            if (is_array($decoded)) {
                $shop_settings['chashbackextend_agent_n'] = $decoded['n'] ?? 0;
                $shop_settings['chashbackextend_agent_n2'] = $decoded['n2'] ?? 0;
            }
        } else {
            $shop_settings[$r['Namevalue']] = $r['value'];
        }
    }
} catch (Exception $e) {}

$affiliate_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM affiliates LIMIT 1");
    if($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $affiliate_settings = $r;
    }
} catch (Exception $e) {}

$cron_status = json_decode($row['cron_status'] ?? '{}', true);
$limitnumber = json_decode($row['limitnumber'] ?? '{}', true);
$lottery_prize = json_decode($row['Lottery_prize'] ?? '{}', true);

$schema = [
    'shop' => [
        'title' => 'فروشگاه',
        'icon' => 'package',
        'sections' => [
            'تنظیمات عمومی فروشگاه' => [
                ['name' => 'set_bulkbuy', 'label' => 'خرید عمده', 'type' => 'select', 'options' => ['onbulk' => 'مجاز', 'offbulk' => 'غیرمجاز'], 'val' => $row['bulkbuy'] ?? ''],
                ['name' => 'shop_minbalancebuybulk', 'label' => 'حداقل موجودی خرید عمده', 'type' => 'number', 'val' => $shop_settings['minbalancebuybulk'] ?? '0'],
                ['name' => 'set_statuscategory', 'label' => 'دسته‌بندی در فروشگاه', 'type' => 'select', 'options' => ['oncategory' => 'فعال', 'offcategory' => 'غیرفعال'], 'val' => $row['statuscategory'] ?? ''],
                ['name' => 'set_statuscategorygenral', 'label' => 'دسته‌بندی سراسری', 'type' => 'select', 'options' => ['oncategorys' => 'فعال', 'offcategorys' => 'غیرفعال'], 'val' => $row['statuscategorygenral'] ?? ''],
                ['name' => 'set_statusterffh', 'label' => 'نمایش لیست تعرفه‌ها', 'type' => 'select', 'options' => ['onterffh' => 'فعال', 'offterffh' => 'غیرفعال'], 'val' => $row['statusterffh'] ?? ''],
                ['name' => 'shop_statusdirectpabuy', 'label' => 'پرداخت مستقیم (بدون شارژ)', 'type' => 'select', 'options' => ['ondirectbuy' => 'فعال', 'offdirectbuy' => 'غیرفعال'], 'val' => $shop_settings['statusdirectpabuy'] ?? ''],
                ['name' => 'shop_statusdisorder', 'label' => 'وضعیت اختلال فروشگاه', 'type' => 'select', 'options' => ['ondisorder' => 'اختلال', 'offdisorder' => 'عادی'], 'val' => $shop_settings['statusdisorder'] ?? ''],
                ['name' => 'shop_statuschangeservice', 'label' => 'امکان تغییر سرویس', 'type' => 'select', 'options' => ['onstatus' => 'مجاز', 'offstatus' => 'غیرمجاز'], 'val' => $shop_settings['statuschangeservice'] ?? ''],
                ['name' => 'shop_statusshowprice', 'label' => 'نمایش قیمت‌ها', 'type' => 'select', 'options' => ['onshowprice' => 'نمایش', 'offshowprice' => 'مخفی'], 'val' => $shop_settings['statusshowprice'] ?? ''],
                ['name' => 'shop_configshow', 'label' => 'نمایش کانفیگ پس از خرید', 'type' => 'select', 'options' => ['onconfig' => 'نمایش', 'offconfig' => 'عدم نمایش'], 'val' => $shop_settings['configshow'] ?? ''],
                ['name' => 'shop_backserviecstatus', 'label' => 'بازگشت سرویس به فروشگاه', 'type' => 'select', 'options' => ['on' => 'فعال', 'off' => 'غیرفعال'], 'val' => $shop_settings['backserviecstatus'] ?? ''],
                ['name' => 'set_Debtsettlement', 'label' => 'تسویه حساب بدهی', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['Debtsettlement'] ?? ''],
                ['name' => 'set_statuslimitchangeloc', 'label' => 'محدودیت تغییر لوکیشن', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['statuslimitchangeloc'] ?? ''],
            ],
            'حجم و زمان اضافه (اکسترا)' => [
                ['name' => 'shop_chashbackextend', 'label' => 'درصد کش‌بک تمدید', 'type' => 'number', 'val' => $shop_settings['chashbackextend'] ?? '0'],
                ['name' => 'shop_statusextra', 'label' => 'فروش حجم اضافه', 'type' => 'select', 'options' => ['onextra' => 'فعال', 'offextra' => 'غیرفعال'], 'val' => $shop_settings['statusextra'] ?? ''],
                ['name' => 'shop_statustimeextra', 'label' => 'فروش زمان اضافه', 'type' => 'select', 'options' => ['ontimeextraa' => 'فعال', 'offtimeextraa' => 'غیرفعال'], 'val' => $shop_settings['statustimeextra'] ?? ''],
                ['name' => 'shop_customvolmef', 'label' => 'قیمت حجم اضافه (پلن f)', 'type' => 'number', 'val' => $shop_settings['customvolmef'] ?? ''],
                ['name' => 'shop_customvolmen', 'label' => 'قیمت حجم اضافه (پلن n)', 'type' => 'number', 'val' => $shop_settings['customvolmen'] ?? ''],
                ['name' => 'shop_customvolmen2', 'label' => 'قیمت حجم اضافه (پلن n2)', 'type' => 'number', 'val' => $shop_settings['customvolmen2'] ?? ''],
                ['name' => 'shop_customtimepricef', 'label' => 'قیمت زمان اضافه (پلن f)', 'type' => 'number', 'val' => $shop_settings['customtimepricef'] ?? ''],
                ['name' => 'shop_customtimepricen', 'label' => 'قیمت زمان اضافه (پلن n)', 'type' => 'number', 'val' => $shop_settings['customtimepricen'] ?? ''],
                ['name' => 'shop_customtimepricen2', 'label' => 'قیمت زمان اضافه (پلن n2)', 'type' => 'number', 'val' => $shop_settings['customtimepricen2'] ?? ''],
                ['name' => 'shop_chashbackextend_agent_n', 'label' => 'کش‌بک تمدید نماینده (n)', 'type' => 'number', 'val' => $shop_settings['chashbackextend_agent_n'] ?? '0'],
                ['name' => 'shop_chashbackextend_agent_n2', 'label' => 'کش‌بک تمدید نماینده (n2)', 'type' => 'number', 'val' => $shop_settings['chashbackextend_agent_n2'] ?? '0'],
            ]
        ]
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    
    $updates_setting = [];
    $params_setting = [];
    $new_cardnumber = null;
    $new_namecard = null;
    
    $new_cron_status = json_decode($row['cron_status'] ?? '{}', true);
    if (!is_array($new_cron_status)) $new_cron_status = [];
    
    $new_limitnumber = json_decode($row['limitnumber'] ?? '{}', true);
    if (!is_array($new_limitnumber)) $new_limitnumber = [];
    
    $new_lottery_prize = json_decode($row['Lottery_prize'] ?? '{}', true);
    if (!is_array($new_lottery_prize)) $new_lottery_prize = [];
    
    $new_chashbackextend_agent = ['n' => 0, 'n2' => 0];
    $stmt = $pdo->query("SELECT value FROM shopSetting WHERE Namevalue = 'chashbackextend_agent'");
    if($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dec = json_decode($r['value'], true);
        if(is_array($dec)) $new_chashbackextend_agent = $dec;
    }
    
    $updates_affiliates = [];
    $params_affiliates = [];
    
    foreach($_POST as $key => $val) {
        if(strpos($key, 'set_cron_') === 0) {
            $field = substr($key, 9);
            $new_cron_status[$field] = ($val === '1');
        } elseif(strpos($key, 'set_limitnumber_') === 0) {
            $field = substr($key, 16);
            $new_limitnumber[$field] = intval($val);
        } elseif(strpos($key, 'set_prize_') === 0) {
            $field = substr($key, 10);
            $new_lottery_prize[$field] = strval($val);
        } elseif(strpos($key, 'set_') === 0) {
            $field = substr($key, 4);
            $updates_setting[] = "$field = ?";
            $params_setting[] = $val;
        } elseif(strpos($key, 'pay_') === 0) {
            $field = substr($key, 4);
            if ($field === 'cardnumber') {
                $new_cardnumber = $val;
            } elseif ($field === 'namecard') {
                $new_namecard = $val;
            } elseif ($field === 'minbalance' || $field === 'maxbalance') {
                $old_json = db_fetch($pdo, "SELECT ValuePay FROM PaySetting WHERE NamePay = ?", [$field])['ValuePay'] ?? '';
                $decoded = json_decode($old_json, true);
                if (!is_array($decoded)) $decoded = [];
                $decoded['n'] = $val;
                $decoded['n2'] = $val;
                $decoded['allusers'] = $val;
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [json_encode($decoded), $field]);
            } elseif ($field === 'minbalancepaynotverify') {
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $field]);
                $old_json = db_fetch($pdo, "SELECT ValuePay FROM PaySetting WHERE NamePay = ?", ['minbalance'])['ValuePay'] ?? '';
                $decoded = json_decode($old_json, true);
                if (!is_array($decoded)) $decoded = [];
                $decoded['f'] = $val;
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [json_encode($decoded), 'minbalance']);
            } elseif ($field === 'maxbalancepaynotverify') {
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $field]);
                $old_json = db_fetch($pdo, "SELECT ValuePay FROM PaySetting WHERE NamePay = ?", ['maxbalance'])['ValuePay'] ?? '';
                $decoded = json_decode($old_json, true);
                if (!is_array($decoded)) $decoded = [];
                $decoded['f'] = $val;
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [json_encode($decoded), 'maxbalance']);
            } else {
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $field]);
            }
        } elseif(strpos($key, 'shop_chashbackextend_agent_') === 0) {
            $field = substr($key, 27);
            $new_chashbackextend_agent[$field] = intval($val);
        } elseif(strpos($key, 'shop_') === 0) {
            $field = substr($key, 5);
            db_query($pdo, "UPDATE shopSetting SET value = ? WHERE Namevalue = ?", [$val, $field]);
        } elseif(strpos($key, 'aff_') === 0) {
            $field = substr($key, 4);
            $updates_affiliates[] = "$field = ?";
            $params_affiliates[] = $val;
        }
    }
    
    $updates_setting[] = "cron_status = ?";
    $params_setting[] = json_encode($new_cron_status);
    
    $updates_setting[] = "limitnumber = ?";
    $params_setting[] = json_encode($new_limitnumber);
    
    $updates_setting[] = "Lottery_prize = ?";
    $params_setting[] = json_encode($new_lottery_prize);
    
    db_query($pdo, "UPDATE shopSetting SET value = ? WHERE Namevalue = ?", [json_encode($new_chashbackextend_agent), 'chashbackextend_agent']);
    
    if(!empty($updates_affiliates)) {
        db_query($pdo, "UPDATE affiliates SET " . implode(', ', $updates_affiliates), $params_affiliates);
    }
    
    if ($new_cardnumber !== null && $new_namecard !== null) {
        $old = db_fetch($pdo, "SELECT cardnumber, namecard FROM card_number LIMIT 1");
        $old_card = $old ? $old['cardnumber'] : null;
        if ($old_card !== $new_cardnumber || ($old && $old['namecard'] !== $new_namecard) || !$old) {
            if ($old_card) {
                db_query($pdo, "DELETE FROM card_number WHERE cardnumber = ?", [$old_card]);
            }
            if ($new_cardnumber) {
                db_query($pdo, "INSERT IGNORE INTO card_number (cardnumber, namecard) VALUES (?, ?)", [$new_cardnumber, $new_namecard]);
            }
        }
    }
    
    if(!empty($updates_setting)) {
        db_query($pdo, "UPDATE setting SET " . implode(', ', $updates_setting), $params_setting);
    }

    flash('success', $textbotlang['panel']['botSettingsSuccess'] ?? 'تنظیمات با موفقیت ذخیره شد.');
    $redirect_tab = $_POST['current_tab'] ?? 'general';
    $redirect_sec = $_POST['current_sec'] ?? '';
    header('Location: settings_shop.php?tab=' . urlencode($redirect_tab) . '&sec=' . urlencode($redirect_sec));
    exit;
}

$tab = $_GET['tab'] ?? 'general';
if (!array_key_exists($tab, $schema)) {
    $tab = 'general';
}

$sections = array_keys($schema[$tab]['sections']);
$sec = $_GET['sec'] ?? $sections[0];
if (!in_array($sec, $sections)) {
    $sec = $sections[0];
}

$pageTitle = 'تنظیمات فروشگاه';
$activeNav = 'settings_shop';
include __DIR__ . '/inc/layout_head.php';
?>

<style>
.arvan-layout {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}
.arvan-main-tabs {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.arvan-main-tab-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background: var(--sf);
    border: 1px solid var(--bd);
    border-radius: 12px;
    color: var(--text2);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.95rem;
    white-space: nowrap;
    outline: none;
}
.arvan-main-tab-btn.active {
    background: var(--ac);
    color: var(--btn-ac-text, #fff);
    border-color: var(--ac);
    box-shadow: 0 4px 15px var(--acs);
}
.arvan-main-tab-btn:hover:not(.active) {
    background: var(--bg);
}

.arvan-tab-card {
    display: flex;
    flex-direction: column;
    background: var(--sf);
    border-radius: 16px;
    border: 1px solid var(--bd);
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    overflow: hidden;
}

.arvan-sidebar {
    background: var(--sf2);
    border-bottom: 1px solid var(--bd);
}

.arvan-sub-tabs {
    display: flex;
    overflow-x: auto;
    padding: 10px 15px;
    gap: 5px;
}
.arvan-sub-tab-btn {
    padding: 10px 18px;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: var(--text2);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    white-space: nowrap;
    outline: none;
}
.arvan-sub-tab-btn.active {
    background: var(--ac);
    color: var(--btn-ac-text, #fff);
    font-weight: 600;
}
.arvan-sub-tab-btn:hover:not(.active) {
    background: var(--sf3);
    color: var(--text);
}

.arvan-content-area {
    flex: 1;
    min-width: 0;
    padding: 25px;
}

@media (min-width: 768px) {
    .arvan-tab-card {
        flex-direction: row;
        min-height: 500px;
    }
    .arvan-sidebar {
        width: 240px;
        flex-shrink: 0;
        border-bottom: none;
        border-left: 1px solid var(--bd);
    }
    .arvan-sub-tabs {
        flex-direction: column;
        padding: 20px 10px;
        gap: 8px;
        overflow-x: visible;
    }
    .arvan-sub-tab-btn {
        text-align: right;
        padding: 12px 15px;
    }
}

.arvan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}
.toggle-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: var(--sf);
    border-radius: 12px;
    border: 1px solid var(--bd);
    gap: 12px;
    text-align: right;
    transition: all 0.2s ease;
}
.toggle-field:hover {
    border-color: var(--ac);
    background: var(--sf2);
    box-shadow: 0 0 0 2px var(--acs);
}
.toggle-texts {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
}
.toggle-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
    line-height: 1.4;
    cursor: pointer;
}
.toggle-state {
    font-size: 0.72rem;
    color: var(--ac);
    font-weight: 500;
}

.arvan-select {
    width: 100%;
    padding: 10px 12px;
    padding-left: 35px;
    border-radius: 8px;
    border: 1.5px solid var(--bd);
    background: var(--sf2);
    color: var(--text);
    font-family: var(--font);
    font-size: 0.85rem;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    color-scheme: inherit;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: left 10px center;
    background-size: 15px;
    transition: all 0.2s ease;
    outline: 0;
}
.arvan-select:hover {
    border-color: var(--bds);
}
.arvan-select:focus {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px var(--acs);
}
.arvan-input {
    width: 100%;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1.5px solid var(--bd);
    background: var(--sf2);
    color: var(--text);
    font-family: var(--font);
    font-size: 0.85rem;
    transition: all 0.2s ease;
    outline: 0;
}
.arvan-input::placeholder {
    color: var(--dim);
}
.arvan-input:hover {
    border-color: var(--bds);
}
.arvan-input:focus {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px var(--acs);
}
@media (max-width: 768px) {
    .arvan-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .field:not(.toggle-field) {
        grid-column: 1 / -1;
    }
}
@media (max-width: 480px) {
    .arvan-grid {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .field:not(.toggle-field) {
        grid-column: 1 / -1;
    }
    .toggle-field {
        flex-direction: row;
        padding: 12px 14px;
        gap: 8px;
    }
    .toggle-label {
        font-size: 0.8rem;
    }
    .toggle-state {
        font-size: 0.68rem;
    }
}
@media (max-width: 600px) {
    .arvan-main-tabs, .arvan-sub-tabs {
        -ms-overflow-style: none; /* IE and Edge */
        scrollbar-width: none; /* Firefox */
    }
    .arvan-main-tabs::-webkit-scrollbar, .arvan-sub-tabs::-webkit-scrollbar {
        display: none; /* Chrome, Safari and Opera */
    }
    .arvan-main-tabs {
        gap: 6px;
        justify-content: space-between;
    }
    .arvan-main-tab-btn {
        padding: 8px 4px;
        font-size: 0.65rem;
        border-radius: 8px;
        flex-direction: column;
        gap: 6px;
        flex: 1;
        min-width: 0;
    }
    .arvan-main-tab-btn span {
        white-space: normal;
        line-height: 1.2;
        text-align: center;
        font-size: 0.65rem;
    }
    .arvan-main-tab-btn svg {
        width: 20px !important;
        height: 20px !important;
    }
    .arvan-sub-tabs {
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px;
        border-bottom: none;
        padding-bottom: 10px;
    }
    .arvan-sub-tab-btn {
        padding: 8px 14px;
        font-size: 0.8rem;
        background: var(--bg);
        border: 1px solid var(--bd) !important;
        border-radius: 10px;
        margin: 0;
        flex: 1 1 auto;
        text-align: center;
    }
    .arvan-sub-tab-btn.active {
        background: var(--ac);
        color: var(--btn-ac-text, #fff) !important;
        border-color: var(--ac) !important;
    }
    .card-head {
        padding: 0 10px !important;
    }
    .card-body {
        padding: 15px !important;
    }
}

/* Toggle Switch Styles */
.arvan-switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 26px;
    flex-shrink: 0;
    direction: ltr;
}
.arvan-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.arvan-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--sf3);
    transition: .3s ease;
    border-radius: 26px;
    border: 1px solid var(--bd);
}
.arvan-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background-color: #fff;
    transition: .3s ease;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
input:checked + .arvan-slider {
    background-color: var(--ac);
    border-color: var(--ac);
}
input:checked + .arvan-slider:before {
    transform: translateX(20px);
}
</style>

<div class="fade-up">
    <!-- Main Tabs -->
    <div class="arvan-main-tabs" style="display: none;">
        <?php foreach ($schema as $key => $tab_data): ?>
            <button type="button" class="arvan-main-tab-btn <?= $tab === $key ? 'active' : '' ?>" data-tab="<?= $key ?>">
                <?= icon($tab_data['icon'] ?? 'settings', 22) ?>
                <span style="font-weight: 600;"><?= $tab_data['title'] ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <form method="POST" id="settingsForm">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="current_tab" id="current_tab_input" value="<?= htmlspecialchars($tab) ?>">
        <input type="hidden" name="current_sec" id="current_sec_input" value="<?= htmlspecialchars($sec) ?>">
        
        <?php foreach ($schema as $key => $tab_data): ?>
            <div class="arvan-tab-content" id="tab-content-<?= $key ?>" style="display: <?= $tab === $key ? 'block' : 'none' ?>;">
                
                <div class="arvan-tab-card">
                    <!-- Sidebar Sub Tabs -->
                    <div class="arvan-sidebar">
                        <div class="arvan-sub-tabs">
                            <?php $isFirstSec = true; foreach ($tab_data['sections'] as $section_title => $fields): ?>
                                <?php 
                                    $isActiveSec = false;
                                    if ($tab === $key && $sec === $section_title) $isActiveSec = true;
                                    elseif ($tab !== $key && $isFirstSec && $sec === '') $isActiveSec = true;
                                    elseif ($tab !== $key && $isFirstSec && !isset($schema[$key]['sections'][$sec])) $isActiveSec = true;
                                ?>
                                <button type="button" class="arvan-sub-tab-btn <?= $isActiveSec ? 'active' : '' ?>" data-tab="<?= $key ?>" data-sec="<?= htmlspecialchars($section_title) ?>">
                                    <?= htmlspecialchars($section_title) ?>
                                </button>
                            <?php $isFirstSec = false; endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="arvan-content-area">
                        <?php $isFirstSec = true; foreach ($tab_data['sections'] as $section_title => $fields): ?>
                            <?php 
                                $isActiveSec = false;
                                if ($tab === $key && $sec === $section_title) $isActiveSec = true;
                                elseif ($tab !== $key && $isFirstSec && $sec === '') $isActiveSec = true;
                                elseif ($tab !== $key && $isFirstSec && !isset($schema[$key]['sections'][$sec])) $isActiveSec = true;
                            ?>
                            <div class="arvan-section-content" data-tab="<?= $key ?>" data-sec="<?= htmlspecialchars($section_title) ?>" style="display: <?= $isActiveSec ? 'block' : 'none' ?>;">
                                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; color: var(--text);"><?= htmlspecialchars($section_title) ?></h3>
                                <div class="arvan-grid">
                                    <?php foreach($fields as $f): ?>
                                        <?php if($f['type'] === 'select'): 
                                            $keys = array_keys($f['options']);
                                            $val1 = $keys[0]; 
                                            $val2 = $keys[1]; 
                                            $currentVal = (strval($f['val']) === strval($val1)) ? $val1 : $val2;
                                            $isChecked = ($currentVal === $val1);
                                        ?>
                                            <div class="field toggle-field">
                                                <div class="toggle-texts">
                                                    <label class="toggle-label" for="chk_<?= $f['name'] ?>"><?= $f['label'] ?></label>
                                                    <span class="toggle-state"><?= $isChecked ? $f['options'][$val1] : $f['options'][$val2] ?></span>
                                                </div>
                                                <input type="hidden" name="<?= $f['name'] ?>" id="hidden_<?= $f['name'] ?>" value="<?= htmlspecialchars($currentVal) ?>">
                                                <label class="arvan-switch">
                                                    <input type="checkbox" id="chk_<?= $f['name'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="document.getElementById('hidden_<?= $f['name'] ?>').value = this.checked ? '<?= $val1 ?>' : '<?= $val2 ?>'; this.closest('.toggle-field').querySelector('.toggle-state').innerText = this.checked ? '<?= $f['options'][$val1] ?>' : '<?= $f['options'][$val2] ?>';">
                                                    <span class="arvan-slider"></span>
                                                </label>
                                            </div>
                                        <?php elseif($f['type'] === 'text' || $f['type'] === 'number'): ?>
                                            <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                                                <label class="field" style="font-weight: 600; color: var(--text2); font-size: 0.78rem;"><?= htmlspecialchars($f['label']) ?></label>
                                                <input type="<?= $f['type'] ?>" name="<?= $f['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($f['val'] ?? '') ?>" placeholder="<?= htmlspecialchars($f['placeholder'] ?? '') ?>">
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php $isFirstSec = false; endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 35px; font-size: 1rem; border-radius: 8px; display:flex; align-items:center; gap:8px;">
                <?= icon('check', 18) ?> ذخیره تغییرات
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const mainTabs = document.querySelectorAll('.arvan-main-tab-btn');
    const subTabs = document.querySelectorAll('.arvan-sub-tab-btn');
    const tabContents = document.querySelectorAll('.arvan-tab-content');
    const secContents = document.querySelectorAll('.arvan-section-content');
    
    mainTabs.forEach(btn => {
        btn.onclick = function() {
            mainTabs.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const targetTab = this.getAttribute('data-tab');
            document.getElementById('current_tab_input').value = targetTab;
            
            tabContents.forEach(c => c.style.display = 'none');
            const targetContent = document.getElementById('tab-content-' + targetTab);
            if(targetContent) targetContent.style.display = 'block';
            
            const targetSubTabs = document.querySelectorAll(`.arvan-sub-tab-btn[data-tab="${targetTab}"]`);
            if (targetSubTabs.length > 0) {
                const activeSubTab = Array.from(targetSubTabs).find(st => st.classList.contains('active'));
                if (!activeSubTab) {
                    targetSubTabs[0].click();
                } else {
                    document.getElementById('current_sec_input').value = activeSubTab.getAttribute('data-sec');
                }
            }
        };
    });
    
    subTabs.forEach(btn => {
        btn.onclick = function() {
            const targetTab = this.getAttribute('data-tab');
            const targetSec = this.getAttribute('data-sec');
            
            const siblingTabs = document.querySelectorAll(`.arvan-sub-tab-btn[data-tab="${targetTab}"]`);
            siblingTabs.forEach(b => b.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById('current_sec_input').value = targetSec;
            
            const siblingContents = document.querySelectorAll(`.arvan-section-content[data-tab="${targetTab}"]`);
            siblingContents.forEach(c => c.style.display = 'none');
            
            const targetContent = Array.from(siblingContents).find(c => c.getAttribute('data-sec') === targetSec);
            if (targetContent) {
                targetContent.style.display = 'block';
            }
        };
    });
})();
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>

