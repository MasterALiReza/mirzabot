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
        $pay_settings[$r['NamePay']] = $r['ValuePay'];
    }
} catch (Exception $e) {}

$shop_settings = [];
try {
    $stmt = $pdo->query("SELECT Namevalue, value FROM shopSetting");
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $shop_settings[$r['Namevalue']] = $r['value'];
    }
} catch (Exception $e) {}

$schema = [
    'general' => [
        'title' => 'عمومی',
        'icon' => 'settings',
        'sections' => [
            'وضعیت و دسترسی' => [
                ['name' => 'set_Bot_Status', 'label' => 'وضعیت ربات', 'type' => 'select', 'options' => ['botstatuson' => 'روشن', 'botstatusoff' => 'خاموش'], 'val' => $row['Bot_Status'] ?? ''],
                ['name' => 'set_roll_Status', 'label' => 'تایید قوانین', 'type' => 'select', 'options' => ['rolleon' => 'اجباری', 'rolleoff' => 'اختیاری'], 'val' => $row['roll_Status'] ?? ''],
                ['name' => 'set_NotUser', 'label' => 'قفل برای غیرکاربران', 'type' => 'select', 'options' => ['onnotuser' => 'فعال', 'offnotuser' => 'غیرفعال'], 'val' => $row['NotUser'] ?? ''],
                ['name' => 'set_statusnewuser', 'label' => 'وضعیت کاربران جدید', 'type' => 'select', 'options' => ['onnewuser' => 'آزاد', 'offnewuser' => 'بسته'], 'val' => $row['statusnewuser'] ?? ''],
                ['name' => 'set_verifystart', 'label' => 'تاییدیه شروع کار', 'type' => 'select', 'options' => ['onverify' => 'فعال', 'offverify' => 'غیرفعال'], 'val' => $row['verifystart'] ?? ''],
                ['name' => 'set_verifybucodeuser', 'label' => 'تاییدیه پیامکی (کد)', 'type' => 'select', 'options' => ['onverify' => 'فعال', 'offverify' => 'غیرفعال'], 'val' => $row['verifybucodeuser'] ?? ''],
                ['name' => 'set_get_number', 'label' => 'دریافت شماره تماس', 'type' => 'select', 'options' => ['onAuthenticationphone' => 'اجباری', 'offAuthenticationphone' => 'اختیاری/خاموش'], 'val' => $row['get_number'] ?? ''],
                ['name' => 'set_iran_number', 'label' => 'فقط شماره ایران', 'type' => 'select', 'options' => ['onAuthenticationiran' => 'بله', 'offAuthenticationiran' => 'خیر'], 'val' => $row['iran_number'] ?? ''],
                ['name' => 'set_timeauto_not_verify', 'label' => 'زمان حذف تایید نشده (روز)', 'type' => 'number', 'val' => $row['timeauto_not_verify'] ?? '4'],
            ],
            'گزارشات و ارتباطات' => [
                ['name' => 'set_Channel_Report', 'label' => 'آیدی کانال گزارشات', 'type' => 'text', 'placeholder' => '-100xxxxxxxxx', 'val' => $row['Channel_Report'] ?? ''],
                ['name' => 'set_id_support', 'label' => 'آیدی پشتیبانی', 'type' => 'text', 'placeholder' => '123456789', 'val' => $row['id_support'] ?? ''],
                ['name' => 'set_statussupportpv', 'label' => 'پشتیبانی PV', 'type' => 'select', 'options' => ['onpvsupport' => 'فعال', 'offpvsupport' => 'غیرفعال'], 'val' => $row['statussupportpv'] ?? ''],
                ['name' => 'set_categoryhelp', 'label' => 'راهنما در دسته‌بندی‌ها', 'type' => 'select', 'options' => ['1' => 'نمایش', '0' => 'عدم نمایش'], 'val' => $row['categoryhelp'] ?? ''],
                ['name' => 'set_linkappstatus', 'label' => 'لینک اپلیکیشن در منو', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['linkappstatus'] ?? ''],
                ['name' => 'set_statusnoteforf', 'label' => 'یادداشت اجباری در خرید', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['statusnoteforf'] ?? ''],
            ],
            'کاربر و سرویس' => [
                ['name' => 'set_limit_usertest_all', 'label' => 'تعداد تست مجاز هر کاربر', 'type' => 'number', 'val' => $row['limit_usertest_all'] ?? ''],
                ['name' => 'set_removedayc', 'label' => 'روزهای نگهداری سرویس حذف شده', 'type' => 'number', 'val' => $row['removedayc'] ?? ''],
                ['name' => 'set_on_hold_day', 'label' => 'روزهای مسدودی موقت', 'type' => 'number', 'val' => $row['on_hold_day'] ?? ''],
                ['name' => 'set_cronvolumere', 'label' => 'بررسی حجم کرون‌جاب (ساعت)', 'type' => 'number', 'val' => $row['cronvolumere'] ?? ''],
                ['name' => 'set_daywarn', 'label' => 'هشدار پایان سرویس (روز)', 'type' => 'number', 'val' => $row['daywarn'] ?? ''],
                ['name' => 'set_volumewarn', 'label' => 'هشدار پایان حجم (گیگابایت)', 'type' => 'number', 'val' => $row['volumewarn'] ?? ''],
                ['name' => 'set_statusnamecustom', 'label' => 'نام سفارشی سرویس‌ها', 'type' => 'select', 'options' => ['onnamecustom' => 'فعال', 'offnamecustom' => 'غیرفعال'], 'val' => $row['statusnamecustom'] ?? ''],
            ],
            'ظاهر و منوها' => [
                ['name' => 'set_inlinebtnmain', 'label' => 'دکمه‌های شیشه‌ای منوی اصلی', 'type' => 'select', 'options' => ['oninline' => 'روشن', 'offinline' => 'خاموش'], 'val' => $row['inlinebtnmain'] ?? ''],
                ['name' => 'set_btn_status_extned', 'label' => 'دکمه تمدید سرویس', 'type' => 'select', 'options' => ['1' => 'روشن', '0' => 'خاموش'], 'val' => $row['btn_status_extned'] ?? ''],
                ['name' => 'set_status_keyboard_config', 'label' => 'کیبورد تنظیمات کانفیگ', 'type' => 'select', 'options' => ['1' => 'روشن', '0' => 'خاموش'], 'val' => $row['status_keyboard_config'] ?? ''],
            ]
        ]
    ],
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
            ]
        ]
    ],
    'financial' => [
        'title' => 'مالی و درگاه‌ها',
        'icon' => 'card',
        'sections' => [
            'تنظیمات کلی مالی' => [
                ['name' => 'pay_minbalance', 'label' => 'حداقل مبلغ پرداخت (عمومی)', 'type' => 'number', 'val' => $pay_settings['minbalance'] ?? ''],
                ['name' => 'pay_maxbalance', 'label' => 'حداکثر مبلغ پرداخت (عمومی)', 'type' => 'number', 'val' => $pay_settings['maxbalance'] ?? ''],
                ['name' => 'pay_minbalancepaynotverify', 'label' => 'حداقل پرداخت (احراز نشده)', 'type' => 'number', 'val' => $pay_settings['minbalancepaynotverify'] ?? ''],
                ['name' => 'pay_maxbalancepaynotverify', 'label' => 'حداکثر پرداخت (احراز نشده)', 'type' => 'number', 'val' => $pay_settings['maxbalancepaynotverify'] ?? ''],
                ['name' => 'set_showcard', 'label' => 'نمایش شماره کارت در ربات', 'type' => 'select', 'options' => ['1' => 'بله', '0' => 'خیر'], 'val' => $row['showcard'] ?? ''],
                ['name' => 'set_statuscopycart', 'label' => 'قابلیت کپی شماره کارت', 'type' => 'select', 'options' => ['1' => 'بله', '0' => 'خیر'], 'val' => $row['statuscopycart'] ?? ''],
            ],
            'کارت به کارت' => [
                ['name' => 'pay_Cartstatus', 'label' => 'کارت به کارت عمومی', 'type' => 'select', 'options' => ['oncard' => 'روشن', 'offcard' => 'خاموش'], 'val' => $pay_settings['Cartstatus'] ?? ''],
                ['name' => 'pay_Cartstatuspv', 'label' => 'کارت به کارت در PV', 'type' => 'select', 'options' => ['oncardpv' => 'روشن', 'offcardpv' => 'خاموش'], 'val' => $pay_settings['Cartstatuspv'] ?? ''],
                ['name' => 'pay_cardnumber', 'label' => 'شماره کارت', 'type' => 'text', 'val' => $pay_settings['cardnumber'] ?? ''],
                ['name' => 'pay_namecard', 'label' => 'نام صاحب کارت', 'type' => 'text', 'val' => $pay_settings['namecard'] ?? ''],
                ['name' => 'pay_minbalancecart', 'label' => 'حداقل مبلغ شارژ', 'type' => 'number', 'val' => $pay_settings['minbalancecart'] ?? ''],
                ['name' => 'pay_maxbalancecart', 'label' => 'حداکثر مبلغ شارژ', 'type' => 'number', 'val' => $pay_settings['maxbalancecart'] ?? ''],
                ['name' => 'pay_chashbackcart', 'label' => 'درصد کش‌بک شارژ', 'type' => 'number', 'val' => $pay_settings['chashbackcart'] ?? '0'],
                ['name' => 'pay_statuscardautoconfirm', 'label' => 'تایید خودکار کارت به کارت', 'type' => 'select', 'options' => ['onautoconfirm' => 'روشن', 'offautoconfirm' => 'خاموش'], 'val' => $pay_settings['statuscardautoconfirm'] ?? ''],
                ['name' => 'pay_autoconfirmcart', 'label' => 'ربات تاییدگر کارت', 'type' => 'select', 'options' => ['onauto' => 'روشن', 'offauto' => 'خاموش'], 'val' => $pay_settings['autoconfirmcart'] ?? ''],
                ['name' => 'pay_checkpaycartfirst', 'label' => 'بررسی تراکنش اول', 'type' => 'select', 'options' => ['onpayverify' => 'روشن', 'offpayverify' => 'خاموش'], 'val' => $pay_settings['checkpaycartfirst'] ?? ''],
            ],
            'درگاه زرین‌پال' => [
                ['name' => 'pay_zarinpalstatus', 'label' => 'وضعیت زرین‌پال', 'type' => 'select', 'options' => ['onzarinpal' => 'روشن', 'offzarinpal' => 'خاموش'], 'val' => $pay_settings['zarinpalstatus'] ?? ''],
                ['name' => 'pay_merchant_zarinpal', 'label' => 'مرچنت زرین‌پال', 'type' => 'text', 'val' => $pay_settings['merchant_zarinpal'] ?? ''],
                ['name' => 'pay_minbalancezarinpal', 'label' => 'حداقل مبلغ', 'type' => 'number', 'val' => $pay_settings['minbalancezarinpal'] ?? ''],
                ['name' => 'pay_maxbalancezarinpal', 'label' => 'حداکثر مبلغ', 'type' => 'number', 'val' => $pay_settings['maxbalancezarinpal'] ?? ''],
                ['name' => 'pay_chashbackzarinpal', 'label' => 'درصد کش‌بک', 'type' => 'number', 'val' => $pay_settings['chashbackzarinpal'] ?? '0'],
            ],
            'درگاه NowPayment' => [
                ['name' => 'pay_nowpaymentstatus', 'label' => 'وضعیت NowPayment', 'type' => 'select', 'options' => ['onnowpayment' => 'روشن', 'offnowpayment' => 'خاموش'], 'val' => $pay_settings['nowpaymentstatus'] ?? ''],
                ['name' => 'pay_apinowpayment', 'label' => 'API Key', 'type' => 'text', 'val' => $pay_settings['apinowpayment'] ?? ''],
                ['name' => 'pay_minbalancenowpayment', 'label' => 'حداقل مبلغ', 'type' => 'number', 'val' => $pay_settings['minbalancenowpayment'] ?? ''],
                ['name' => 'pay_maxbalancenowpayment', 'label' => 'حداکثر مبلغ', 'type' => 'number', 'val' => $pay_settings['maxbalancenowpayment'] ?? ''],
                ['name' => 'pay_cashbacknowpayment', 'label' => 'درصد کش‌بک', 'type' => 'number', 'val' => $pay_settings['cashbacknowpayment'] ?? '0'],
            ],
            'درگاه آقای پرداخت' => [
                ['name' => 'pay_statusaqayepardakht', 'label' => 'وضعیت آقای پرداخت', 'type' => 'select', 'options' => ['onaqayepardakht' => 'روشن', 'offaqayepardakht' => 'خاموش'], 'val' => $pay_settings['statusaqayepardakht'] ?? ''],
                ['name' => 'pay_merchant_id_aqayepardakht', 'label' => 'مرچنت آیدی', 'type' => 'text', 'val' => $pay_settings['merchant_id_aqayepardakht'] ?? ''],
                ['name' => 'pay_minbalanceaqayepardakht', 'label' => 'حداقل مبلغ', 'type' => 'number', 'val' => $pay_settings['minbalanceaqayepardakht'] ?? ''],
                ['name' => 'pay_maxbalanceaqayepardakht', 'label' => 'حداکثر مبلغ', 'type' => 'number', 'val' => $pay_settings['maxbalanceaqayepardakht'] ?? ''],
                ['name' => 'pay_chashbackaqaypardokht', 'label' => 'درصد کش‌بک', 'type' => 'number', 'val' => $pay_settings['chashbackaqaypardokht'] ?? '0'],
            ],
            'درگاه ایران پی 3' => [
                ['name' => 'pay_statusiranpay3', 'label' => 'وضعیت ایران پی 3', 'type' => 'select', 'options' => ['oniranpay3' => 'روشن', 'offiranpay3' => 'خاموش'], 'val' => $pay_settings['statusiranpay3'] ?? ''],
                ['name' => 'pay_apiiranpay', 'label' => 'API Key', 'type' => 'text', 'val' => $pay_settings['apiiranpay'] ?? ''],
                ['name' => 'pay_minbalanceiranpay', 'label' => 'حداقل مبلغ', 'type' => 'number', 'val' => $pay_settings['minbalanceiranpay'] ?? ''],
                ['name' => 'pay_maxbalanceiranpay', 'label' => 'حداکثر مبلغ', 'type' => 'number', 'val' => $pay_settings['maxbalanceiranpay'] ?? ''],
                ['name' => 'pay_chashbackiranpay3', 'label' => 'درصد کش‌بک', 'type' => 'number', 'val' => $pay_settings['chashbackiranpay3'] ?? '0'],
            ],
            'ترون و ارز دیجیتال' => [
                ['name' => 'pay_digistatus', 'label' => 'وضعیت درگاه کریپتو', 'type' => 'select', 'options' => ['ondigi' => 'روشن', 'offdigi' => 'خاموش'], 'val' => $pay_settings['digistatus'] ?? ''],
                ['name' => 'pay_marchent_tronseller', 'label' => 'مرچنت TronSeller', 'type' => 'text', 'val' => $pay_settings['marchent_tronseller'] ?? ''],
                ['name' => 'pay_urlpaymenttron', 'label' => 'آدرس API TronSeller', 'type' => 'text', 'val' => $pay_settings['urlpaymenttron'] ?? ''],
                ['name' => 'pay_walletaddress', 'label' => 'آدرس کیف پول TRC20', 'type' => 'text', 'val' => $pay_settings['walletaddress'] ?? ''],
                ['name' => 'pay_minbalancedigitaltron', 'label' => 'حداقل مبلغ کریپتو', 'type' => 'number', 'val' => $pay_settings['minbalancedigitaltron'] ?? ''],
                ['name' => 'pay_maxbalancedigitaltron', 'label' => 'حداکثر مبلغ کریپتو', 'type' => 'number', 'val' => $pay_settings['maxbalancedigitaltron'] ?? ''],
            ],
            'درگاه تلگرام استارز' => [
                ['name' => 'pay_statusstar', 'label' => 'وضعیت استارز', 'type' => 'select', 'options' => ['1' => 'روشن', '0' => 'خاموش'], 'val' => $pay_settings['statusstar'] ?? '0'],
                ['name' => 'pay_minbalancestar', 'label' => 'حداقل مبلغ', 'type' => 'number', 'val' => $pay_settings['minbalancestar'] ?? ''],
                ['name' => 'pay_maxbalancestar', 'label' => 'حداکثر مبلغ', 'type' => 'number', 'val' => $pay_settings['maxbalancestar'] ?? ''],
                ['name' => 'pay_chashbackstar', 'label' => 'درصد کش‌بک', 'type' => 'number', 'val' => $pay_settings['chashbackstar'] ?? '0'],
            ],
            'سایر درگاه‌ها و پرداخت‌ها' => [
                ['name' => 'pay_statusSwapWallet', 'label' => 'وضعیت SwapWallet / ایران پی 1', 'type' => 'select', 'options' => ['onSwapinoBot' => 'روشن', 'offnSolutions' => 'خاموش'], 'val' => $pay_settings['statusSwapWallet'] ?? ''],
                ['name' => 'pay_marchent_floypay', 'label' => 'مرچنت FloyPay', 'type' => 'text', 'val' => $pay_settings['marchent_floypay'] ?? ''],
                ['name' => 'pay_minbalanceiranpay1', 'label' => 'حداقل ایران پی 1', 'type' => 'number', 'val' => $pay_settings['minbalanceiranpay1'] ?? ''],
                ['name' => 'pay_maxbalanceiranpay1', 'label' => 'حداکثر ایران پی 1', 'type' => 'number', 'val' => $pay_settings['maxbalanceiranpay1'] ?? ''],
                ['name' => 'pay_chashbackiranpay1', 'label' => 'کش‌بک ایران پی 1', 'type' => 'number', 'val' => $pay_settings['chashbackiranpay1'] ?? '0'],
                ['name' => 'pay_statustarnado', 'label' => 'وضعیت ترنادو / ایران پی 2', 'type' => 'select', 'options' => ['onternado' => 'روشن', 'offternado' => 'خاموش'], 'val' => $pay_settings['statustarnado'] ?? ''],
                ['name' => 'pay_apiternado', 'label' => 'API Key ترنادو', 'type' => 'text', 'val' => $pay_settings['apiternado'] ?? ''],
                ['name' => 'pay_minbalanceiranpay2', 'label' => 'حداقل ترنادو', 'type' => 'number', 'val' => $pay_settings['minbalanceiranpay2'] ?? ''],
                ['name' => 'pay_maxbalanceiranpay2', 'label' => 'حداکثر ترنادو', 'type' => 'number', 'val' => $pay_settings['maxbalanceiranpay2'] ?? ''],
                ['name' => 'pay_chashbackiranpay2', 'label' => 'کش‌بک ترنادو', 'type' => 'number', 'val' => $pay_settings['chashbackiranpay2'] ?? '0'],
                ['name' => 'pay_minbalanceplisio', 'label' => 'حداقل Plisio', 'type' => 'number', 'val' => $pay_settings['minbalanceplisio'] ?? ''],
                ['name' => 'pay_maxbalanceplisio', 'label' => 'حداکثر Plisio', 'type' => 'number', 'val' => $pay_settings['maxbalanceplisio'] ?? ''],
                ['name' => 'pay_chashbackplisio', 'label' => 'کش‌بک Plisio', 'type' => 'number', 'val' => $pay_settings['chashbackplisio'] ?? '0'],
                ['name' => 'pay_minbalanceperfect', 'label' => 'حداقل Perfect Money', 'type' => 'number', 'val' => $pay_settings['minbalanceperfect'] ?? ''],
                ['name' => 'pay_maxbalanceperfect', 'label' => 'حداکثر Perfect Money', 'type' => 'number', 'val' => $pay_settings['maxbalanceperfect'] ?? ''],
                ['name' => 'pay_chashbackperfect', 'label' => 'کش‌بک Perfect Money', 'type' => 'number', 'val' => $pay_settings['chashbackperfect'] ?? '0'],
            ]
        ]
    ],
    'agents' => [
        'title' => 'نمایندگان و همکاری',
        'icon' => 'users',
        'sections' => [
            'تنظیمات نمایندگان' => [
                ['name' => 'set_statusagentrequest', 'label' => 'درخواست نمایندگی', 'type' => 'select', 'options' => ['onrequestagent' => 'باز', 'offrequestagent' => 'بسته'], 'val' => $row['statusagentrequest'] ?? ''],
                ['name' => 'set_agentreqprice', 'label' => 'حداقل شارژ برای درخواست (تومان)', 'type' => 'number', 'val' => $row['agentreqprice'] ?? ''],
            ],
            'همکاری در فروش (Affiliates)' => [
                ['name' => 'set_affiliatesstatus', 'label' => 'وضعیت همکاری در فروش', 'type' => 'select', 'options' => ['onaffiliates' => 'فعال', 'offaffiliates' => 'غیرفعال'], 'val' => $row['affiliatesstatus'] ?? ''],
                ['name' => 'set_affiliatespercentage', 'label' => 'درصد پورسانت', 'type' => 'number', 'val' => $row['affiliatespercentage'] ?? '0'],
            ]
        ]
    ],
    'gamification' => [
        'title' => 'سرگرمی و امتیاز',
        'icon' => 'star',
        'sections' => [
            'گردونه شانس' => [
                ['name' => 'set_wheelـluck', 'label' => 'وضعیت گردونه', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['wheelـluck'] ?? ''],
                ['name' => 'set_wheelـluck_price', 'label' => 'قیمت هر چرخش (تومان)', 'type' => 'number', 'val' => $row['wheelـluck_price'] ?? '0'],
                ['name' => 'set_statusfirstwheel', 'label' => 'اولین چرخش رایگان', 'type' => 'select', 'options' => ['1' => 'بله', '0' => 'خیر'], 'val' => $row['statusfirstwheel'] ?? ''],
                ['name' => 'set_wheelagent', 'label' => 'گردونه برای نمایندگان', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['wheelagent'] ?? ''],
            ],
            'سایر بازی‌ها و امتیازات' => [
                ['name' => 'set_Dice', 'label' => 'بازی تاس', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['Dice'] ?? ''],
                ['name' => 'set_scorestatus', 'label' => 'سیستم امتیازدهی', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['scorestatus'] ?? ''],
                ['name' => 'set_Lotteryagent', 'label' => 'قرعه‌کشی نمایندگان', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['Lotteryagent'] ?? ''],
            ]
        ]
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    
    $updates_setting = [];
    $params_setting = [];
    
    foreach($_POST as $key => $val) {
        if(strpos($key, 'set_') === 0) {
            $field = substr($key, 4);
            $updates_setting[] = "$field = ?";
            $params_setting[] = $val;
        } elseif(strpos($key, 'pay_') === 0) {
            $field = substr($key, 4);
            db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $field]);
        } elseif(strpos($key, 'shop_') === 0) {
            $field = substr($key, 5);
            db_query($pdo, "UPDATE shopSetting SET value = ? WHERE Namevalue = ?", [$val, $field]);
        }
    }
    
    if(!empty($updates_setting)) {
        db_query($pdo, "UPDATE setting SET " . implode(', ', $updates_setting), $params_setting);
    }

    flash('success', $textbotlang['panel']['botSettingsSuccess'] ?? 'تنظیمات با موفقیت ذخیره شد.');
    $redirect_tab = $_POST['current_tab'] ?? 'general';
    header('Location: bot_settings.php?tab=' . $redirect_tab);
    exit;
}

$tab = $_GET['tab'] ?? 'general';
if (!array_key_exists($tab, $schema)) {
    $tab = 'general';
}

$pageTitle = $textbotlang['panel']['layoutPageTitleBotSettings'] ?? 'تنظیمات ربات';
$activeNav = 'bot_settings';
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;gap:4px;margin-bottom:18px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:5px;overflow-x:auto" class="fade-up">
    <?php foreach ($schema as $key => $tab_data): ?>
        <a href="?tab=<?= $key ?>"
            style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:7px;font-size:.82rem;font-weight:600;white-space:nowrap;flex-shrink:0;transition:all .15s;text-decoration:none;
                  <?= $tab === $key ? 'background:var(--ac);color:#fff;box-shadow:0 0 14px var(--acg)' : 'color:var(--mute)' ?>">
            <?= icon($tab_data['icon'] ?? 'settings', 15) ?> <?= $tab_data['title'] ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card fade-up">
    <div class="card-head">
        <div>
            <div class="card-title"><?= icon($schema[$tab]['icon'] ?? 'settings', 18) ?> <?= $schema[$tab]['title'] ?></div>
        </div>
    </div>
    
    <div class="card-body" style="display:flex; flex-direction:column; gap:20px;">
        <?php foreach($schema[$tab]['sections'] as $section_title => $fields): ?>
            <form method="POST" style="border: 1px solid var(--bd); border-radius: 10px; overflow: hidden; background: var(--bg); box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="current_tab" value="<?= $tab ?>">
                
                <div style="background: var(--sf); padding: 12px 15px; font-weight: bold; border-bottom: 1px solid var(--bd); color: var(--fg); font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                    <div style="width: 4px; height: 16px; background: var(--ac); border-radius: 2px;"></div>
                    <?= $section_title ?>
                </div>
                <div style="padding: 15px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                    <?php foreach($fields as $f): ?>
                        <div class="field">
                            <label><?= $f['label'] ?></label>
                            <?php if($f['type'] === 'select'): ?>
                                <select name="<?= $f['name'] ?>" class="select">
                                    <?php foreach($f['options'] as $opt_val => $opt_label): ?>
                                        <option value="<?= $opt_val ?>" <?= (strval($f['val']) === strval($opt_val)) ? 'selected' : '' ?>><?= $opt_label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif($f['type'] === 'text' || $f['type'] === 'number'): ?>
                                <input type="<?= $f['type'] ?>" name="<?= $f['name'] ?>" class="input" value="<?= htmlspecialchars($f['val'] ?? '') ?>" placeholder="<?= $f['placeholder'] ?? '' ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="padding: 12px 15px; background: var(--sf); border-top: 1px solid var(--bd); display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 8px 24px; font-size: 0.9rem; border-radius: 6px;">
                        <?= icon('check', 16) ?> ذخیره تنظیمات <?= $section_title ?>
                    </button>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
