<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$currentUserData = db_fetch($pdo, "SELECT * FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
$isSuperAdmin = ($currentUserData && $currentUserData['rule'] === 'administrator');

// Fetch affiliates settings (status, percentage, etc.)
$affiliate_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM affiliates LIMIT 1");
    if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $affiliate_settings = $r;
    }
} catch (Exception $e) {}

$bot_setting = [];
try {
    $stmt = $pdo->query("SELECT affiliatesstatus, affiliatespercentage FROM setting LIMIT 1");
    if ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bot_setting = $r;
    }
} catch (Exception $e) {}

// Handle withdrawal actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['withdrawal_id'])) {
    csrf_check_post();
    $action = $_POST['action'];
    $w_id = (int)$_POST['withdrawal_id'];
    
    try {
        $w_req = db_fetch($pdo, "SELECT * FROM withdrawal_requests WHERE id = ?", [$w_id]);
        if ($w_req && $w_req['status'] === 'pending') {
            if ($action === 'approve') {
                $stmt = db_query($pdo, "UPDATE withdrawal_requests SET status = 'approved' WHERE id = ? AND status = 'pending'", [$w_id]);
                if ($stmt->rowCount() > 0) {
                    // Send telegram message to user
                    $msg = "✅ درخواست تسویه حساب شما به مبلغ " . number_format($w_req['amount']) . " تومان تایید و پرداخت شد.";
                    file_get_contents("https://api.telegram.org/bot{$APIKEY}/sendMessage?chat_id={$w_req['user_id']}&text=" . urlencode($msg));
                    $_SESSION['msg'] = 'درخواست تایید شد.';
                } else {
                    $_SESSION['err'] = 'خطا: درخواست قبلا توسط شخص دیگری بررسی شده است.';
                }
            } elseif ($action === 'reject') {
                $stmt = db_query($pdo, "UPDATE withdrawal_requests SET status = 'rejected' WHERE id = ? AND status = 'pending'", [$w_id]);
                if ($stmt->rowCount() > 0) {
                    // Refund to affiliate balance
                    db_query($pdo, "UPDATE user SET affiliate_balance = affiliate_balance + ? WHERE id = ?", [$w_req['amount'], $w_req['user_id']]);
                    $msg = "❌ درخواست تسویه حساب شما به مبلغ " . number_format($w_req['amount']) . " تومان رد شد و مبلغ به کیف پول پورسانت شما بازگشت داده شد.";
                    file_get_contents("https://api.telegram.org/bot{$APIKEY}/sendMessage?chat_id={$w_req['user_id']}&text=" . urlencode($msg));
                    $_SESSION['err'] = 'درخواست رد شد.';
                } else {
                    $_SESSION['err'] = 'خطا: درخواست قبلا توسط شخص دیگری بررسی شده است.';
                }
            }
        }
    } catch (Exception $e) {}
    header("Location: affiliates.php");
    exit;
}

// Fetch pending withdrawals
$pending_withdrawals = [];
try {
    $pending_withdrawals = db_fetchAll($pdo, "SELECT * FROM withdrawal_requests WHERE status = 'pending' ORDER BY time ASC");
} catch (Exception $e) {}

// Search and pagination logic
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ["affiliatescount > 0"];
$params = [];

if ($search !== '') {
    $where[] = "(id LIKE ? OR COALESCE(username,'') LIKE ? OR COALESCE(namecustom,'') LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = implode(' AND ', $where);

try {
    $total = db_count($pdo, "SELECT COUNT(*) FROM user WHERE $whereSQL", $params);
    $referrers = db_fetchAll($pdo, "SELECT * FROM user WHERE $whereSQL ORDER BY CAST(affiliatescount AS SIGNED) DESC LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
    $total = 0;
    $referrers = [];
    error_log('affiliates.php: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($total / $perPage));

// Summary counts
$totalReferrers = 0;
$totalSubMembers = 0;
try {
    $totalReferrers = db_count($pdo, "SELECT COUNT(DISTINCT affiliates) FROM user WHERE affiliates != '0' AND affiliates != ''");
    $totalSubMembers = db_count($pdo, "SELECT COUNT(*) FROM user WHERE affiliates != '0' AND affiliates != ''");
} catch (Exception $e) {}

$pageTitle = 'مدیریت همکاری در فروش (زیرمجموعه‌ها)';
$activeNav = 'affiliates';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<!-- Top Statistics Cards -->
<div class="stats fade-up" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:20px;">
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-emerald"><?= icon('users', 20) ?></div>
            <div class="dash-card-title">مبلغین فعال (معرف‌ها)</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill success">فعال</span></div>
            <div class="dash-card-value"><?= number_format($totalReferrers) ?> <small style="font-size:0.75rem; color:var(--mute)">نفر</small></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue"><?= icon('user-plus', 20) ?></div>
            <div class="dash-card-title">کل زیرمجموعه‌ها (دعوت‌شدگان)</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill info">زیرمجموعه</span></div>
            <div class="dash-card-value"><?= number_format($totalSubMembers) ?> <small style="font-size:0.75rem; color:var(--mute)">نفر</small></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-amber"><?= icon('percent', 20) ?></div>
            <div class="dash-card-title">وضعیت و درصد پورسانت</div>
        </div>
        <div class="dash-card-footer">
            <?php 
            $statusOn = (($bot_setting['affiliatesstatus'] ?? 'offaffiliates') === 'onaffiliates');
            $statusClass = $statusOn ? 'success' : 'danger';
            $statusLabel = $statusOn ? 'روشن' : 'خاموش';
            ?>
            <div class="dash-card-pill"><span class="status-pill <?= $statusClass ?>"><?= $statusLabel ?></span></div>
            <div class="dash-card-value">
                <?= (int)($bot_setting['affiliatespercentage'] ?? 0) ?>%
            </div>
        </div>
    </div>
</div>

<!-- Main Section -->
<?php if (!empty($pending_withdrawals)): ?>
<div class="card fade-up" style="margin-bottom: 20px; border-left: 4px solid var(--amber);">
    <div class="toolbar">
        <div class="toolbar-title" style="color: var(--amber);">درخواست‌های تسویه حساب پورسانت (در انتظار تایید)</div>
    </div>
    <div class="tbl-wrap">
        <table class="tbl-lg">
            <thead>
                <tr>
                    <th style="text-align: right;">آیدی کاربر</th>
                    <th style="text-align: right;">مبلغ درخواستی (تومان)</th>
                    <th style="text-align: right;">شماره کارت</th>
                    <th style="text-align: right;">تاریخ درخواست</th>
                    <th style="text-align: center;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_withdrawals as $w): ?>
                <tr>
                    <td data-label="کاربر" class="cell-mono"><?= htmlspecialchars($w['user_id']) ?></td>
                    <td data-label="مبلغ" style="color: var(--emerald); font-weight: bold;"><?= number_format($w['amount']) ?></td>
                    <td data-label="شماره کارت" style="font-family: monospace; letter-spacing: 1px;"><?= htmlspecialchars($w['card_number']) ?></td>
                    <td data-label="تاریخ" style="color: var(--mute);"><?= htmlspecialchars($w['created_at']) ?></td>
                    <td data-label="عملیات" style="text-align: center;">
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('آیا از تایید این درخواست و واریز مبلغ اطمینان دارید؟');">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="background: rgba(16,185,129,0.1); color: var(--emerald);">تایید و پرداخت شد</button>
                        </form>
                        <form method="POST" style="display:inline-block; margin-right: 5px;" onsubmit="return confirm('آیا از رد این درخواست اطمینان دارید؟ مبلغ به حساب کاربر برگشت داده می‌شود.');">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" style="color: var(--rose);">رد درخواست</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
            <div class="toolbar-title">همکاری در فروش (زیرمجموعه‌ها)</div>
            <a href="settings_affiliates.php" class="btn btn-ghost btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
                <?= icon('settings', 14) ?> تنظیمات پورسانت
            </a>
        </div>
        <form method="GET" class="toolbar-end">
            <div class="search-box" style="min-width:260px">
                <?= icon('search', 15) ?>
                <input type="text" name="q" placeholder="جستجو در شناسه، نام کاربری..."
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <?php if ($search): ?>
                    <button type="button" class="search-clear" onclick="window.location='affiliates.php'">✕</button>
                <?php endif; ?>
                <button type="submit" class="search-btn">جستجو</button>
            </div>
        </form>
    </div>

    <div class="tbl-wrap">
        <table class="tbl-lg" id="affiliatesTbl">
            <thead>
                <tr>
                    <th style="width: 48px; min-width: 48px; text-align: center; padding: 14px 8px;"></th>
                    <th style="text-align: right; padding: 14px 8px;">معرف</th>
                    <th style="text-align: right; padding: 14px 8px;">تعداد زیرمجموعه‌ها</th>
                    <th style="text-align: right; padding: 14px 8px;">موجودی کیف پول</th>
                    <th style="text-align: center; padding: 14px 8px;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($referrers)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty" style="padding:48px 20px">
                                <svg class="ill" viewBox="0 0 200 160" fill="none" style="width: 120px; height: 90px; margin-bottom: 12px; opacity: 0.7;">
                                    <circle cx="100" cy="60" r="40" fill="var(--sf3)" />
                                    <circle cx="100" cy="47" r="18" fill="var(--bd)" />
                                    <path d="M62 105 Q100 88 138 105" stroke="var(--bd)" stroke-width="8" stroke-linecap="round" fill="none" />
                                </svg>
                                <p>هیچ کاربری با ویژگی‌های مورد نظر دارای زیرمجموعه نیست.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($referrers as $ref): 
                        $name = $ref['namecustom'] ?? '';
                        if ($name === 'none') $name = '';
                        $uname = $ref['username'] ?? '';
                        if ($uname === 'none' || $uname === 'NOT_USERNAME') $uname = '';
                        ?>
                        <tr style="border-bottom: 1px solid var(--bd);" id="referrer-row-<?= $ref['id'] ?>">
                            <td class="no-label" style="width: 48px; min-width: 48px; text-align: center; padding: 14px 8px; vertical-align: middle;">
                                <button class="btn btn-ghost btn-icon toggler" 
                                        onclick="toggleReferrals(<?= $ref['id'] ?>)" 
                                        style="width: 28px; height: 28px; border-radius: 6px; padding: 0; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;" 
                                        id="btn-<?= $ref['id'] ?>">
                                    <?= icon('plus', 14) ?>
                                </button>
                            </td>
                            <td data-label="معرف" style="text-align: right; padding: 14px 8px; vertical-align: middle;">
                                <div class="dash-unified-content" style="align-items: center; display: flex; gap: 10px; direction: rtl; justify-content: flex-start; width: 100%; min-width: 0;">
                                    <div class="profile-avatar" style="width: 38px; height: 38px; font-size: 16px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--sf3); border: 1px solid var(--bd); flex-shrink: 0;">
                                        <?= mb_substr($name ?: ($uname ?: $ref['id']), 0, 1) ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; text-align: right; align-items: flex-start; overflow: hidden;">
                                        <a href="user.php?id=<?= (int)$ref['id'] ?>" class="username-link" style="color: var(--text); font-weight: 600; text-decoration: none; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block;">
                                            <?= htmlspecialchars($name ?: ($uname ? '@' . $uname : 'کاربر بی‌نام')) ?>
                                        </a>
                                        <div class="profile-id-box" style="font-size: 0.75rem; color: var(--mute); margin: 0; display: flex; align-items: center; gap: 2px;">
                                            <span style="color: var(--ac);"><?= icon('hash', 10) ?></span>
                                            <?= htmlspecialchars($ref['id']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="تعداد زیرمجموعه‌ها" style="text-align: right; padding: 14px 8px; vertical-align: middle;">
                                <span class="cn" style="font-weight: 700; font-size: 1.05rem;">
                                    <?= number_format((int)$ref['affiliatescount']) ?>
                                    <span class="cf" style="font-size: 0.8rem; color: var(--mute);">نفر</span>
                                </span>
                            </td>
                            <td data-label="موجودی کیف پول" style="text-align: right; padding: 14px 8px; vertical-align: middle;">
                                <span class="cn" style="font-weight: 600; font-size: 1rem; color: var(--ac);">
                                    <?= number_format((int)($ref['affiliate_balance'] ?? 0)) ?>
                                    <span class="cf" style="font-size: 0.75rem; color: var(--mute);">تومان</span>
                                </span>
                            </td>
                            <td data-label="عملیات" style="text-align: center; padding: 14px 8px; vertical-align: middle;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
                                    <button class="btn btn-ghost btn-sm" 
                                            id="btn-view-<?= $ref['id'] ?>"
                                            onclick="toggleReferrals(<?= $ref['id'] ?>)" 
                                            style="padding: 6px 14px; font-weight: 600;">
                                        <?= icon('eye', 14) ?> مشاهده زیرمجموعه‌ها
                                    </button>
                                    <a href="user_action.php?action=removeaffiliates&id=<?= (int)$ref['id'] ?>&_csrf=<?= csrf_token() ?>&back=affiliates.php" 
                                       class="btn btn-no btn-sm" 
                                       style="padding: 6px 14px; font-weight: 600;"
                                       data-confirm="آیا از لغو کامل تمام زیرمجموعه‌های این کاربر مطمئن هستید؟">
                                        <?= icon('trash', 14) ?> حذف کل زیرمجموعه‌ها
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="tbl-foot">
            <span><?= number_format($total) ?> کاربر پیدا شد • صفحه <?= $page ?> از <?= $totalPages ?></span>
            <div class="pager">
                <?php $qs = fn($p) => '?q=' . urlencode($search) . '&page=' . $p; ?>
                <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleReferrals(referrerId) {
    const btn = document.getElementById('btn-' + referrerId);
    const viewBtn = document.getElementById('btn-view-' + referrerId);
    const row = document.getElementById('referrer-row-' + referrerId);
    let detailsRow = document.getElementById('details-' + referrerId);

    if (detailsRow) {
        if (detailsRow.classList.contains('d-none')) {
            detailsRow.classList.remove('d-none');
            if (row) row.classList.add('expanded');
            if (btn) {
                btn.innerHTML = `<?= icon('minus', 14) ?>`;
                btn.classList.add('active');
            }
            if (viewBtn) {
                viewBtn.innerHTML = `<?= icon('eye-off', 14) ?> بستن زیرمجموعه‌ها`;
                viewBtn.classList.add('btn-active');
            }
        } else {
            detailsRow.classList.add('d-none');
            if (row) row.classList.remove('expanded');
            if (btn) {
                btn.innerHTML = `<?= icon('plus', 14) ?>`;
                btn.classList.remove('active');
            }
            if (viewBtn) {
                viewBtn.innerHTML = `<?= icon('eye', 14) ?> مشاهده زیرمجموعه‌ها`;
                viewBtn.classList.remove('btn-active');
            }
        }
    } else {
        // Create details row
        detailsRow = document.createElement('tr');
        detailsRow.id = 'details-' + referrerId;
        detailsRow.className = 'details-row';
        detailsRow.style.borderBottom = '1px solid var(--bd)';
        detailsRow.style.background = 'rgba(0,0,0,0.01)';
        
        const td = document.createElement('td');
        td.colSpan = 5;
        td.style.padding = '12px 24px';
        
        // Loader Spinner (Premium SVG Animation)
        td.innerHTML = `
            <div style="display:flex; align-items:center; justify-content:center; gap:8px; padding:16px; color:var(--mute); font-size:0.85rem;">
                <svg width="20" height="20" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                    <g fill="none" fill-rule="evenodd">
                        <g transform="translate(1 1)" stroke-width="3">
                            <circle stroke-opacity=".2" cx="18" cy="18" r="18"/>
                            <path d="M36 18c0-9.94-8.06-18-18-18">
                                <animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.9s" repeatCount="indefinite"/>
                            </path>
                        </g>
                    </g>
                </svg>
                <span>در حال بارگذاری لیست زیرمجموعه‌ها...</span>
            </div>
        `;
        
        detailsRow.appendChild(td);
        row.parentNode.insertBefore(detailsRow, row.nextSibling);
        if (row) row.classList.add('expanded');
        
        if (btn) {
            btn.innerHTML = `<?= icon('minus', 14) ?>`;
            btn.classList.add('active');
        }
        if (viewBtn) {
            viewBtn.innerHTML = `<?= icon('eye-off', 14) ?> بستن زیرمجموعه‌ها`;
            viewBtn.classList.add('btn-active');
        }
        
        // Fetch sub-members list
        fetch('ajax/get_referrals.php?id=' + referrerId)
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.text();
            })
            .then(html => {
                td.innerHTML = html;
            })
            .catch(err => {
                td.innerHTML = `
                    <div style="text-align:center; padding:12px; color:var(--red); font-size:0.85rem; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <?= icon('x-circle', 14) ?> خطا در بارگذاری اطلاعات زیرمجموعه‌ها. لطفاً مجدداً تلاش کنید.
                    </div>
                `;
            });
    }
}
</script>

<style>
/* ==========================================
   DESKTOP COLUMN ALIGNMENTS (min-width: 769px)
   ========================================== */
@media (min-width: 769px) {
    /* Main table headers */
    #affiliatesTbl > thead > tr > th:nth-child(1) { width: 48px; min-width: 48px; text-align: center; }
    #affiliatesTbl > thead > tr > th:nth-child(2) { width: 35%; }
    #affiliatesTbl > thead > tr > th:nth-child(3) { width: 20%; }
    #affiliatesTbl > thead > tr > th:nth-child(4) { width: 20%; }
    #affiliatesTbl > thead > tr > th:nth-child(5) { width: 25%; }

    /* Main table body rows */
    #affiliatesTbl > tbody > tr[id^="referrer-row-"] > td:nth-child(1) { width: 48px; min-width: 48px; text-align: center; }
    #affiliatesTbl > tbody > tr[id^="referrer-row-"] > td:nth-child(2) { width: 35%; }
    #affiliatesTbl > tbody > tr[id^="referrer-row-"] > td:nth-child(3) { width: 20%; }
    #affiliatesTbl > tbody > tr[id^="referrer-row-"] > td:nth-child(4) { width: 20%; }
    #affiliatesTbl > tbody > tr[id^="referrer-row-"] > td:nth-child(5) { width: 25%; }

    /* Nested table columns */
    #affiliatesTbl tbody tr[id^="details-"] table th:nth-child(1), 
    #affiliatesTbl tbody tr[id^="details-"] table td:nth-child(1) { width: 48px; min-width: 48px; text-align: center; }
    #affiliatesTbl tbody tr[id^="details-"] table th:nth-child(2), 
    #affiliatesTbl tbody tr[id^="details-"] table td:nth-child(2) { width: 35%; }
    #affiliatesTbl tbody tr[id^="details-"] table th:nth-child(3), 
    #affiliatesTbl tbody tr[id^="details-"] table td:nth-child(3) { width: 20%; }
    #affiliatesTbl tbody tr[id^="details-"] table th:nth-child(4), 
    #affiliatesTbl tbody tr[id^="details-"] table td:nth-child(4) { width: 20%; }
    #affiliatesTbl tbody tr[id^="details-"] table th:nth-child(5), 
    #affiliatesTbl tbody tr[id^="details-"] table td:nth-child(5) { width: 25%; }
}

/* ==========================================
   MOBILE LAYOUT & CARD CUSTOMIZATIONS (max-width: 768px)
   ========================================== */
@media (max-width: 768px) {
    /* 1. Statistics Cards Optimization */
    .stats {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    .stats > * {
        grid-column: auto !important;
    }
    
    /* 2. Toolbar layout for mobile */
    .toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
        padding: 16px 12px !important;
    }
    .toolbar > div {
        width: 100% !important;
        justify-content: space-between !important;
        gap: 8px;
    }
    .toolbar-end {
        width: 100% !important;
    }
    .search-box {
        width: 100% !important;
        min-width: 100% !important;
    }

    /* 3. Table styling as card list for mobile */
    #affiliatesTbl thead {
        display: none !important;
    }
    #affiliatesTbl, #affiliatesTbl tbody {
        display: block !important;
        width: 100% !important;
    }
    #affiliatesTbl tbody tr[id^="referrer-row-"] {
        display: flex !important;
        flex-direction: column !important;
        background: var(--sf) !important;
        border: 1px solid var(--bd) !important;
        border-radius: 12px !important;
        padding: 16px !important;
        margin-bottom: 16px !important;
        gap: 12px !important;
        position: relative !important;
        box-shadow: var(--sh) !important;
        width: 100% !important;
    }
    
    /* Toggler button absolute positioning inside card */
    #affiliatesTbl tbody tr[id^="referrer-row-"] td:first-child {
        display: block !important;
        position: absolute !important;
        top: 16px !important;
        left: 16px !important;
        border: none !important;
        padding: 0 !important;
        width: 32px !important;
        height: 32px !important;
        z-index: 10 !important;
    }
    #affiliatesTbl tbody tr[id^="referrer-row-"] td:first-child button {
        width: 32px !important;
        height: 32px !important;
        background: var(--sf2) !important;
        border: 1px solid var(--bd) !important;
    }
    
    /* Style all other TDs to be flex rows with labels */
    #affiliatesTbl tbody tr[id^="referrer-row-"] td:not(:first-child) {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 0 !important;
        border-bottom: none !important;
        text-align: right !important;
        width: 100% !important;
    }
    
    /* Inject the labels from data-label */
    #affiliatesTbl tbody tr[id^="referrer-row-"] td:not(:first-child)::before {
        content: attr(data-label) ": ";
        font-weight: 700 !important;
        color: var(--mute) !important;
        font-size: 0.82rem !important;
    }
    
    /* Particular style for Referrer (معرف) to align avatar and info nicely */
    #affiliatesTbl tbody tr[id^="referrer-row-"] td[data-label="معرف"] {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 8px !important;
        padding-bottom: 12px !important;
        border-bottom: 1px solid var(--bd) !important;
    }
    #affiliatesTbl tbody tr[id^="referrer-row-"] td[data-label="معرف"]::before {
        display: none !important;
    }
    #affiliatesTbl tbody tr[id^="referrer-row-"] td[data-label="معرف"] .dash-unified-content {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        text-align: right !important;
        gap: 10px !important;
        width: 100% !important;
        padding-left: 48px !important; /* Make room for the absolute toggler on the left */
        padding-right: 0 !important;
    }
    #affiliatesTbl tbody tr[id^="referrer-row-"] td[data-label="معرف"] .dash-unified-content a.username-link {
        max-width: 140px !important;
    }
    
    /* Styling the Operations (عملیات) td */
    #affiliatesTbl tbody tr[id^="referrer-row-"] td[data-label="عملیات"] {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
        margin-top: 8px !important;
        padding-top: 12px !important;
        border-top: 1px solid var(--bd) !important;
    }
    #affiliatesTbl tbody tr[id^="referrer-row-"] td[data-label="عملیات"]::before {
        display: none !important;
    }
    #affiliatesTbl tbody tr[id^="referrer-row-"] td[data-label="عملیات"] .btn {
        width: 100% !important;
        justify-content: center !important;
    }
    
    /* Expanded states for referrer rows to merge with details card */
    #affiliatesTbl tbody tr[id^="referrer-row-"].expanded {
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
        margin-bottom: 0 !important;
        border-bottom: none !important;
        box-shadow: none !important;
    }

    /* 4. Sub-members (جزئیات) row on mobile */
    #affiliatesTbl tbody tr[id^="details-"] {
        display: block; /* No !important to allow d-none override */
        background: var(--sf) !important;
        border-left: 1px solid var(--bd) !important;
        border-right: 1px solid var(--bd) !important;
        border-bottom: 1px solid var(--bd) !important;
        border-top: none !important;
        border-bottom-left-radius: 14px !important;
        border-bottom-right-radius: 14px !important;
        border-top-left-radius: 0 !important;
        border-top-right-radius: 0 !important;
        margin-top: 0 !important;
        margin-bottom: 16px !important;
        padding: 0 16px 16px 16px !important;
        width: 100% !important;
        box-shadow: 0 6px 16px rgba(0,0,0,0.12) !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] td {
        display: block !important;
        padding: 0 !important;
        width: 100% !important;
        border: none !important;
    }
    
    /* Nested table styles */
    #affiliatesTbl tbody tr[id^="details-"] td .tbl-wrap {
        display: block !important;
        width: 100% !important;
        margin: 10px 0 !important;
        padding: 8px !important;
        background: var(--sf2) !important;
        border: 1px solid var(--bd) !important;
        border-radius: 8px !important;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02) !important;
        overflow-x: hidden !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table {
        min-width: 100% !important;
        width: 100% !important;
        display: block !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table thead {
        display: none !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody {
        display: block !important;
        width: 100% !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr {
        display: flex !important;
        flex-direction: column !important;
        background: var(--sf) !important;
        border: 1px solid var(--bd) !important;
        border-radius: 10px !important;
        padding: 12px !important;
        margin-bottom: 8px !important;
        gap: 10px !important;
        width: 100% !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr:last-child {
        margin-bottom: 0 !important;
    }
    
    /* Hide the empty sub-table placeholder column on mobile */
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td:first-child {
        display: none !important;
    }
    
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 10px 12px !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        text-align: right !important;
        width: 100% !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td::before {
        content: attr(data-label) ": ";
        font-weight: 700 !important;
        color: var(--mute) !important;
        font-size: 0.78rem !important;
    }
    
    /* Inner table user column */
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td[data-label="زیرمجموعه"] {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 6px !important;
        padding-bottom: 8px !important;
        border-bottom: 1px solid var(--bd) !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td[data-label="زیرمجموعه"]::before {
        display: none !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td[data-label="زیرمجموعه"] .dash-unified-content {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        text-align: right !important;
        gap: 10px !important;
        width: 100% !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td[data-label="زیرمجموعه"] .dash-unified-content a.username-link {
        max-width: 140px !important;
    }
    
    /* Inner table operations column */
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td[data-label="عملیات"] {
        margin-top: 4px !important;
        padding-top: 8px !important;
        border-top: 1px solid var(--bd) !important;
        border-bottom: none !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td[data-label="عملیات"]::before {
        display: none !important;
    }
    #affiliatesTbl tbody tr[id^="details-"] table tbody tr td[data-label="عملیات"] .btn {
        width: 100% !important;
        justify-content: center !important;
    }
}
</style>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
