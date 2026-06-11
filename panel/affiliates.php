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
<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
            <div class="toolbar-title">همکاری در فروش (زیرمجموعه‌ها)</div>
            <a href="settings_agents.php?tab=affiliates" class="btn btn-ghost btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
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

    <div class="tbl-wrap dash-unified">
        <table class="tbl-lg" id="affiliatesTbl">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th style="text-align: right;">معرف</th>
                    <th style="text-align: right;">تعداد زیرمجموعه‌ها</th>
                    <th style="text-align: right;">موجودی کیف پول</th>
                    <th style="text-align: center;">عملیات</th>
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
                        if ($uname === 'none') $uname = '';
                        ?>
                        <tr style="border-bottom: 1px solid var(--bd);" id="referrer-row-<?= $ref['id'] ?>">
                            <td style="text-align: center;">
                                <button class="btn btn-ghost btn-icon toggler" 
                                        onclick="toggleReferrals(<?= $ref['id'] ?>)" 
                                        style="width: 28px; height: 28px; border-radius: 6px; padding: 0; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;" 
                                        id="btn-<?= $ref['id'] ?>">
                                    <?= icon('plus', 14) ?>
                                </button>
                            </td>
                            <td data-label="معرف" style="text-align: right;">
                                <div class="dash-unified-content" style="align-items: center; display: flex; gap: 8px;">
                                    <div class="profile-avatar" style="width: 38px; height: 38px; font-size: 16px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--sf3); border: 1px solid var(--bd);">
                                        <?= mb_substr($name ?: ($uname ?: $ref['id']), 0, 1) ?>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <a href="user.php?id=<?= (int)$ref['id'] ?>" class="cm" style="color: var(--text); font-weight: 600; text-decoration: none;">
                                            <?= htmlspecialchars($name ?: ($uname ? '@' . $uname : 'کاربر بی‌نام')) ?>
                                        </a>
                                        <div class="profile-id-box" style="font-size: 0.75rem; color: var(--mute); margin: 0; display: flex; align-items: center; gap: 2px;">
                                            <span style="color: var(--ac);"><?= icon('hash', 10) ?></span>
                                            <?= htmlspecialchars($ref['id']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="تعداد زیرمجموعه‌ها" style="text-align: right;">
                                <span class="cn" style="font-weight: 700; font-size: 1.05rem;">
                                    <?= number_format((int)$ref['affiliatescount']) ?>
                                    <span class="cf" style="font-size: 0.8rem; color: var(--mute);">نفر</span>
                                </span>
                            </td>
                            <td data-label="موجودی کیف پول" style="text-align: right;">
                                <span class="cn" style="font-weight: 600; font-size: 1rem; color: var(--ac);">
                                    <?= number_format((int)($ref['Balance'] ?? 0)) ?>
                                    <span class="cf" style="font-size: 0.75rem; color: var(--mute);">تومان</span>
                                </span>
                            </td>
                            <td data-label="عملیات" style="text-align: center;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
                                    <button class="btn btn-ghost btn-sm" 
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
    const row = document.getElementById('referrer-row-' + referrerId);
    let detailsRow = document.getElementById('details-' + referrerId);

    if (detailsRow) {
        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = 'table-row';
            btn.innerHTML = `<?= icon('minus', 14) ?>`;
            btn.classList.add('active');
        } else {
            detailsRow.style.display = 'none';
            btn.innerHTML = `<?= icon('plus', 14) ?>`;
            btn.classList.remove('active');
        }
    } else {
        // Create details row
        detailsRow = document.createElement('tr');
        detailsRow.id = 'details-' + referrerId;
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
        
        btn.innerHTML = `<?= icon('minus', 14) ?>`;
        btn.classList.add('active');
        
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

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
