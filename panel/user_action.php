<?php
require_once __DIR__ . '/inc/config.php';
require_auth();
csrf_check_get();

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

$allowed_back = ['users.php', 'user.php'];
$rawBack = $_GET['back'] ?? '';
$back = 'users.php'; 
foreach ($allowed_back as $allowed) {
    if (strpos($rawBack, $allowed) === 0) {
        
        $base = explode('?', $rawBack)[0];
        $back = $base . ($id ? "?id=$id" : '');
        break;
    }
}

if ($rawBack === 'users.php') $back = 'users.php';

if (!$id) {
    flash('error', $textbotlang['panel']['userActionInvalidUserId']);
    header('Location: users.php'); exit;
}

$user = db_fetch($pdo, "SELECT * FROM user WHERE id = ?", [$id]);
if (!$user) {
    flash('error', $textbotlang['panel']['userActionUserNotFound']);
    header('Location: users.php'); exit;
}

switch ($action) {
    case 'block':
        if ($user['User_Status'] === 'block') {
            flash('warning', $textbotlang['panel']['userActionUserAlreadyBlocked']);
        } else {
            db_query($pdo, "UPDATE user SET User_Status = 'block' WHERE id = ?", [$id]);
            flash('success', sprintf($textbotlang['panel']['userActionUserBlockedSuccess'], $id));
        }
        break;

    case 'unblock':
        if ($user['User_Status'] !== 'block') {
            flash('warning', $textbotlang['panel']['userActionUserIsActive']);
        } else {
            db_query($pdo, "UPDATE user SET User_Status = 'active' WHERE id = ?", [$id]);
            flash('success', sprintf($textbotlang['panel']['userActionUserUnblockedSuccess'], $id));
        }
        break;

    case 'zerobalance':
        db_query($pdo, "UPDATE user SET Balance = 0 WHERE id = ?", [$id]);
        $textkam = sprintf($textbotlang['Admin']['adminphp']['err_user_balance_amount_2'] ?? "❌ مبلغ %s تومان از موجودی شما کسر شد.", "کامل");
        telegram('sendMessage', [
            'chat_id' => $id,
            'text' => "❌ <b>کسر موجودی</b>\n\nموجودی کیف پول شما توسط مدیریت صفر شد.\n💰 موجودی فعلی شما: <b>0</b> تومان",
            'parse_mode' => 'HTML'
        ]);
        flash('success', 'موجودی کاربر صفر شد.');
        break;

    case 'toggle_verify':
        $current = $user['verify'] ?? '1';
        $new = ($current === '1') ? '0' : '1';
        db_query($pdo, "UPDATE user SET verify = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت احراز هویت تغییر کرد.');
        break;

    case 'confirm_phone':
        db_query($pdo, "UPDATE user SET number = 'confrim number by admin' WHERE id = ?", [$id]);
        flash('success', 'شماره تلفن با موفقیت توسط ادمین تایید شد.');
        break;

    case 'toggle_card':
        $current = $user['cardpayment'] ?? '1';
        $new = ($current === '1') ? '0' : '1';
        db_query($pdo, "UPDATE user SET cardpayment = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت نمایش شماره کارت تغییر کرد.');
        break;

    case 'verify_channel':
        $current = $user['joinchannel'] ?? '0';
        $new = ($current === '1') ? '0' : '1';
        db_query($pdo, "UPDATE user SET joinchannel = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت جوین اجباری تغییر کرد.');
        break;

    case 'toggle_cron':
        $current = $user['status_cron'] ?? '1';
        $new = ($current === '1') ? '0' : '1';
        db_query($pdo, "UPDATE user SET status_cron = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت کرون‌جاب کاربر تغییر کرد.');
        break;

    case 'removeaffiliates':
        db_query($pdo, "UPDATE user SET affiliates = '0' WHERE affiliates = ?", [$id]);
        db_query($pdo, "UPDATE user SET affiliatescount = 0 WHERE id = ?", [$id]);
        flash('success', 'زیرمجموعه‌های این کاربر حذف شدند.');
        break;

    case 'remove_single_affiliate':
        $parent_id = intval($user['affiliates'] ?? 0);
        if ($parent_id > 0) {
            try {
                $pdo->beginTransaction();
                db_query($pdo, "UPDATE user SET affiliates = '0' WHERE id = ?", [$id]);
                db_query($pdo, "UPDATE user SET affiliatescount = GREATEST(0, CAST(affiliatescount AS SIGNED) - 1) WHERE id = ?", [$parent_id]);
                $pdo->commit();
                flash('success', 'کاربر با موفقیت از لیست زیرمجموعه‌ها خارج شد.');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('remove_single_affiliate error: ' . $e->getMessage());
                flash('error', 'خطا در لغو عضویت زیرمجموعه.');
            }
        } else {
            flash('warning', 'این کاربر زیرمجموعه کسی نیست.');
        }
        break;

    case 'toggle_bot':
        // Clear or set bot type restriction (you might need to adjust based on exact bot logic)
        $current = $user['bottype'] ?? '0';
        $new = ($current === '0') ? '1' : '0';
        db_query($pdo, "UPDATE user SET bottype = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت ربات کاربر تغییر کرد.');
        break;

    case 'set_vol_price':
        $price = intval($_POST['price'] ?? 0);
        $bot_info_row = db_fetch($pdo, "SELECT setting FROM botsaz WHERE id_user = ?", [$id]);
        if ($bot_info_row) {
            $bot_info = json_decode($bot_info_row['setting'], true) ?: [];
            $bot_info['minpricevolume'] = $price;
            db_query($pdo, "UPDATE botsaz SET setting = ? WHERE id_user = ?", [json_encode($bot_info), $id]);
            flash('success', 'قیمت پایه حجم با موفقیت تغییر کرد.');
        } else {
            flash('error', 'کاربر هنوز ربات فعالی ندارد.');
        }
        break;

    case 'set_time_price':
        $price = intval($_POST['price'] ?? 0);
        $bot_info_row = db_fetch($pdo, "SELECT setting FROM botsaz WHERE id_user = ?", [$id]);
        if ($bot_info_row) {
            $bot_info = json_decode($bot_info_row['setting'], true) ?: [];
            $bot_info['minpricetime'] = $price;
            db_query($pdo, "UPDATE botsaz SET setting = ? WHERE id_user = ?", [json_encode($bot_info), $id]);
            flash('success', 'قیمت پایه زمان با موفقیت تغییر کرد.');
        } else {
            flash('error', 'کاربر هنوز ربات فعالی ندارد.');
        }
        break;

    case 'set_hide_panel':
        $panel_name = trim($_POST['panel_name'] ?? '');
        if (empty($panel_name)) {
            flash('error', 'نام پنل نباید خالی باشد.');
            break;
        }
        $bot_info_row = db_fetch($pdo, "SELECT hide_panel FROM botsaz WHERE id_user = ?", [$id]);
        if ($bot_info_row) {
            $hidden = json_decode($bot_info_row['hide_panel'], true);
            if (!is_array($hidden)) $hidden = [];
            
            $pos = array_search($panel_name, $hidden);
            if ($pos !== false) {
                unset($hidden[$pos]);
                $hidden = array_values($hidden);
                flash('success', 'پنل از حالت مخفی خارج شد.');
            } else {
                $hidden[] = $panel_name;
                flash('success', 'پنل با موفقیت مخفی شد.');
            }
            db_query($pdo, "UPDATE botsaz SET hide_panel = ? WHERE id_user = ?", [json_encode($hidden), $id]);
        } else {
            flash('error', 'کاربر هنوز ربات فعالی ندارد.');
        }
        break;

    case 'removeservice':
        $id_invoice = $_GET['id_invoice'] ?? '';
        if (!$id_invoice) {
            flash('error', 'شناسه سرویس ارسال نشده است.');
            break;
        }
        $invoice = db_fetch($pdo, "SELECT * FROM invoice WHERE id_invoice = ? AND id_user = ?", [$id_invoice, $id]);
        if (!$invoice) {
            flash('error', 'سرویس یافت نشد.');
            break;
        }
        $old_cwd = getcwd();
        chdir(__DIR__ . '/../');
        require_once 'panels.php';
        chdir($old_cwd);

        $ManagePanel = new ManagePanel();
        $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        
        $warning = '';
        if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
            $warning = ' (اما کاربر در سرور یافت نشد)';
        } elseif (isset($DataUserOut['status']) && $DataUserOut['status'] == "Unsuccessful") {
            $err_msg = !empty($DataUserOut['msg']) ? (is_string($DataUserOut['msg']) ? $DataUserOut['msg'] : json_encode($DataUserOut['msg'])) : "";
            $warning = ' (سرور در دسترس نبود یا خطا داشت: ' . htmlspecialchars($err_msg) . ')';
        }

        $ManagePanel->RemoveUser($invoice['Service_location'], $invoice['username']);
        db_query($pdo, "UPDATE invoice SET Status = 'removebyadmin' WHERE id_invoice = ?", [$id_invoice]);
        flash('success', 'سرویس کاربر با موفقیت حذف شد.' . $warning);
        break;

    case 'removeserviceandrefund':
        $id_invoice = $_GET['id_invoice'] ?? '';
        if (!$id_invoice) {
            flash('error', 'شناسه سرویس ارسال نشده است.');
            break;
        }
        $invoice = db_fetch($pdo, "SELECT * FROM invoice WHERE id_invoice = ? AND id_user = ?", [$id_invoice, $id]);
        if (!$invoice) {
            flash('error', 'سرویس یافت نشد.');
            break;
        }
        if ($invoice['Status'] === 'removebyadmin') {
            flash('warning', 'این سرویس قبلاً حذف شده است.');
            break;
        }
        $old_cwd = getcwd();
        chdir(__DIR__ . '/../');
        require_once 'panels.php';
        require_once 'botapi.php';
        chdir($old_cwd);

        $ManagePanel = new ManagePanel();
        $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        
        $warning = '';
        if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
            $warning = ' (اما کاربر در سرور یافت نشد)';
        } elseif (isset($DataUserOut['status']) && $DataUserOut['status'] == "Unsuccessful") {
            $err_msg = !empty($DataUserOut['msg']) ? (is_string($DataUserOut['msg']) ? $DataUserOut['msg'] : json_encode($DataUserOut['msg'])) : "";
            $warning = ' (سرور در دسترس نبود یا خطا داشت: ' . htmlspecialchars($err_msg) . ')';
        }

        $ManagePanel->RemoveUser($invoice['Service_location'], $invoice['username']);
        db_query($pdo, "UPDATE invoice SET Status = 'removebyadmin' WHERE id_invoice = ?", [$id_invoice]);
        
        $price = (int)$invoice['price_product'];
        db_query($pdo, "UPDATE user SET Balance = Balance + ? WHERE id = ?", [$price, $id]);
        
        $msg = sprintf($textbotlang['Admin']['adminphp']['msg_user_balance_amount_add_2'] ?? "مبلغ %s تومان به حساب شما افزوده شد.", number_format($price));
        if (function_exists('sendmessage')) {
            sendmessage($id, $msg, null, 'HTML');
        } elseif (function_exists('telegram')) {
            telegram('sendMessage', ['chat_id' => $id, 'text' => $msg, 'parse_mode' => 'HTML']);
        }
        
        flash('success', 'سرویس حذف شد و مبلغ ' . number_format($price) . ' تومان به حساب کاربر برگشت داده شد.' . $warning);
        break;

    case 'extendservice':
        $id_invoice = $_GET['id_invoice'] ?? '';
        if (!$id_invoice) {
            flash('error', 'شناسه سرویس ارسال نشده است.');
            break;
        }
        $invoice = db_fetch($pdo, "SELECT * FROM invoice WHERE id_invoice = ? AND id_user = ?", [$id_invoice, $id]);
        if (!$invoice) {
            flash('error', 'سرویس یافت نشد.');
            break;
        }
        $old_cwd = getcwd();
        chdir(__DIR__ . '/../');
        require_once 'panels.php';
        chdir($old_cwd);

        $marzban_panel = db_fetch($pdo, "SELECT * FROM marzban_panel WHERE name_panel = ?", [$invoice['Service_location']]);
        if (!$marzban_panel) {
            flash('error', 'پنل مربوط به این سرویس یافت نشد.');
            break;
        }

        $ManagePanel = new ManagePanel();
        $extend = $ManagePanel->extend($marzban_panel['Methodextend'], $invoice['Volume'], $invoice['Service_time'], $invoice['username'], "custom_volume", $marzban_panel['code_panel']);
        
        if ($extend['status'] == false) {
            $err_msg = is_string($extend['msg']) ? $extend['msg'] : json_encode($extend['msg']);
            flash('error', 'خطا در تمدید سرویس در سرور: ' . htmlspecialchars($err_msg));
            break;
        }

        db_query($pdo, "INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $id,
            $invoice['username'],
            $invoice['Volume'] . "_" . $invoice['Service_time'],
            "extend_user_by_admin",
            date('Y-m-d H:i:s'),
            0,
            ""
        ]);

        flash('success', 'سرویس کاربر با موفقیت تمدید شد.');
        break;

    case 'toggle_status':
        $id_invoice = $_GET['id_invoice'] ?? '';
        if (!$id_invoice) {
            flash('error', 'شناسه سرویس ارسال نشده است.');
            break;
        }
        $invoice = db_fetch($pdo, "SELECT * FROM invoice WHERE id_invoice = ? AND id_user = ?", [$id_invoice, $id]);
        if (!$invoice) {
            flash('error', 'سرویس یافت نشد.');
            break;
        }
        $old_cwd = getcwd();
        chdir(__DIR__ . '/../');
        require_once 'panels.php';
        chdir($old_cwd);

        $ManagePanel = new ManagePanel();
        $dataoutput = $ManagePanel->Change_status($invoice['username'], $invoice['Service_location']);
        if (isset($dataoutput['status']) && $dataoutput['status'] == "Unsuccessful") {
            $err_msg = !empty($dataoutput['msg']) ? (is_string($dataoutput['msg']) ? $dataoutput['msg'] : json_encode($dataoutput['msg'])) : "";
            flash('error', 'خطا در تغییر وضعیت اکانت در سرور: ' . htmlspecialchars($err_msg));
            break;
        }

        $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (isset($DataUserOut['status']) && $DataUserOut['status'] == "active") {
            db_query($pdo, "UPDATE invoice SET Status = 'active' WHERE id_invoice = ?", [$id_invoice]);
            flash('success', 'اکانت با موفقیت روشن (فعال) شد.');
        } else {
            db_query($pdo, "UPDATE invoice SET Status = 'disablebyadmin' WHERE id_invoice = ?", [$id_invoice]);
            flash('success', 'اکانت با موفقیت خاموش (غیرفعال) شد.');
        }
        break;

    default:
        flash('error', $textbotlang['panel']['userActionInvalidOperation']);
}

header("Location: $back"); exit;

