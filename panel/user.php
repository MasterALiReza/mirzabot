<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../botapi.php';
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
            $res = telegram('SendMessage', [
                'chat_id' => $id,
                'text' => $message,
            ]);
            if (isset($res['ok']) && $res['ok']) {
                flash('success', "پیام شما به کاربر ارسال شد.");
            } else {
                $err = $res['description'] ?? 'خطای نامشخص تلگرام';
                flash('error', "ارسال پیام ناموفق بود: $err");
            }
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
            flash('error', "شناسه کاربر وارد شده معتبر نیست.");
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

<div class="dash-grid fade-up" style="margin-bottom:18px">
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5H5v-4h16V7"/></svg>
            </div>
            <div class="dash-card-title">موجودی</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill neutral">کیف پول</span>
            </div>
            <div class="dash-card-value-flex">
                <span class="dash-card-value cn"><?= number_format($balance) ?></span>
                <span class="dash-card-unit cf">تومان</span>
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <div class="dash-card-title">مجموع خرید</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill neutral"><?= count($invoices) ?> سفارش</span>
            </div>
            <div class="dash-card-value-flex">
                <?php if ($totalSpent >= 1_000_000): ?>
                    <span class="dash-card-value cn"><?= number_format($totalSpent / 1_000_000, 1) ?></span>
                    <span class="dash-card-unit cf">م ت</span>
                <?php else: ?>
                    <span class="dash-card-value cn"><?= number_format($totalSpent) ?></span>
                    <span class="dash-card-unit cf">تومان</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
            </div>
            <div class="dash-card-title">سرویس فعال</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <?php if ($expiredServices > 0): ?>
                    <span class="status-pill danger"><?= $expiredServices ?> منقضی</span>
                <?php else: ?>
                    <span class="status-pill neutral">بدون منقضی</span>
                <?php endif; ?>
            </div>
            <div class="dash-card-value-flex">
                <span class="dash-card-value cn"><?= $activeServices ?></span>
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="dash-card-title">نرخ پرداخت</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill neutral"><?= $paidCount ?> از <?= count($payments) ?> موفق</span>
            </div>
            <div class="dash-card-value-flex">
                <span class="dash-card-value cn"><?= $convRate ?></span>
                <span class="dash-card-unit cf">%</span>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    .dash-orders td {
        justify-content: flex-start !important;
        gap: 12px !important;
    }
    .dash-orders td::before {
        min-width: 80px;
        color: var(--mute) !important;
    }
}
.u-sidebar {
    /* Style moved to CSS or handled cleanly */
}
.u-sidebar::-webkit-scrollbar {
    width: 4px;
}
.u-sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.u-sidebar::-webkit-scrollbar-thumb {
    background: var(--bd);
    border-radius: 4px;
}
</style>

<div class="profile-grid u-profile-grid">

    <div class="u-sidebar" style="display:flex;flex-direction:column;gap:12px;">

        <div class="card fade-up">
            <div class="profile-head" style="display: flex; flex-direction: column; gap: 16px;">
                <!-- Row 1: Avatar, Name and Status Tags -->
                <div style="display: flex; gap: 16px; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap;">
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <div style="width: 84px; display:flex; justify-content:center; flex-shrink:0;">
                            <div class="profile-avatar" style="width: 84px; height: 84px; display:flex; align-items:center; justify-content:center; font-size: 2.5rem; background: rgba(var(--ac-rgb), 0.1); color: var(--ac); border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:6px; justify-content: center; text-align:right; align-items:flex-start;">
                            <span style="font-weight:800; font-size:1.35rem; color:var(--text);">
                                <?php if ($fullName): ?>
                                    <?= htmlspecialchars($fullName) ?>
                                <?php elseif ($username): ?>
                                    <span class="cm" style="direction:ltr; display:inline-block;">@<?= htmlspecialchars($username) ?></span>
                                <?php else: ?>
                                    <span style="font-size:1.15rem; font-weight:600; opacity:0.8; text-align:center;">بدون نام</span>
                                <?php endif; ?>
                            </span>
                            <?php if ($fullName && $username): ?>
                                <span class="cm" style="color:var(--mute); font-size:1rem; font-weight:600; direction:ltr; display:inline-block;">@<?= htmlspecialchars($username) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items:center; gap: 8px;">
                        <span class="tag <?= user_role_tag($agent) ?>">
                            <?= user_role_label($agent) ?>
                        </span>
                        <span class="tag <?= $isBlocked ? 'tag-no' : 'tag-ok' ?>">
                            <?= $isBlocked ? $textbotlang['panel']['userStatusBlocked'] : $textbotlang['panel']['userStatusActive'] ?>
                        </span>
                    </div>
                </div>
                
                <!-- Row 2: User ID -->
                <div style="display: flex; gap: 16px; align-items: center; justify-content: flex-start; width: 100%;">
                    <div style="width: 84px; display:flex; justify-content:center; flex-shrink:0;">
                        <div onclick="navigator.clipboard.writeText('<?= htmlspecialchars($user['id']) ?>').then(()=> {let o=this.style.color; this.style.color='var(--ac)'; this.style.borderColor='var(--ac)'; setTimeout(()=>{this.style.color=o; this.style.borderColor='var(--bd)';},1000);})" style="width: 58px; height: 58px; display:flex; align-items:center; justify-content:center; background: var(--sf2); color: var(--mute); border-radius: 14px; border: 1px solid var(--bd); cursor: pointer; transition: 0.2s;" title="کپی شناسه" onmouseover="this.style.color='var(--ac)'; this.style.borderColor='var(--ac)';" onmouseout="this.style.color='var(--mute)'; this.style.borderColor='var(--bd)';">
                            <?= icon('copy', 20) ?>
                        </div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:4px; justify-content: center; align-items:flex-start;">
                        <span style="font-size:0.8rem; font-weight:600; color:var(--mute);">شناسه کاربر:</span>
                        <span class="cm" style="font-size:1.1rem; font-weight:700; direction:ltr; display:inline-block; color:var(--text);"><?= htmlspecialchars($user['id']) ?></span>
                    </div>
                </div>
            </div>

            <div class="user-kv-grid" style="display:flex; flex-direction:column; gap:10px; padding:16px; background:transparent; border-top: 1px solid var(--bd);">
                <?php if ($fullName): ?>
                    <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                        <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('user', 14) ?> نام:</span>
                        <span style="font-weight:600; font-size:0.9rem;"><?= htmlspecialchars($fullName) ?></span>
                    </div>
                <?php endif; ?>
                <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                    <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('phone', 14) ?> اطلاعات تماس:</span>
                    <span class="cm" style="direction: ltr; font-weight:600; font-size:0.9rem;">
                        <?= (!empty($user['number']) && $user['number'] !== 'none') ? htmlspecialchars($user['number']) : '—' ?>
                    </span>
                </div>
                <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                    <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('calendar', 14) ?> تاریخ عضویت:</span>
                    <span class="cn" style="font-weight:600; font-size:0.9rem;"><?= safe_date($user['register'] ?? null, 'Y/m/d') ?></span>
                </div>
                <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                    <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('wallet', 14) ?> کیف پول کاربر:</span>
                    <span class="cn" style="font-weight:700; font-size:1rem; color:var(--ac);"><?= number_format($balance) ?> <span class="cf" style="font-size:0.75rem">ت</span></span>
                </div>
                <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                    <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('star', 14) ?> امتیاز کاربر:</span>
                    <span class="cn" style="font-weight:600; font-size:1.05rem;"><?= number_format((int) ($user['score'] ?? 0)) ?></span>
                </div>
                <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                    <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('award', 14) ?> نوع کاربری:</span>
                    <span class="cn" style="font-weight:600; font-size:0.9rem;"><?= user_role_label($user['agent'] ?? 'f') ?></span>
                </div>
                <?php if (!empty($user['affiliates']) && $user['affiliates'] !== '0'): ?>
                    <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                        <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('user-plus', 14) ?> معرف:</span>
                        <span class="cm" style="color:var(--ac); direction: ltr; font-weight:600; font-size:0.9rem;"><?= htmlspecialchars($user['affiliates']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['affiliatescount'] ?? 0) > 0): ?>
                    <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                        <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('users', 14) ?> زیرمجموعه‌ها:</span>
                        <span class="cn" style="font-weight:600; font-size:0.9rem;"><?= number_format((int) $user['affiliatescount']) ?> <span class="cf" style="font-size:0.8rem">نفر</span></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['expire'])): ?>
                    <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                        <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('hard-drive', 14) ?> <?= $textbotlang['panel']['userColVolume'] ?>:</span>
                        <span class="cn" style="font-weight:600; <?= is_numeric($user['expire']) && (int) $user['expire'] < time() ? 'color:var(--no)' : '' ?>">
                            <?= safe_date($user['expire'], 'Y/m/d H:i') ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user['codeInvitation'])): ?>
                    <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                        <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('hash', 14) ?> کد عضویت:</span>
                        <span class="cm" style="color:var(--ac); direction: ltr; text-align: left; font-weight:600; font-size:0.9rem;"><?= htmlspecialchars($user['codeInvitation']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($user['message_count'] ?? 0) > 0): ?>
                    <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                        <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('message-square', 14) ?> پیام‌ها:</span>
                        <span class="cn" style="font-weight:600; font-size:0.9rem;"><?= number_format((int) $user['message_count']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="u-main-col" style="display:flex;flex-direction:column;gap:16px">

        <div class="card fade-up d1">
            <div class="card-head">
                <div class="card-title" style="display:flex;align-items:center;gap:6px">
                    <span style="color:var(--ac)"><?= icon('zap', 16) ?></span> عملیات کاربر
                </div>
            </div>
            <div style="padding: 16px; display: flex; flex-direction: column; gap: 20px;">
                <!-- Financial Actions -->
                <div>
                    <div style="font-size:0.85rem;color:var(--mute);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <?= icon('wallet', 14) ?> <span>امور مالی</span>
                        <div style="flex:1;height:1px;background:var(--bd);margin-right:10px;"></div>
                    </div>
                    <div class="user-actions-grid" style="padding:0;">
                        <button class="btn btn-primary" onclick="openModal('addModal')">
                            <?= icon('plus', 14) ?> افزایش موجودی
                        </button>
                        <button class="btn" style="background:var(--warn);color:#000;border:none" onclick="openModal('deductModal')">
                            <?= icon('minus', 14) ?> کسر موجودی
                        </button>
                        <a href="user_action.php?action=zerobalance&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php" class="btn btn-ghost" data-confirm="آیا از صفر کردن موجودی کاربر مطمئن هستید؟" hx-boost="false">
                            <?= icon('slash', 14) ?> صفر موجودی
                        </a>
                        <button class="btn btn-ghost" onclick="openModal('discountModal')">
                            <?= icon('percent', 14) ?> درصد تخفیف
                        </button>
                    </div>
                </div>

                <!-- Access Actions -->
                <div>
                    <div style="font-size:0.85rem;color:var(--mute);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <?= icon('shield', 14) ?> <span>دسترسی و محدودیت‌ها</span>
                        <div style="flex:1;height:1px;background:var(--bd);margin-right:10px;"></div>
                    </div>
                    <div class="user-actions-grid" style="padding:0;">
                        <?php if ($isBlocked): ?>
                            <a href="user_action.php?action=unblock&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                                class="btn btn-ok" data-confirm="آیا از رفع مسدودیت مطمئن هستید؟" hx-boost="false">
                                <?= icon('check', 14) ?> رفع مسدودی
                            </a>
                        <?php else: ?>
                            <a href="user_action.php?action=block&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                                class="btn btn-no" data-confirm="آیا از مسدود کردن مطمئن هستید؟" hx-boost="false">
                                <?= icon('block', 14) ?> مسدود کردن
                            </a>
                        <?php endif; ?>

                        <?php $isVerify = (int)($user['verify'] ?? 0) === 1; ?>
                        <a href="user_action.php?action=toggle_verify&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                            class="btn <?= $isVerify ? 'btn-no' : 'btn-ok' ?>" data-confirm="آیا مطمئن هستید؟" hx-boost="false">
                            <?= icon($isVerify ? 'x-circle' : 'check-circle', 14) ?> <?= $isVerify ? 'لغو احراز هویت' : 'تایید احراز هویت' ?>
                        </a>

                        <?php $isCard = (int)($user['cardpayment'] ?? 0) === 1; ?>
                        <a href="user_action.php?action=toggle_card&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                            class="btn <?= $isCard ? 'btn-no' : 'btn-ok' ?>" data-confirm="آیا مطمئن هستید؟" hx-boost="false">
                            <?= icon('credit-card', 14) ?> <?= $isCard ? 'غیرفعال کارت' : 'فعالسازی کارت' ?>
                        </a>

                        <button class="btn btn-ghost" onclick="openModal('limitTestModal')">
                            <?= icon('sliders', 14) ?> اکانت تست
                        </button>

                        <a href="user_action.php?action=verify_channel&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                            class="btn btn-ghost" data-confirm="آیا تایید عضویت کانال اعمال شود؟" hx-boost="false">
                            <?= icon('bell', 14) ?> تایید کانال
                        </a>
                    </div>
                </div>

                <!-- Management Actions -->
                <div>
                    <div style="font-size:0.85rem;color:var(--mute);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <?= icon('settings', 14) ?> <span>ارتباط و مدیریت</span>
                        <div style="flex:1;height:1px;background:var(--bd);margin-right:10px;"></div>
                    </div>
                    <div class="user-actions-grid" style="padding:0;">
                        <button class="btn btn-ghost" onclick="openModal('msgModal')">
                            <?= icon('message-square', 14) ?> ارسال پیام
                        </button>
                        
                        <?php $isCron = (int)($user['status_cron'] ?? 0) === 1; ?>
                        <a href="user_action.php?action=toggle_cron&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                            class="btn <?= $isCron ? 'btn-no' : 'btn-ok' ?>" data-confirm="آیا مطمئن هستید؟" hx-boost="false">
                            <?= icon('clock', 14) ?> <?= $isCron ? 'غیرفعال پیام' : 'فعال پیام کرون' ?>
                        </a>

                        <button class="btn btn-ghost" onclick="openModal('roleModal')">
                            <?= icon('users', 14) ?> تغییر گروه
                        </button>
                        <button class="btn btn-ghost" onclick="openModal('transferModal')">
                            <?= icon('refresh-cw', 14) ?> انتقال حساب
                        </button>
                        
                        <a href="user_action.php?action=removeaffiliates&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                            class="btn btn-ghost" data-confirm="حذف تمام زیرمجموعه های کاربر؟" hx-boost="false">
                            <?= icon('user-minus', 14) ?> حذف زیرمجموعه‌ها
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (in_array($agent, ['n', 'n2'])): ?>
        <div class="card fade-up d2">
            <div class="card-head">
                <div class="card-title" style="display:flex;align-items:center;gap:6px">
                    <span style="color:var(--warn)"><?= icon('briefcase', 16) ?></span> عملیات نمایندگی
                </div>
            </div>
            <div style="padding: 16px; display: flex; flex-direction: column; gap: 20px;">
                <!-- Pricing & Sales -->
                <div>
                    <div style="font-size:0.85rem;color:var(--mute);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <?= icon('tag', 14) ?> <span>فروش و قیمت‌گذاری</span>
                        <div style="flex:1;height:1px;background:var(--bd);margin-right:10px;"></div>
                    </div>
                    <div class="user-actions-grid" style="padding:0;">
                        <button class="btn btn-ghost" onclick="openModal('agentVolPriceModal')">
                            <?= icon('database', 14) ?> قیمت پایه حجم
                        </button>
                        <button class="btn btn-ghost" onclick="openModal('agentTimePriceModal')">
                            <?= icon('clock', 14) ?> قیمت پایه زمان
                        </button>
                        <button class="btn btn-ghost" onclick="openModal('agentBuyCapModal')">
                            <?= icon('briefcase', 14) ?> سقف خرید نماینده
                        </button>
                    </div>
                </div>

                <!-- Limits & Access -->
                <div>
                    <div style="font-size:0.85rem;color:var(--mute);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <?= icon('shield', 14) ?> <span>محدودیت‌ها و دسترسی</span>
                        <div style="flex:1;height:1px;background:var(--bd);margin-right:10px;"></div>
                    </div>
                    <div class="user-actions-grid" style="padding:0;">
                        <button class="btn btn-ghost" onclick="openModal('agentExpireModal')">
                            <?= icon('calendar', 14) ?> تاریخ انقضا
                        </button>
                        <button class="btn btn-ghost" onclick="openModal('agentLocLimitModal')">
                            <?= icon('map-pin', 14) ?> سقف لوکیشن
                        </button>
                        <button class="btn btn-ghost" onclick="openModal('agentHidePanelModal')">
                            <?= icon('eye-off', 14) ?> مخفی کردن پنل
                        </button>
                    </div>
                </div>

                <!-- Automation -->
                <div>
                    <div style="font-size:0.85rem;color:var(--mute);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <?= icon('cpu', 14) ?> <span>اتوماسیون</span>
                        <div style="flex:1;height:1px;background:var(--bd);margin-right:10px;"></div>
                    </div>
                    <div class="user-actions-grid" style="padding:0;">
                        <?php $isBotSell = (int)($user['status_bot_sell'] ?? 0) === 1; ?>
                        <a href="user_action.php?action=toggle_bot&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                            class="btn <?= $isBotSell ? 'btn-no' : 'btn-ok' ?>" data-confirm="آیا مطمئن هستید؟">
                            <?= icon('cpu', 14) ?> <?= $isBotSell ? 'حذف ربات فروش' : 'ساخت ربات فروش' ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card fade-up">
            <div class="card-head">
                <div class="u-tab-bar" style="display:flex;gap:4px;background:var(--sf2);border-radius:7px;padding:3px">
                    <button class="btn btn-sm active" id="tabInvs" onclick="switchTab('invs')">
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
                <div class="tbl-wrap dash-user-orders">
                    <table class="tbl-lg">
                        <thead>
                            <tr>
                                <th style="text-align:right;"><?= $textbotlang['panel']['dashColProduct'] ?? 'محصول' ?></th>
                                <th class="desktop-text-center" style="text-align:right;"><?= $textbotlang['panel']['dashColAmount'] ?? 'مبلغ' ?></th>
                                <th style="text-align:right;"><?= $textbotlang['panel']['dashColStatus'] ?? 'وضعیت' ?></th>
                                <th style="text-align:center;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="4">
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
                                    'unpaid' => ['tag-no', $textbotlang['panel']['userStatusUnpaid']],
                                ];
                                foreach ($invoices as $inv):
                                    [$tagClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['tag-plain', $inv['Status'] ?? '—'];
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--bd);">
                                        <td data-label="<?= $textbotlang['panel']['dashColProduct'] ?? 'محصول' ?>" class="cs" style="text-align:right;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="color:var(--mute); display:flex; align-items:center;"><?= icon('box', 16) ?></span>
                                                <span style="font-weight:700; color:var(--text);"><?= htmlspecialchars($inv['name_product'] ?? '—') ?></span>
                                            </div>
                                        </td>
                                        <td data-label="<?= $textbotlang['panel']['dashColAmount'] ?? 'مبلغ' ?>" class="cn desktop-text-center" style="text-align:right;">
                                            <div class="dash-unified-content mobile-flex-between" style="align-items: center; gap: 8px;">
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <span class="icon-span" style="color:var(--mute)"><?= icon('wallet', 14) ?></span>
                                                    <span class="mobile-label" style="display:none; color:var(--mute); font-weight:normal;"><?= $textbotlang['panel']['dashColAmount'] ?? 'مبلغ' ?>:</span>
                                                </div>
                                                <span class="cn" style="font-weight:600; font-size:1rem; color:var(--ac);">
                                                    <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem">تومان</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td data-label="تاریخ" class="desktop-text-center" style="text-align:right;">
                                            <div class="dash-unified-content mobile-flex-between" style="align-items: center; gap: 8px;">
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <span class="icon-span" style="color:var(--mute)"><?= icon('calendar', 14) ?></span>
                                                    <span class="mobile-label" style="display:none; color:var(--mute); font-weight:normal;">تاریخ:</span>
                                                </div>
                                                <span class="cn" style="font-weight:500; color:var(--text); display:inline-flex; align-items:center; gap:12px;">
                                                    <span><?= safe_date($inv['time_sell'] ?? null, 'Y/m/d') ?></span>
                                                    <span style="opacity:0.2; font-size:0.85em;">|</span>
                                                    <span style="opacity:0.8; font-size:0.95em;"><?= safe_date($inv['time_sell'] ?? null, 'H:i') ?></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td data-label="<?= $textbotlang['panel']['dashColStatus'] ?? 'وضعیت' ?>" style="text-align:right;">
                                            <span class="tag <?= $tagClass ?>"><?= $label ?></span>
                                        </td>
                                        <td data-label="عملیات" style="text-align:center;">
                                            <button class="btn btn-ghost btn-sm" onclick="manageService('<?= $inv['id_invoice'] ?>')" title="مدیریت سرویس">
                                                <?= icon('settings', 16) ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="panePay" style="display:none">
                <div class="tbl-wrap dash-pays">
                    <table class="tbl-md">
                        <thead>
                            <tr>
                                <th style="text-align:right;"><?= $textbotlang['panel']['dashColAmount'] ?? 'مبلغ' ?></th>
                                <th style="text-align:right;">تاریخ</th>
                                <th style="text-align:right;"><?= $textbotlang['panel']['userColPaymentMethod'] ?></th>
                                <th style="text-align:right;"><?= $textbotlang['panel']['dashColStatus'] ?></th>
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
                                    'unpaid' => ['tag-no', $textbotlang['panel']['userStatusUnpaid']],
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
                                    <tr style="border-bottom: 1px solid var(--bd);">
                                        <td data-label="<?= $textbotlang['panel']['dashColAmount'] ?? 'مبلغ' ?>" class="cn desktop-text-center" style="text-align:right;">
                                              <div class="dash-unified-content mobile-flex-between" style="align-items: center; gap: 8px;">
                                                  <div style="display:flex; align-items:center; gap:6px;">
                                                      <span class="icon-span" style="color:var(--mute)"><?= icon('wallet', 14) ?></span>
                                                      <span class="mobile-label" style="display:none; color:var(--mute); font-weight:normal;"><?= $textbotlang['panel']['dashColAmount'] ?? 'مبلغ' ?>:</span>
                                                  </div>
                                                  <span class="cn" style="font-weight:600; font-size:1rem; color:var(--ac);">
                                                      <?= number_format((int) ($p['price'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem">تومان</span>
                                                  </span>
                                              </div>
                                          </td>
                                          <td data-label="تاریخ" class="desktop-text-center" style="text-align:right;">
                                              <div class="dash-unified-content mobile-flex-between" style="align-items: center; gap: 8px;">
                                                  <div style="display:flex; align-items:center; gap:6px;">
                                                      <span class="icon-span" style="color:var(--mute)"><?= icon('calendar', 14) ?></span>
                                                      <span class="mobile-label" style="display:none; color:var(--mute); font-weight:normal;">تاریخ:</span>
                                                  </div>
                                                  <span class="cn" style="font-weight:500; color:var(--text); display:inline-flex; align-items:center; gap:12px;">
                                                      <span><?= safe_date($p['time'] ?? null, 'Y/m/d') ?></span>
                                                      <span style="opacity:0.2; font-size:0.85em;">|</span>
                                                      <span style="opacity:0.8; font-size:0.95em;"><?= safe_date($p['time'] ?? null, 'H:i') ?></span>
                                                  </span>
                                              </div>
                                          </td>
                                        <td data-label="<?= $textbotlang['panel']['userColPaymentMethod'] ?>" class="cs" style="text-align:right;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="color:var(--mute); display:flex; align-items:center;"><?= icon('credit-card', 16) ?></span>
                                                <span style="font-weight:700; color:var(--text);"><?= htmlspecialchars($method) ?></span>
                                            </div>
                                        </td>
                                        <td data-label="<?= $textbotlang['panel']['dashColStatus'] ?? 'وضعیت' ?>" style="text-align:right;">
                                            <span class="tag <?= $tagClass ?>"><?= $label ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (count($referrals) > 0): ?>
                <div id="paneRefs" style="display:none">
                    <div class="tbl-wrap dash-users">
                        <table class="tbl-md">
                            <thead>
                                <tr>
                                    <th>کاربر</th>
                                    <th>موجودی</th>
                                    <th>گروه</th>
                                    <th>تاریخ</th>
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
                                        <td data-label="کاربر">
                                            <div class="dash-unified-content" style="align-items: center;">
                                                <span class="mobile-label">نام و شناسه:</span>
                                                <div style="display:flex;align-items:center;gap:8px;">
                                                    <div class="profile-avatar" style="width:32px;height:32px;font-size:14px;"><?= mb_substr($refName ?: ($refUname ?: $ref['id']), 0, 1) ?></div>
                                                    <div style="display:flex;flex-direction:column;gap:2px;">
                                                        <a href="user.php?id=<?= (int) $ref['id'] ?>" class="cm" style="color:var(--text);font-weight:600;text-decoration:none;">
                                                            <?php if ($refName): ?>
                                                                <?= htmlspecialchars(trunc($refName, 16)) ?>
                                                            <?php elseif ($refUname): ?>
                                                                @<?= htmlspecialchars(trunc($refUname, 14)) ?>
                                                            <?php else: ?>
                                                                کاربر بی‌نام
                                                            <?php endif; ?>
                                                        </a>
                                                        <div class="profile-id-box" style="font-size:0.75rem;padding:2px 6px;margin:0;">
                                                            <span style="color:var(--ac);"><?= icon('hash', 10) ?></span>
                                                            <?= htmlspecialchars($ref['id']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="موجودی" class="cn">
                                            <div class="dash-unified-content"><span class="mobile-label">موجودی:</span>
                                                <div style="display:flex; align-items:center; gap:4px;">
                                                    <span class="cn" style="font-weight:600; font-size:1rem; color:var(--text);">
                                                        <?= number_format((int) ($ref['Balance'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem;color:var(--mute)"><?= $textbotlang['panel']['dashTomanShort'] ?? 'ت' ?></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="گروه">
                                            <div class="dash-unified-content"><span class="mobile-label">گروه:</span><span class="tag <?= user_role_tag($refAgent) ?>">
                                                <?= user_role_label($refAgent) ?>
                                            </span></div>
                                        </td>
                                        <td data-label="تاریخ" class="cf">
                                            <div class="dash-unified-content"><span class="mobile-label">تاریخ:</span><span style="color:var(--mute);"><?= safe_date($ref['register'] ?? null, 'm/d') ?></span></div>
                                        </td>
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
            <h3>افزایش موجودی</h3>
            <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add_balance">
                <div class="field">
                    <label>مبلغ افزایش (تومان)</label>
                    <input type="number" name="amount" class="input" placeholder="مبلغ به تومان وارد کنید" min="1000" required>
                    <span class="field-hint">موجودی فعلی کاربر: <strong><?= number_format($balance) ?> تومان</strong></span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> افزایش موجودی</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-veil" id="roleModal">
    <div class="modal">
        <div class="modal-head">
            <h3>تغییر گروه کاربری</h3>
            <button class="modal-x" onclick="closeModal('roleModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_role">
                <div class="field">
                    <label>انتخاب گروه</label>
                    <select name="new_role" class="select">
                        <option value="f" <?= $agent === 'f' ? 'selected' : '' ?>><?= $textbotlang['panel']['userRoleFreeUser'] ?></option>
                        <option value="n" <?= $agent === 'n' ? 'selected' : '' ?>><?= $textbotlang['panel']['userRoleNormalAgent'] ?></option>
                        <option value="n2" <?= $agent === 'n2' ? 'selected' : '' ?>><?= $textbotlang['panel']['userRoleAdvancedAgent2'] ?></option>
                    </select>
                    <span class="field-hint">
                        گروه فعلی: <strong><?= user_role_label($agent) ?></strong>
                        <span class="cm" style="color:var(--mute)">(<?= htmlspecialchars($agent) ?>)</span>
                    </span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ثبت تغییرات</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('roleModal')">انصراف</button>
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
        <form method="POST" action="">
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
        <form method="POST" action="">
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
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="transfer_account">
                <div class="field">
                    <label>شناسه کاربر مقصد</label>
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
        <form method="POST" action="">
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
        <form method="POST" action="">
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
        <form method="POST" action="">
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
        <form method="POST" action="">
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
        <form method="POST" action="">
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

<div class="modal-veil" id="serviceManageModal">
    <div class="modal">
        <div class="modal-head">
            <h3>مدیریت سرویس</h3>
            <button class="modal-x" onclick="closeModal('serviceManageModal')"><?= icon('close', 14) ?></button>
        </div>
        <div class="modal-body" id="serviceManageContent" style="min-height: 200px; display: flex; justify-content: center; align-items: center;">
            <!-- Loading Spinner -->
            <div style="text-align:center; color:var(--mute);">
                در حال دریافت اطلاعات از سرور...
            </div>
        </div>
    </div>
</div>

<script src="js/profile.js?v=<?= time() ?>"></script>
<script>
    // Fix browser back caching
    window.onpageshow = function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    };

    function manageService(invoiceId) {
        openModal('serviceManageModal');
        const contentDiv = document.getElementById('serviceManageContent');
        contentDiv.innerHTML = '<div style="text-align:center; color:var(--mute); padding: 40px 0;">در حال دریافت اطلاعات از سرور...</div>';
        
        fetch('ajax/get_service_details.php?id_user=<?= $id ?>&id_invoice=' + encodeURIComponent(invoiceId) + '&_csrf=<?= csrf_token() ?>')
            .then(response => {
                if (!response.ok && response.status !== 400 && response.status !== 404 && response.status !== 500) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                contentDiv.innerHTML = html;
            })
            .catch(error => {
                contentDiv.innerHTML = '<div style="text-align:center; color:var(--red); padding: 40px 0;">خطا در برقراری ارتباط با سرور.</div>';
                console.error('Error fetching service details:', error);
            });
    }
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
