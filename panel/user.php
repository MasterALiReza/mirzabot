<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: users.php');
    exit;
}

$user = db_fetch($pdo, "SELECT * FROM user WHERE id = ?", [$id]);
if (!$user) {
    flash('error', $textbotlang['panel']['userNotFound']);
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_balance') {
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount >= 1000) {
            db_query($pdo, "UPDATE user SET Balance = Balance + ? WHERE id = ?", [$amount, $id]);
            flash('success', number_format($amount) . $textbotlang['panel']['userBalanceAddedSuffix']);
        } else {
            flash('error', $textbotlang['panel']['userMinAmountToman']);
        }
    } elseif ($action === 'set_role') {
        $newRole = $_POST['new_role'] ?? 'f';
        if (in_array($newRole, ['f', 'n', 'n2', 'all'], true)) {
            db_query($pdo, "UPDATE user SET agent = ? WHERE id = ?", [$newRole, $id]);
            flash('success', $textbotlang['panel']['userGroupChangedPrefix'] . user_role_label($newRole) . $textbotlang['panel']['userGroupChangedSuffix']);
        }
    } elseif ($action === 'deduct_balance') {
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount > 0) {
            db_query($pdo, "UPDATE user SET Balance = GREATEST(0, Balance - ?) WHERE id = ?", [$amount, $id]);
            flash('success', "مبلغ " . number_format($amount) . " تومان از حساب کاربر کسر شد.");
        } else {
            flash('error', "مبلغ نامعتبر است.");
        }
    } elseif ($action === 'set_discount') {
        $percent = (int) ($_POST['percent'] ?? 0);
        if ($percent >= 0 && $percent <= 100) {
            db_query($pdo, "UPDATE user SET pricediscount = ? WHERE id = ?", [$percent, $id]);
            flash('success', "درصد تخفیف کاربر با موفقیت روی {$percent}٪ تنظیم شد.");
        } else {
            flash('error', "درصد تخفیف نامعتبر است.");
        }
    } elseif ($action === 'send_msg') {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            telegram('SendMessage', [
                'chat_id' => $id,
                'text' => $message,
            ]);
            flash('success', "پیام شما به کاربر ارسال شد.");
        }
    } elseif ($action === 'set_test_limit') {
        $limit = (int) ($_POST['limit'] ?? 0);
        db_query($pdo, "UPDATE user SET limit_usertest = ? WHERE id = ?", [$limit, $id]);
        flash('success', "سقف اکانت تست کاربر با موفقیت تغییر کرد.");
    } elseif ($action === 'transfer_account') {
        $target_id = (int) ($_POST['target_id'] ?? 0);
        if ($target_id && $target_id != $id) {
            $target = db_fetch($pdo, "SELECT id FROM user WHERE id = ?", [$target_id]);
            if ($target) {
                $balance = $user['Balance'] ?? 0;
                db_query($pdo, "UPDATE user SET Balance = Balance + ? WHERE id = ?", [$balance, $target_id]);
                db_query($pdo, "UPDATE user SET Balance = 0 WHERE id = ?", [$id]);
                db_query($pdo, "UPDATE invoice SET id_user = ? WHERE id_user = ?", [$target_id, $id]);
                db_query($pdo, "UPDATE Payment_report SET id_user = ? WHERE id_user = ?", [$target_id, $id]);
                flash('success', "موجودی و فاکتورها به کاربر مقصد منتقل شد.");
            } else {
                flash('error', "کاربر مقصد در ربات یافت نشد.");
            }
        } else {
            flash('error', "آیدی وارد شده معتبر نیست.");
        }
    } elseif ($action === 'set_buy_cap') {
        $cap = (int) ($_POST['cap'] ?? 0);
        db_query($pdo, "UPDATE user SET maxbuyagent = ? WHERE id = ?", [$cap, $id]);
        flash('success', "سقف خرید نماینده تنظیم شد.");
    } elseif ($action === 'set_expire') {
        $expire = trim($_POST['timestamp'] ?? '');
        db_query($pdo, "UPDATE user SET expire = ? WHERE id = ?", [$expire, $id]);
        flash('success', "تاریخ انقضای نماینده تنظیم شد.");
    } elseif ($action === 'set_loc_limit') {
        $limit = (int) ($_POST['limit'] ?? 0);
        db_query($pdo, "UPDATE user SET limitchangeloc = ? WHERE id = ?", [$limit, $id]);
        flash('success', "سقف تغییر لوکیشن نماینده تنظیم شد.");
    }

    header("Location: user.php?id=$id");
    exit;
}

$invoices = [];
$payments = [];
$referrals = [];

try {
    $invoices = db_fetchAll($pdo, "SELECT * FROM invoice WHERE id_user = ? ORDER BY time_sell DESC LIMIT 30", [$id]);
} catch (Exception $e) {
}

try {
    $payments = db_fetchAll($pdo, "SELECT * FROM Payment_report WHERE id_user = ? ORDER BY time DESC LIMIT 20", [$id]);
} catch (Exception $e) {
}

try {
    $referrals = db_fetchAll($pdo, "SELECT id, username, namecustom, Balance, register, agent FROM user WHERE affiliates = ? ORDER BY register DESC LIMIT 20", [$id]);
} catch (Exception $e) {
}

$balance = (int) ($user['Balance'] ?? 0);
$totalSpent = array_sum(array_column($invoices, 'price_product'));
$activeServices = count(array_filter($invoices, fn($inv) => ($inv['Status'] ?? '') === 'active'));
$expiredServices = count(array_filter($invoices, fn($inv) => in_array($inv['Status'] ?? '', ['end_of_time', 'end_of_volume', 'expired'])));
$paidCount = count(array_filter($payments, fn($p) => in_array($p['payment_Status'] ?? '', ['paid', 'success'])));
$convRate = count($payments) > 0 ? round($paidCount / count($payments) * 100) : 0;

$agent = $user['agent'] ?? 'f';
$isBlocked = ($user['User_Status'] ?? '') === 'block';
$fullName = $user['namecustom'] ?? '';
if ($fullName === 'none')
    $fullName = '';
$username = $user['username'] ?? '';
if ($username === 'none')
    $username = '';
$initials = mb_strtoupper(mb_substr($fullName ?: ($username ?: 'U'), 0, 1, 'UTF-8'), 'UTF-8');

$pageTitle = $fullName ?: ($username ? '@' . $username : $textbotlang['panel']['userNumberPrefix'] . $id);
$activeNav = 'users';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px"
    class="fade-up">
    <a href="users.php" class="btn btn-ghost btn-sm"><?= icon('arrow-left', 14) ?> <?= $textbotlang['panel']['userProfileHeading'] ?></a>
    <?php if ($username): ?>
        <a href="https://t.me/<?= htmlspecialchars($username) ?>" target="_blank" rel="noopener"
            class="btn btn-ghost btn-sm">
            <?= icon('eye', 13) ?> <?= $textbotlang['panel']['userBackToUsersBtn'] ?>
        </a>
    <?php endif; ?>
</div>

<div class="stats u-stats fade-up" style="margin-bottom:18px">
    <div class="stat fade-up">
        <div class="stat-label"><?= $textbotlang['panel']['userIdLabel'] ?></div>
        <div class="stat-num"><?= number_format($balance) ?><small><?= $textbotlang['panel']['usernameLabel'] ?></small></div>
        <div class="stat-meta"><?= $textbotlang['panel']['userFirstNameLabel'] ?></div>
    </div>
    <div class="stat ok fade-up d1">
        <div class="stat-label"><?= $textbotlang['panel']['userBalanceLabel'] ?></div>
        <div class="stat-num">
            <?= $totalSpent >= 1_000_000
                ? number_format($totalSpent / 1_000_000, 1) . $textbotlang['panel']['userUnitMillionToman']
                : number_format($totalSpent) . $textbotlang['panel']['userUnitToman'] ?>
        </div>
        <div class="stat-meta"><?= count($invoices) ?> <?= $textbotlang['panel']['userGroupLabel'] ?></div>
    </div>
    <div class="stat warn fade-up d2">
        <div class="stat-label"><?= $textbotlang['panel']['userStatusLabel'] ?></div>
        <div class="stat-num"><?= $activeServices ?></div>
        <div class="stat-meta"><?= $expiredServices ?> <?= $textbotlang['panel']['userJoinDateLabel'] ?></div>
    </div>
    <div class="stat fade-up d3">
        <div class="stat-label"><?= $textbotlang['panel']['userCustomNameLabel'] ?></div>
        <div class="stat-num"><?= $convRate ?>%</div>
        <div class="stat-meta"><?= $paidCount ?> <?= $textbotlang['panel']['userPhoneLabel'] ?> <?= count($payments) ?></div>
    </div>
</div>

<div class="profile-grid u-profile-grid">

    <div class="u-sidebar" style="display:flex;flex-direction:column;gap:12px">

        <div class="card fade-up">
            <div class="profile-head">
                <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="profile-name"><?= htmlspecialchars($fullName ?: $textbotlang['panel']['userNoName']) ?></div>
                <?php if ($username): ?>
                    <div class="profile-handle">@<?= htmlspecialchars($username) ?></div>
                <?php endif; ?>
                <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                    <span class="tag <?= $isBlocked ? 'tag-no' : 'tag-ok' ?>">
                        <?= $isBlocked ? $textbotlang['panel']['userStatusBlocked'] : $textbotlang['panel']['userStatusActive'] ?>
                    </span>
                    <span class="tag <?= user_role_tag($agent) ?>">
                        <?= user_role_label($agent) ?>
                    </span>
                </div>
            </div>

            <div class="kv-list">
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['userAddBalanceTitle'] ?></span>
                    <span class="kv-val cm"><?= htmlspecialchars($user['id']) ?></span>
                </div>
                <?php if ($fullName): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['userAddBalanceBtn'] ?></span>
                        <span class="kv-val"><?= htmlspecialchars($fullName) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['number']) && $user['number'] !== 'none'): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['userChangeGroupTitle'] ?></span>
                        <span class="kv-val cm"><?= htmlspecialchars($user['number']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['userChangeGroupBtn'] ?></span>
                    <span class="kv-val" style="color:var(--ac)"><?= number_format($balance) ?> <?= $textbotlang['panel']['userBlockUserBtn'] ?></span>
                </div>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['userUnblockUserBtn'] ?></span>
                    <span class="kv-val">
                        <span class="tag <?= user_role_tag($agent) ?>"><?= user_role_label($agent) ?></span>
                        <span class="cm cf"
                            style="margin-right:6px;font-size:.72rem"><?= htmlspecialchars($agent) ?></span>
                    </span>
                </div>
                <div class="kv">
                    <span class="kv-key"><?= $textbotlang['panel']['userServicesTabLabel'] ?></span>
                    <span class="kv-val"><?= safe_date($user['register'] ?? null) ?></span>
                </div>
                <?php if (!empty($user['affiliates']) && $user['affiliates'] !== '0'): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['userTransactionsTabLabel'] ?></span>
                        <span class="kv-val cm" style="color:var(--ac)"><?= htmlspecialchars($user['affiliates']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['affiliatescount'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['userOrdersTabLabel'] ?></span>
                        <span class="kv-val"><?= number_format((int) $user['affiliatescount']) ?> <?= $textbotlang['panel']['userColService'] ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['score'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['userColStatus'] ?></span>
                        <span class="kv-val" style="color:var(--warn)">⭐ <?= number_format((int) $user['score']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['expire'])): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['userColVolume'] ?></span>
                        <span class="kv-val"
                            style="<?= is_numeric($user['expire']) && (int) $user['expire'] < time() ? 'color:var(--no)' : '' ?>">
                            <?= safe_date($user['expire']) ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['codeInvitation'])): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['userColTime'] ?></span>
                        <span class="kv-val cm"
                            style="color:var(--ac)"><?= htmlspecialchars($user['codeInvitation']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['message_count'] ?? 0) > 0): ?>
                    <div class="kv">
                        <span class="kv-key"><?= $textbotlang['panel']['userColPanel'] ?></span>
                        <span class="kv-val cn"><?= number_format((int) $user['message_count']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card fade-up d1">
            <div class="card-head">
                <div class="card-title">عملیات کاربر</div>
            </div>
            <div style="padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:6px">
                <button class="btn btn-primary btn-sm" style="justify-content:center" onclick="openModal('addModal')">
                    <?= icon('plus', 13) ?> افزایش موجودی
                </button>
                <button class="btn btn-primary btn-sm" style="background:var(--warn);color:#000;justify-content:center" onclick="openModal('deductModal')">
                    <?= icon('minus', 13) ?> کسر موجودی
                </button>
                <a href="user_action.php?action=zerobalance&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php" class="btn btn-ghost btn-sm" style="justify-content:center" data-confirm="آیا از صفر کردن موجودی کاربر مطمئن هستید؟">
                    <?= icon('slash', 13) ?> صفر موجودی
                </a>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('roleModal')">
                    <?= icon('users', 13) ?> تغییر گروه
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('discountModal')">
                    <?= icon('percent', 13) ?> درصد تخفیف
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('msgModal')">
                    <?= icon('message-square', 13) ?> ارسال پیام
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('limitTestModal')">
                    <?= icon('sliders', 13) ?> اکانت تست
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('transferModal')">
                    <?= icon('refresh-cw', 13) ?> انتقال حساب
                </button>

                <?php if ($isBlocked): ?>
                    <a href="user_action.php?action=unblock&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                        class="btn btn-ok btn-sm" style="justify-content:center" data-confirm="آیا از رفع مسدودیت مطمئن هستید؟">
                        <?= icon('check', 13) ?> رفع مسدودی
                    </a>
                <?php else: ?>
                    <a href="user_action.php?action=block&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                        class="btn btn-no btn-sm" style="justify-content:center" data-confirm="آیا از مسدود کردن مطمئن هستید؟">
                        <?= icon('block', 13) ?> مسدود کردن
                    </a>
                <?php endif; ?>

                <?php $isVerify = (int)($user['verify'] ?? 0) === 1; ?>
                <a href="user_action.php?action=toggle_verify&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                    class="btn <?= $isVerify ? 'btn-no' : 'btn-ok' ?> btn-sm" style="justify-content:center" data-confirm="آیا مطمئن هستید؟">
                    <?= icon($isVerify ? 'x-circle' : 'check-circle', 13) ?> <?= $isVerify ? 'لغو احراز هویت' : 'تایید احراز هویت' ?>
                </a>

                <?php $isCard = (int)($user['cardpayment'] ?? 0) === 1; ?>
                <a href="user_action.php?action=toggle_card&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                    class="btn <?= $isCard ? 'btn-no' : 'btn-ok' ?> btn-sm" style="justify-content:center" data-confirm="آیا مطمئن هستید؟">
                    <?= icon('credit-card', 13) ?> <?= $isCard ? 'غیرفعال کارت' : 'فعالسازی کارت' ?>
                </a>

                <a href="user_action.php?action=verify_channel&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                    class="btn btn-ghost btn-sm" style="justify-content:center" data-confirm="آیا تایید عضویت کانال اعمال شود؟">
                    <?= icon('bell', 13) ?> تایید کانال
                </a>

                <?php $isCron = (int)($user['status_cron'] ?? 0) === 1; ?>
                <a href="user_action.php?action=toggle_cron&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                    class="btn <?= $isCron ? 'btn-no' : 'btn-ok' ?> btn-sm" style="justify-content:center" data-confirm="آیا مطمئن هستید؟">
                    <?= icon('clock', 13) ?> <?= $isCron ? 'غیرفعال پیام' : 'فعال پیام کرون' ?>
                </a>
                
                <a href="user_action.php?action=removeaffiliates&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                    class="btn btn-ghost btn-sm" style="justify-content:center" data-confirm="حذف تمام زیرمجموعه های کاربر؟">
                    <?= icon('user-minus', 13) ?> حذف زیرمجموعه‌ها
                </a>
            </div>
        </div>

        <?php if (in_array($agent, ['n', 'n2'])): ?>
        <div class="card fade-up d2">
            <div class="card-head">
                <div class="card-title">عملیات نمایندگی</div>
            </div>
            <div style="padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:6px">
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('agentBuyCapModal')">
                    <?= icon('briefcase', 13) ?> سقف خرید نماینده
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('agentVolPriceModal')">
                    <?= icon('database', 13) ?> قیمت پایه حجم
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('agentTimePriceModal')">
                    <?= icon('clock', 13) ?> قیمت پایه زمان
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('agentHidePanelModal')">
                    <?= icon('eye-off', 13) ?> مخفی کردن پنل
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('agentExpireModal')">
                    <?= icon('calendar', 13) ?> تاریخ انقضا
                </button>
                <button class="btn btn-ghost btn-sm" style="justify-content:center" onclick="openModal('agentLocLimitModal')">
                    <?= icon('map-pin', 13) ?> سقف لوکیشن
                </button>

                <?php $isBotSell = (int)($user['status_bot_sell'] ?? 0) === 1; ?>
                <a href="user_action.php?action=toggle_bot&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                    class="btn <?= $isBotSell ? 'btn-no' : 'btn-ok' ?> btn-sm" style="justify-content:center" data-confirm="آیا مطمئن هستید؟">
                    <?= icon('cpu', 13) ?> <?= $isBotSell ? 'حذف ربات فروش' : 'ساخت ربات فروش' ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="u-main-col" style="display:flex;flex-direction:column;gap:16px">

        <div class="card fade-up">
            <div class="card-head">
                <div class="u-tab-bar" style="display:flex;gap:4px;background:var(--sf2);border-radius:7px;padding:3px">
                    <button class="btn btn-sm" id="tabOrders" onclick="switchTab('orders')"
                        style="background:var(--ac);color:#fff;border-radius:5px;font-size:.75rem">
                        <?= $textbotlang['panel']['userColProduct'] ?>
                    </button>
                    <button class="btn btn-sm" id="tabPay" onclick="switchTab('pay')"
                        style="background:transparent;color:var(--mute);border-radius:5px;font-size:.75rem;border:none">
                        <?= $textbotlang['panel']['userColPrice'] ?>
                    </button>
                    <?php if (count($referrals) > 0): ?>
                        <button class="btn btn-sm" id="tabRefs" onclick="switchTab('refs')"
                            style="background:transparent;color:var(--mute);border-radius:5px;font-size:.75rem;border:none">
                            <?= $textbotlang['panel']['userNoOrderForUser'] ?>
                            <span
                                style="background:var(--acs);color:var(--ac);padding:1px 6px;border-radius:99px;font-size:.65rem">
                                <?= count($referrals) ?>
                            </span>
                        </button>
                    <?php endif; ?>
                </div>
                <a href="invoice.php?q=<?= urlencode($id) ?>" class="btn-link" style="font-size:.75rem"><?= $textbotlang['panel']['userColTrackingCode'] ?></a>
            </div>

            <div id="paneOrders">
                <div class="tbl-wrap">
                    <table class="tbl-lg">
                        <thead>
                            <tr>
                                <th><?= $textbotlang['panel']['userDetailTitle'] ?></th>
                                <th><?= $textbotlang['panel']['userCloseBtn'] ?></th>
                                <th><?= $textbotlang['panel']['userRoleNormalUser'] ?></th>
                                <th><?= $textbotlang['panel']['userRoleAgent'] ?></th>
                                <th><?= $textbotlang['panel']['userRoleAdvancedAgent'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty" style="padding:30px">
                                            <p><?= $textbotlang['panel']['userColId'] ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else:
                                $statusMap = [
                                    'active' => ['tag-ok', $textbotlang['panel']['userStatusActive2']],
                                    'end_of_time' => ['tag-warn', $textbotlang['panel']['userStatusNearTimeEnd']],
                                    'end_of_volume' => ['tag-no', $textbotlang['panel']['userStatusNearVolumeEnd']],
                                    'sendedwarn' => ['tag-warn', $textbotlang['panel']['userNotifAllSent']],
                                    'send_on_hold' => ['tag-plain', $textbotlang['panel']['userStatusWaiting']],
                                    'unpiad' => ['tag-plain', $textbotlang['panel']['userStatusUnpaid']],
                                ];
                                foreach ($invoices as $inv):
                                    [$tagClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['tag-plain', $inv['Status'] ?? '—'];
                                    ?>
                                    <tr>
                                        <td class="cs"
                                            style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                            <?= htmlspecialchars($inv['name_product'] ?? '—') ?>
                                        </td>
                                        <td class="cn cs" style="white-space:nowrap">
                                            <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['userColName'] ?></span>
                                        </td>
                                        <td class="cn cf"><?= htmlspecialchars($inv['Volume'] ?? '—') ?></td>
                                        <td class="cf" style="white-space:nowrap">
                                            <?= safe_date($inv['time_sell'] ?? null, 'Y/m/d') ?>
                                        </td>
                                        <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="panePay" style="display:none">
                <div class="tbl-wrap">
                    <table class="tbl-md">
                        <thead>
                            <tr>
                                <th><?= $textbotlang['panel']['userColCreatedAt'] ?></th>
                                <th><?= $textbotlang['panel']['userWalletLabel'] ?></th>
                                <th><?= $textbotlang['panel']['userTotalPurchaseLabel'] ?></th>
                                <th><?= $textbotlang['panel']['userTotalServicesLabel'] ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty" style="padding:30px">
                                            <p><?= $textbotlang['panel']['userAffiliateCountLabel'] ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else:
                                $methodLabels = [
                                    'cart to cart' => $textbotlang['panel']['userMethodCardToCard'],
                                    'add balance by admin' => $textbotlang['panel']['userMethodAdminAdd'],
                                    'low balance by admin' => $textbotlang['panel']['userMethodAdminDeduct'],
                                    'zarinpal' => $textbotlang['panel']['userMethodZarinpal'],
                                    'aqayepardakht' => $textbotlang['panel']['userMethodAqayePardakht'],
                                    'plisio' => 'Plisio',
                                    'nowpayment' => 'NowPayment',
                                    'Star Telegram' => $textbotlang['panel']['userMethodTelegramStar'],
                                    'Currency Rial 1' => $textbotlang['panel']['userMethodRial1'],
                                    'Currency Rial tow' => $textbotlang['panel']['userMethodRial2'],
                                    'Currency Rial 3' => $textbotlang['panel']['userMethodRial3'],
                                    'arze digital offline' => $textbotlang['panel']['userMethodCrypto'],
                                ];
                                $payStatusMap = [
                                    'paid' => ['tag-ok', $textbotlang['panel']['userStatusSuccess']],
                                    'Unpaid' => ['tag-no', $textbotlang['panel']['userStatusFailed']],
                                    'expire' => ['tag-plain', $textbotlang['panel']['userStatusExpired']],
                                    'reject' => ['tag-no', $textbotlang['panel']['userStatusRejected']],
                                    'waiting' => ['tag-warn', $textbotlang['panel']['userStatusWaiting2']],
                                    'pending' => ['tag-warn', $textbotlang['panel']['userStatusWaiting3']],
                                ];
                                foreach ($payments as $p):
                                    $payStatus = $p['payment_Status'] ?? '';
                                    [$tagClass, $label] = $payStatusMap[$payStatus] ?? ['tag-plain', $payStatus ?: '—'];
                                    $method = $methodLabels[$p['Payment_Method'] ?? ''] ?? ($p['Payment_Method'] ?? '—');
                                    ?>
                                    <tr>
                                        <td class="cn cs" style="white-space:nowrap">
                                            <?= number_format((int) ($p['price'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['userReferrerLabel'] ?></span>
                                        </td>
                                        <td style="font-size:.82rem"><?= htmlspecialchars($method) ?></td>
                                        <td class="cf" style="white-space:nowrap">
                                            <?= safe_date($p['time'] ?? null, 'Y/m/d H:i') ?>
                                        </td>
                                        <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (count($referrals) > 0): ?>
                <div id="paneRefs" style="display:none">
                    <div class="tbl-wrap">
                        <table class="tbl-md">
                            <thead>
                                <tr>
                                    <th><?= $textbotlang['panel']['userNoteLabel'] ?></th>
                                    <th><?= $textbotlang['panel']['userEditNoteBtn'] ?></th>
                                    <th><?= $textbotlang['panel']['userSendMessageBtn'] ?></th>
                                    <th><?= $textbotlang['panel']['userSendMessageTitle'] ?></th>
                                    <th><?= $textbotlang['panel']['userMessagePlaceholder'] ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referrals as $ref):
                                    $refName = $ref['namecustom'] ?? '';
                                    if ($refName === 'none')
                                        $refName = '';
                                    $refUname = $ref['username'] ?? '';
                                    if ($refUname === 'none')
                                        $refUname = '';
                                    $refAgent = $ref['agent'] ?? 'f';
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="user.php?id=<?= (int) $ref['id'] ?>" class="cm" style="color:var(--ac)">
                                                <?= htmlspecialchars($ref['id']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($refName): ?>
                                                <span class="cs"><?= htmlspecialchars(trunc($refName, 16)) ?></span>
                                            <?php elseif ($refUname): ?>
                                                <span class="cm"
                                                    style="color:var(--ac)">@<?= htmlspecialchars(trunc($refUname, 14)) ?></span>
                                            <?php else: ?>
                                                <span class="cf">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cn" style="white-space:nowrap">
                                            <?= number_format((int) ($ref['Balance'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['userSendBtn'] ?></span>
                                        </td>
                                        <td>
                                            <span class="tag <?= user_role_tag($refAgent) ?>">
                                                <?= user_role_label($refAgent) ?>
                                            </span>
                                        </td>
                                        <td class="cf"><?= safe_date($ref['register'] ?? null, 'm/d') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<div class="modal-veil" id="addModal">
    <div class="modal">
        <div class="modal-head">
            <h3><?= $textbotlang['panel']['userCancelBtn'] ?></h3>
            <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_balance">
                <div class="field">
                    <label><?= $textbotlang['panel']['userDetailUser'] ?></label>
                    <input type="number" name="amount" class="input" placeholder=$textbotlang['panel']['userAmountPlaceholder'] min="1000" required>
                    <span class="field-hint"><?= $textbotlang['panel']['userDetailAmount'] ?> <strong><?= number_format($balance) ?> <?= $textbotlang['panel']['userDetailMethod'] ?></strong></span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> <?= $textbotlang['panel']['userDetailStatus'] ?></button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')"><?= $textbotlang['panel']['userDetailDate'] ?></button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="roleModal">
    <div class="modal">
        <div class="modal-head">
            <h3><?= $textbotlang['panel']['userDetailProduct'] ?></h3>
            <button class="modal-x" onclick="closeModal('roleModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_role">
                <div class="field">
                    <label><?= $textbotlang['panel']['userDetailService'] ?></label>
                    <select name="new_role" class="select">
                        <option value="f" <?= $agent === 'f' ? 'selected' : '' ?>><?= $textbotlang['panel']['userRoleFreeUser'] ?></option>
                        <option value="n" <?= $agent === 'n' ? 'selected' : '' ?>><?= $textbotlang['panel']['userRoleNormalAgent'] ?></option>
                        <option value="n2" <?= $agent === 'n2' ? 'selected' : '' ?>><?= $textbotlang['panel']['userRoleAdvancedAgent2'] ?></option>
                    </select>
                    <span class="field-hint">
                        <?= $textbotlang['panel']['userDetailPanel'] ?> <strong><?= user_role_label($agent) ?></strong>
                        <span class="cm" style="color:var(--mute)">(<?= htmlspecialchars($agent) ?>)</span>
                    </span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $textbotlang['panel']['userDetailTrackingCode'] ?></button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('roleModal')"><?= $textbotlang['panel']['userDetailDescription'] ?></button>
            </div>
        </form>
    </div>
</div>

<!-- New Modals -->
<div class="modal-veil" id="deductModal">
    <div class="modal">
        <div class="modal-head">
            <h3>کسر موجودی</h3>
            <button class="modal-x" onclick="closeModal('deductModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="deduct_balance">
                <div class="field">
                    <label>مبلغ به تومان</label>
                    <input type="number" name="amount" class="input" placeholder="مبلغ را وارد کنید..." required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary" style="background:var(--warn);color:#000"><?= icon('minus', 13) ?> کسر از حساب</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('deductModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="discountModal">
    <div class="modal">
        <div class="modal-head">
            <h3>درصد تخفیف</h3>
            <button class="modal-x" onclick="closeModal('discountModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_discount">
                <div class="field">
                    <label>درصد (مثال: 10)</label>
                    <input type="number" name="percent" class="input" placeholder="درصد تخفیف..." min="0" max="100" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('percent', 13) ?> ثبت تخفیف</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('discountModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="msgModal">
    <div class="modal">
        <div class="modal-head">
            <h3>ارسال پیام به کاربر</h3>
            <button class="modal-x" onclick="closeModal('msgModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="send_msg">
                <div class="field">
                    <label>متن پیام</label>
                    <textarea name="message" class="input" style="height:80px" placeholder="پیام خود را بنویسید..." required></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('message-square', 13) ?> ارسال</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('msgModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="limitTestModal">
    <div class="modal">
        <div class="modal-head">
            <h3>سقف اکانت تست</h3>
            <button class="modal-x" onclick="closeModal('limitTestModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_test_limit">
                <div class="field">
                    <label>تعداد محدودیت</label>
                    <input type="number" name="limit" class="input" placeholder="مثال: 5" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('sliders', 13) ?> ثبت سقف</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('limitTestModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="transferModal">
    <div class="modal">
        <div class="modal-head">
            <h3>انتقال حساب به کاربر دیگر</h3>
            <button class="modal-x" onclick="closeModal('transferModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="transfer_account">
                <div class="field">
                    <label>آیدی عددی کاربر مقصد</label>
                    <input type="number" name="target_id" class="input" placeholder="ID کاربر هدف..." required>
                    <span class="field-hint" style="color:var(--warn)">توجه: موجودی و سرویس‌ها منتقل خواهند شد.</span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary" style="background:var(--warn);color:#000"><?= icon('refresh-cw', 13) ?> انتقال اطلاعات</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('transferModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<!-- Agent Modals -->
<div class="modal-veil" id="agentBuyCapModal">
    <div class="modal">
        <div class="modal-head">
            <h3>سقف خرید نماینده</h3>
            <button class="modal-x" onclick="closeModal('agentBuyCapModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_buy_cap">
                <div class="field">
                    <label>مبلغ سقف (تومان)</label>
                    <input type="number" name="cap" class="input" placeholder="مثلا 1000000" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ثبت</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('agentBuyCapModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="agentVolPriceModal">
    <div class="modal">
        <div class="modal-head">
            <h3>قیمت پایه حجم</h3>
            <button class="modal-x" onclick="closeModal('agentVolPriceModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_vol_price">
                <div class="field">
                    <label>قیمت (تومان)</label>
                    <input type="number" name="price" class="input" placeholder="قیمت پایه..." required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ثبت</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('agentVolPriceModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="agentTimePriceModal">
    <div class="modal">
        <div class="modal-head">
            <h3>قیمت پایه زمان</h3>
            <button class="modal-x" onclick="closeModal('agentTimePriceModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_time_price">
                <div class="field">
                    <label>قیمت (تومان)</label>
                    <input type="number" name="price" class="input" placeholder="قیمت پایه..." required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ثبت</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('agentTimePriceModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="agentHidePanelModal">
    <div class="modal">
        <div class="modal-head">
            <h3>مخفی کردن پنل</h3>
            <button class="modal-x" onclick="closeModal('agentHidePanelModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_hide_panel">
                <div class="field">
                    <label>نام پنل (جهت مخفی‌سازی)</label>
                    <input type="text" name="panel_name" class="input" placeholder="نام پنل..." required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('eye-off', 13) ?> مخفی کن</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('agentHidePanelModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="agentExpireModal">
    <div class="modal">
        <div class="modal-head">
            <h3>تعیین تاریخ انقضا</h3>
            <button class="modal-x" onclick="closeModal('agentExpireModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_expire">
                <div class="field">
                    <label>تاریخ (به صورت timestamp یا فرمت استاندارد)</label>
                    <input type="text" name="timestamp" class="input" placeholder="مثال: 1698765432" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('calendar', 13) ?> ثبت انقضا</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('agentExpireModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="agentLocLimitModal">
    <div class="modal">
        <div class="modal-head">
            <h3>سقف خرید لوکیشن</h3>
            <button class="modal-x" onclick="closeModal('agentLocLimitModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_loc_limit">
                <div class="field">
                    <label>محدودیت (تعداد)</label>
                    <input type="number" name="limit" class="input" placeholder="تعداد..." required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('map-pin', 13) ?> ثبت سقف</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('agentLocLimitModal')">لغو</button>
            </div>
        </form>
    </div>
</div>

<script src="js/profile.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>