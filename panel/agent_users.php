<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';

if (!isset($_SESSION['agent_id'])) {
    header("Location: agent_login.php");
    exit;
}

$agent_id = $_SESSION['agent_id'];

// Get agent name and type
$stmt = $pdo->prepare("SELECT namecustom, agent FROM user WHERE id = :id");
$stmt->execute([':id' => $agent_id]);
$agentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
$agentUsername = !empty($agentUserRow['namecustom']) ? $agentUserRow['namecustom'] : 'نماینده ' . $agent_id;
$agentType = $agentUserRow['agent'] ?? 'n'; // e.g., 'n', 'n2', 'all'

$initials = mb_strtoupper(mb_substr($agentUsername, 0, 1, 'UTF-8'), 'UTF-8');

// Fetch allowed panels
$stmtPanel = $pdo->prepare("SELECT * FROM marzban_panel WHERE agent = :agent OR agent = 'all'");
$stmtPanel->execute([':agent' => $agentType]);
$allowedPanels = $stmtPanel->fetchAll(PDO::FETCH_ASSOC);

// Fetch allowed products
$stmtProduct = $pdo->prepare("SELECT * FROM product WHERE (agent = :agent OR agent = 'all')");
$stmtProduct->execute([':agent' => $agentType]);
$allowedProducts = $stmtProduct->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل نمایندگی - مدیریت کاربران</title>
    <link rel="stylesheet" href="css/agent_users.css">
</head>
<body class="agent-panel-body">

    <!-- Mobile Header & Toggle -->
    <div class="au-mobile-toggle" style="padding: 16px 20px; background: var(--au-surface); border-bottom: 1px solid var(--au-border); align-items: center; justify-content: space-between; position: fixed; top: 0; left: 0; right: 0; z-index: 90;">
        <div style="font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 10px;">
            <button id="au-mobile-toggle" class="au-btn-icon" style="border: none; background: transparent; padding: 0;">
                <?= icon('menu', 24) ?>
            </button>
            <span>پنل نمایندگی</span>
        </div>
        <div>
            <a href="ajax/agent_auth.php?action=logout" class="au-btn-icon" style="border: none; background: transparent; color: var(--au-danger); text-decoration: none; display: flex;" title="خروج">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </div>

    <!-- Sidebar -->
    <aside class="au-sidebar" id="au-sidebar">
        <div class="au-sidebar-header">
            <div class="au-avatar"><?= $initials ?></div>
            <div class="au-sidebar-title">
                <strong>پنل نمایندگی</strong>
                <span><?= $agentUsername ?></span>
            </div>
        </div>
        <nav class="au-nav">
            <a href="agent_users.php" class="au-nav-item">
                <?= icon('dashboard', 18) ?> داشبورد
            </a>
            <a href="agent_users.php" class="au-nav-item active">
                <?= icon('users', 18) ?> مدیریت کاربران
            </a>
            <a href="javascript:void(0)" onclick="alert('این بخش به زودی فعال می‌شود');" class="au-nav-item">
                <?= icon('activity', 18) ?> لاگ عملیات
            </a>
            <a href="javascript:void(0)" onclick="alert('این بخش به زودی فعال می‌شود');" class="au-nav-item">
                <?= icon('database', 18) ?> مستندات API
            </a>
            <a href="javascript:void(0)" onclick="alert('این بخش به زودی فعال می‌شود');" class="au-nav-item">
                <?= icon('settings', 18) ?> تنظیمات
            </a>
        </nav>
        <div class="au-sidebar-footer">
            <a href="#" class="au-nav-item">
                <?= icon('user', 16) ?> حساب نماینده
            </a>
            <a href="#" class="au-nav-item">
                <?= icon('shield', 16) ?> نشست فعال
            </a>
            <a href="ajax/agent_auth.php?action=logout" class="au-nav-item" style="color: var(--au-danger);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> 
                خروج از حساب
            </a>
            <a href="#" class="au-nav-item" style="color: var(--au-text-muted);" onclick="document.getElementById('au-sidebar').classList.remove('open')">
                <?= icon('arrow-left', 16) ?> جمع کردن منو
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="au-main" style="margin-top: calc(var(--au-mobile-offset, 0px));">
        
        <div class="au-header">
            <div class="au-header-text">
                <h1>مدیریت کاربران</h1>
                <p>ساخت، نمایش و حذف کاربران با لوکیشن، حجم و مدت به‌صورت ساده</p>
            </div>
            <div class="au-header-actions">
                <button class="au-btn au-btn-icon" title="بروزرسانی">
                    <?= icon('refresh-cw', 18) ?>
                </button>
                <button class="au-btn au-btn-primary">
                    <?= icon('plus', 16) ?> ساخت کاربر
                </button>
            </div>
        </div>

        <div class="au-toolbar">
            <div class="au-search">
                <?= icon('search', 16) ?>
                <input type="text" id="au-search-input" placeholder="جستجوی کاربر یا لوکیشن...">
            </div>
            <select class="au-select">
                <option>وضعیت (همه)</option>
                <option>فعال</option>
                <option>منقضی</option>
            </select>
            <select class="au-select">
                <option>وضعیت اتصال (همه)</option>
                <option>آنلاین</option>
                <option>آفلاین</option>
            </select>
            <button class="au-btn au-btn-icon" title="مرتب سازی">
                <?= icon('sliders', 18) ?>
            </button>
        </div>

        <div class="au-users-list" id="au-users-container">
            <div style="text-align:center; padding: 40px; color: var(--au-text-muted);">
                <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
                <p style="margin-top: 15px;">در حال بارگذاری کاربران...</p>
            </div>
        </div>

        <div class="au-pagination" id="au-pagination-container">
            <!-- Pagination items will be injected here -->
        </div>

    </main>

    <!-- Create User Modal -->
    <div id="create-modal" class="au-modal">
        <div class="au-modal-content">
            <div class="au-modal-header">
                <h2>ساخت کاربر جدید</h2>
                <button class="au-btn-icon" onclick="closeModal('create-modal')"><?= icon('x', 20) ?></button>
            </div>
            <div class="au-modal-body">
                <div class="au-form-group">
                    <label>سرور (پنل)</label>
                    <select id="create-location" class="au-select" style="width: 100%; margin-bottom: 15px;" onchange="updateProductsList('create-location', 'create-product')">
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach($allowedPanels as $p): ?>
                            <option value="<?= htmlspecialchars($p['code_panel']) ?>"><?= htmlspecialchars($p['name_panel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="au-form-group">
                    <label>سرویس (پلن)</label>
                    <select id="create-product" class="au-select" style="width: 100%; margin-bottom: 15px;">
                        <option value="">ابتدا سرور را انتخاب کنید</option>
                    </select>
                </div>
                <div class="au-form-group">
                    <label>نام کاربری (اختیاری - انگلیسی)</label>
                    <input type="text" id="create-username" class="au-select" style="width: 100%; margin-bottom: 15px;" placeholder="مثال: ali_123" dir="ltr">
                </div>
                <div id="create-error" style="color: var(--au-danger); font-size: 0.9rem; margin-bottom: 10px; display: none;"></div>
            </div>
            <div class="au-modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--au-border); display: flex; justify-content: flex-end; gap: 10px;">
                <button class="au-btn" onclick="closeModal('create-modal')">انصراف</button>
                <button class="au-btn au-btn-primary" id="btn-submit-create" onclick="submitCreateUser()">ساخت و کسر از حساب</button>
            </div>
        </div>
    </div>

    <!-- Renew User Modal -->
    <div id="renew-modal" class="au-modal">
        <div class="au-modal-content">
            <div class="au-modal-header">
                <h2>تمدید سرویس</h2>
                <button class="au-btn-icon" onclick="closeModal('renew-modal')"><?= icon('x', 20) ?></button>
            </div>
            <div class="au-modal-body">
                <p style="margin-bottom: 15px;">کاربر: <strong id="renew-username-lbl"></strong> (سرور: <span id="renew-location-lbl"></span>)</p>
                <input type="hidden" id="renew-invoice-id">
                <input type="hidden" id="renew-location-val">
                <div class="au-form-group">
                    <label>انتخاب سرویس جدید</label>
                    <select id="renew-product" class="au-select" style="width: 100%; margin-bottom: 15px;">
                    </select>
                </div>
                <div id="renew-error" style="color: var(--au-danger); font-size: 0.9rem; margin-bottom: 10px; display: none;"></div>
            </div>
            <div class="au-modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--au-border); display: flex; justify-content: flex-end; gap: 10px;">
                <button class="au-btn" onclick="closeModal('renew-modal')">انصراف</button>
                <button class="au-btn au-btn-primary" id="btn-submit-renew" onclick="submitRenewUser()">تمدید سرویس</button>
            </div>
        </div>
    </div>

    <!-- Config Links Modal -->
    <div id="link-modal" class="au-modal">
        <div class="au-modal-content">
            <div class="au-modal-header">
                <h2>لینک اشتراک</h2>
                <button class="au-btn-icon" onclick="closeModal('link-modal')"><?= icon('x', 20) ?></button>
            </div>
            <div class="au-modal-body">
                <div id="link-content" style="background: var(--au-background); padding: 15px; border-radius: 8px; word-break: break-all; font-family: monospace; font-size: 0.85rem; line-height: 1.5; text-align: left; direction: ltr; max-height: 200px; overflow-y: auto; user-select: all;">
                    در حال دریافت...
                </div>
                <button class="au-btn au-btn-primary" style="width: 100%; margin-top: 15px; justify-content: center;" onclick="copyConfigLink()">کپی کردن</button>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="details-modal" class="au-modal">
        <div class="au-modal-content" style="max-width: 500px;">
            <div class="au-modal-header">
                <h2>جزئیات اشتراک</h2>
                <button class="au-btn-icon" onclick="closeModal('details-modal')"><?= icon('x', 20) ?></button>
            </div>
            <div class="au-modal-body">
                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--au-border); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 15px;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px;">
                        <span style="color: var(--au-text-muted); font-size: 0.9rem;">نام کاربری</span>
                        <strong id="det-username" style="font-size: 1.1rem; color: var(--au-primary);">...</strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--au-text-muted); font-size: 0.9rem;">لوکیشن (سرور)</span>
                        <span id="det-location" style="background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem;">...</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--au-text-muted); font-size: 0.9rem;">وضعیت اتصال</span>
                        <span id="det-connection" style="font-size: 0.85rem;">...</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--au-text-muted); font-size: 0.9rem;">وضعیت اکانت</span>
                        <span id="det-status" style="font-size: 0.85rem;">...</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px;">
                        <span style="color: var(--au-text-muted); font-size: 0.9rem;">ترافیک مصرفی / کل</span>
                        <div style="text-align: left;">
                            <strong id="det-usage-used">...</strong> <span style="color: var(--au-text-muted); font-size: 0.85rem;" id="det-usage-limit">از ...</span>
                        </div>
                    </div>

                    <!-- Progress Bar for Details -->
                    <div class="au-progress-track" style="height: 8px;">
                        <div class="au-progress-fill" id="det-progress-fill" style="width: 0%;">
                            <div class="au-progress-knob"></div>
                        </div>
                    </div>
                    <div style="text-align: left; margin-top: -10px;"><span id="det-usage-pct" style="font-size: 0.8rem; color: var(--au-text-muted);">٪۰</span></div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px;">
                        <span style="color: var(--au-text-muted); font-size: 0.9rem;">تاریخ ساخت</span>
                        <span id="det-created" style="font-size: 0.9rem;">...</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--au-text-muted); font-size: 0.9rem;">تاریخ انقضا</span>
                        <div style="text-align: left;">
                            <span id="det-expired" style="font-size: 0.9rem;">...</span>
                            <div id="det-rem-days" style="font-size: 0.75rem; color: var(--au-success);">...</div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="au-modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--au-border); display: flex; justify-content: flex-end; gap: 10px;">
                <button class="au-btn" onclick="closeModal('details-modal')">بستن</button>
            </div>
        </div>
    </div>

    <script>
        const RAW_PRODUCTS = <?= json_encode($allowedProducts) ?>;
        
        // Modal functions
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        // Data Fetching Logic
        let currentPage = 1;
        let currentSearch = '';

        async function loadUsers(page = 1) {
            currentPage = page;
            const container = document.getElementById('au-users-container');
            const pagination = document.getElementById('au-pagination-container');
            
            container.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--au-text-muted);"><i class="fa-solid fa-circle-notch fa-spin fa-2x"></i><p style="margin-top: 15px;">در حال بارگذاری کاربران...</p></div>';

            try {
                const res = await fetch(`ajax/agent_users_data.php?action=get_users&page=${page}&search=${encodeURIComponent(currentSearch)}`);
                const json = await res.json();
                
                if (json.status !== 'success') {
                    container.innerHTML = `<div style="text-align:center; padding: 40px; color: var(--au-danger);">${json.message || 'خطا در دریافت اطلاعات'}</div>`;
                    return;
                }

                const users = json.data;
                const pageInfo = json.pagination;

                if (users.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--au-text-muted);">هیچ کاربری یافت نشد.</div>';
                    pagination.innerHTML = '';
                    return;
                }

                let html = '';
                users.forEach(user => {
                    html += `
                    <div class="au-card" id="user-card-${user.id}" onclick="openUserDetails(${user.id})" style="cursor: pointer;">
                        <!-- ۱. نام کاربری و لوکیشن و تقویم -->
                        <div class="au-col au-col-meta">
                            <div class="au-user-title">
                                <span class="au-username">${user.username}</span>
                                <span class="au-online-indicator offline" id="online-indicator-${user.id}"></span>
                            </div>
                            <div class="au-meta-sub">
                                <span class="au-badge-location">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    ${user.location}
                                </span>
                                <span class="au-meta-date">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    ساخته شده: ${toPersianDigits(user.created_at)}
                                </span>
                            </div>
                        </div>

                        <!-- ۲. وضعیت فعال/غیرفعال و آنلاین/آفلاین و انقضا -->
                        <div class="au-col au-col-status">
                            <div class="au-badges-row">
                                <span class="au-badge-status ${user.status === 'active' ? 'active' : 'inactive'}" id="status-badge-${user.id}">
                                    ${user.status_label}
                                </span>
                                <span class="au-badge-connection offline" id="connection-badge-${user.id}">
                                    <span class="dot">•</span> <span class="label">در حال بررسی...</span>
                                </span>
                            </div>
                            <div class="au-expiry-text" id="expiry-text-${user.id}">
                                پایان: ${user.expires_at} (${toPersianDigits(user.rem_days)} روز)
                            </div>
                        </div>

                        <!-- ۳. میزان مصرف داده و نوار پیشرفت خطی -->
                        <div class="au-col au-col-usage">
                            <div class="au-usage-top">
                                <span class="au-usage-pct" id="usage-pct-${user.id}">...</span>
                                <span class="au-usage-lbl">مصرف <i class="fa-solid fa-circle-info" style="font-size: 0.75rem; opacity: 0.5;"></i></span>
                            </div>
                            <div class="au-progress-track">
                                <div class="au-progress-fill" id="progress-fill-${user.id}" style="width: 0%;">
                                    <div class="au-progress-knob"></div>
                                </div>
                            </div>
                            <div class="au-usage-bottom">
                                <span class="au-usage-limit" id="usage-limit-${user.id}">از ${toPersianDigits(user.total_gb)}</span>
                                <span class="au-usage-used" id="usage-used-${user.id}">...</span>
                            </div>
                        </div>

                        <!-- ۴. دکمه‌های عملیات سریع -->
                        <div class="au-col au-col-actions">
                            <button class="au-btn-circle-action" onclick="openLinkModal(${user.id}); event.stopPropagation();" title="کپی لینک اشتراک">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                            </button>
                            
                            <div style="position: relative;">
                                <button class="au-btn-circle-action au-btn-dropdown" data-target="dropdown-${user.id}" title="بیشتر">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                                </button>
                                
                                <div class="au-dropdown" id="dropdown-${user.id}">
                                    <a href="#" class="au-dropdown-item" onclick="openLinkModal(${user.id}); return false;"><i class="fa-solid fa-link"></i> لینک اشتراک</a>
                                    <a href="#" class="au-dropdown-item" onclick="openRenewModal(${user.id}, '${user.username}', '${user.location}'); return false;"><i class="fa-solid fa-rotate-right"></i> تمدید سرویس</a>
                                    <a href="#" class="au-dropdown-item danger" onclick="deleteUser(${user.id}); return false;"><i class="fa-solid fa-trash"></i> حذف کاربر</a>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });

                // Render all users first
                container.innerHTML = html;

                // Re-bind dropdowns
                bindDropdowns();

                // Render pagination
                renderPagination(pageInfo.page, pageInfo.total_pages);

                // Then load live stats sequentially to prevent server overload
                for (const user of users) {
                    await fetchLiveStats(user.id);
                }

            } catch (err) {
                container.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--au-danger);">خطای ارتباط با سرور</div>';
            }
        }

        async function fetchLiveStats(id) {
            try {
                const res = await fetch(`ajax/agent_users_data.php?action=get_user_live&id=${id}`);
                const json = await res.json();
                
                if (json.status !== 'success') {
                    const connBadge = document.getElementById(`connection-badge-${id}`);
                    if (connBadge) {
                        connBadge.innerHTML = `<span class="dot">•</span> <span class="label">خطا</span>`;
                    }
                    return;
                }
                
                const data = json.live;
                
                // 1. Update Connection Status Badge
                const connBadge = document.getElementById(`connection-badge-${id}`);
                if (connBadge) {
                    connBadge.className = `au-badge-connection ${data.is_online}`;
                    connBadge.innerHTML = `<span class="dot">•</span> <span class="label">${data.online_label}</span>`;
                }
                
                // 2. Update Username Online Indicator Dot
                const onlineInd = document.getElementById(`online-indicator-${id}`);
                if (onlineInd) {
                    onlineInd.className = `au-online-indicator ${data.is_online}`;
                }
                
                // 3. Update Status Badge
                const statusBadge = document.getElementById(`status-badge-${id}`);
                if (statusBadge) {
                    statusBadge.className = `au-badge-status ${data.status}`;
                    statusBadge.textContent = data.status_label;
                }
                
                // 4. Update Expiry Text
                const expiryText = document.getElementById(`expiry-text-${id}`);
                if (expiryText) {
                    expiryText.textContent = `پایان: ${data.expires_at} (${toPersianDigits(data.rem_days)} روز)`;
                }
                
                // 5. Update Usage Top (Percentage)
                const usagePct = document.getElementById(`usage-pct-${id}`);
                if (usagePct) {
                    usagePct.textContent = `٪${toPersianDigits(data.usage_percent)}`;
                }
                
                // 6. Update Usage Limits/Used
                const usageLimit = document.getElementById(`usage-limit-${id}`);
                if (usageLimit) {
                    usageLimit.textContent = `از ${toPersianDigits(data.limit_formatted)}`;
                }
                
                const usageUsed = document.getElementById(`usage-used-${id}`);
                if (usageUsed) {
                    usageUsed.textContent = toPersianDigits(data.used_formatted);
                }
                
                // 7. Update Progress Bar
                const progressFill = document.getElementById(`progress-fill-${id}`);
                if (progressFill) {
                    progressFill.style.width = `${data.usage_percent}%`;
                    if (data.usage_percent >= 90) {
                        progressFill.className = 'au-progress-fill danger';
                    } else if (data.usage_percent >= 75) {
                        progressFill.className = 'au-progress-fill warning';
                    } else {
                        progressFill.className = 'au-progress-fill';
                    }
                }
                
            } catch (err) {
                console.error(err);
            }
        }

        function toPersianDigits(str) {
            if (str === null || str === undefined) return '';
            const id = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return str.toString()
                .replace(/[0-9]/g, function(w) { return id[+w]; })
                .replace(/\./g, '٫');
        }

        function renderPagination(current, total) {
            const pagination = document.getElementById('au-pagination-container');
            if (total <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let html = '';
            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
                    html += `<a href="#" class="au-page-btn ${i === current ? 'active' : ''}" onclick="event.preventDefault(); loadUsers(${i})">${i}</a>`;
                } else if (i === current - 2 || i === current + 2) {
                    html += `<a href="#" class="au-page-btn" style="pointer-events:none;">...</a>`;
                }
            }
            pagination.innerHTML = html;
        }

        function bindDropdowns() {
            const dropdownBtns = document.querySelectorAll('.au-btn-dropdown');
            dropdownBtns.forEach(btn => {
                btn.onclick = (e) => {
                    e.stopPropagation();
                    const targetId = btn.getAttribute('data-target');
                    const dropdown = document.getElementById(targetId);
                    
                    document.querySelectorAll('.au-dropdown.show').forEach(d => {
                        if (d.id !== targetId) d.classList.remove('show');
                    });

                    if (dropdown) dropdown.classList.toggle('show');
                };
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadUsers(1);

            const searchInput = document.getElementById('au-search-input');
            let searchTimeout;
            if(searchInput) {
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        currentSearch = e.target.value;
                        loadUsers(1);
                    }, 500);
                });
            }
            
            const btnCreateOpen = document.querySelector('.au-btn-primary');
            if(btnCreateOpen) {
                btnCreateOpen.addEventListener('click', () => {
                    document.getElementById('create-error').style.display = 'none';
                    document.getElementById('create-username').value = '';
                    openModal('create-modal');
                });
            }
        });

        function updateProductsList(locSelectId, prodSelectId) {
            const loc = document.getElementById(locSelectId).value;
            const prodSelect = document.getElementById(prodSelectId);
            prodSelect.innerHTML = '<option value="">-- انتخاب پلن --</option>';
            if(!loc) return;

            const filtered = RAW_PRODUCTS.filter(p => p.Location === loc || p.Location === '/all');
            filtered.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.name_product} - ${Number(p.price_product).toLocaleString()} تومان`;
                prodSelect.appendChild(opt);
            });
        }

        async function submitCreateUser() {
            const loc = document.getElementById('create-location').value;
            const prodId = document.getElementById('create-product').value;
            const username = document.getElementById('create-username').value;
            const errDiv = document.getElementById('create-error');
            const btn = document.getElementById('btn-submit-create');

            if(!loc || !prodId) {
                errDiv.textContent = 'انتخاب سرور و پلن الزامی است';
                errDiv.style.display = 'block';
                return;
            }

            errDiv.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال پردازش...';

            const formData = new FormData();
            formData.append('action', 'create_user');
            formData.append('location', loc);
            formData.append('product_id', prodId);
            formData.append('username', username);

            try {
                const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.status === 'success') {
                    closeModal('create-modal');
                    loadUsers(1);
                    alert('کاربر با موفقیت ساخته شد!');
                } else {
                    errDiv.textContent = json.message || 'خطا در ساخت کاربر';
                    errDiv.style.display = 'block';
                }
            } catch(e) {
                errDiv.textContent = 'خطای ارتباط با سرور';
                errDiv.style.display = 'block';
            }
            btn.disabled = false;
            btn.textContent = 'ساخت و کسر از حساب';
        }

        function openRenewModal(invoiceId, username, location) {
            document.getElementById('renew-invoice-id').value = invoiceId;
            document.getElementById('renew-username-lbl').textContent = username;
            document.getElementById('renew-location-lbl').textContent = location;
            document.getElementById('renew-location-val').value = location;
            
            document.getElementById('renew-error').style.display = 'none';
            
            const prodSelect = document.getElementById('renew-product');
            prodSelect.innerHTML = '<option value="">-- انتخاب پلن --</option>';
            const filtered = RAW_PRODUCTS.filter(p => p.Location === location || p.Location === '/all');
            filtered.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.name_product} - ${Number(p.price_product).toLocaleString()} تومان`;
                prodSelect.appendChild(opt);
            });
            
            openModal('renew-modal');
        }

        async function submitRenewUser() {
            const invoiceId = document.getElementById('renew-invoice-id').value;
            const prodId = document.getElementById('renew-product').value;
            const errDiv = document.getElementById('renew-error');
            const btn = document.getElementById('btn-submit-renew');

            if(!prodId) {
                errDiv.textContent = 'انتخاب پلن الزامی است';
                errDiv.style.display = 'block';
                return;
            }

            errDiv.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال پردازش...';

            const formData = new FormData();
            formData.append('action', 'renew_user');
            formData.append('invoice_id', invoiceId);
            formData.append('product_id', prodId);

            try {
                const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.status === 'success') {
                    closeModal('renew-modal');
                    loadUsers(currentPage);
                    alert('سرویس با موفقیت تمدید شد!');
                } else {
                    errDiv.textContent = json.message || 'خطا در تمدید سرویس';
                    errDiv.style.display = 'block';
                }
            } catch(e) {
                errDiv.textContent = 'خطای ارتباط با سرور';
                errDiv.style.display = 'block';
            }
            btn.disabled = false;
            btn.textContent = 'تمدید سرویس';
        }

        async function deleteUser(invoiceId) {
            if(!confirm('آیا از حذف این کاربر اطمینان دارید؟ این عملیات قابل بازگشت نیست!')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('invoice_id', invoiceId);

            try {
                const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.status === 'success') {
                    loadUsers(currentPage);
                } else {
                    alert(json.message || 'خطا در حذف کاربر');
                }
            } catch(e) {
                alert('خطای ارتباط با سرور');
            }
        }

        async function openLinkModal(invoiceId) {
            openModal('link-modal');
            const content = document.getElementById('link-content');
            content.textContent = 'در حال دریافت اطلاعات...';
            
            const formData = new FormData();
            formData.append('action', 'get_link');
            formData.append('invoice_id', invoiceId);

            try {
                const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.status === 'success') {
                    content.textContent = json.link;
                } else {
                    content.textContent = json.message || 'خطا در دریافت لینک';
                }
            } catch(e) {
                content.textContent = 'خطای ارتباط با سرور';
            }
        }

        function openUserDetails(id) {
            openModal('details-modal');
            
            // Get data from DOM elements (from the card)
            const username = document.querySelector(\`#user-card-\${id} .au-username\`)?.textContent || '—';
            const location = document.querySelector(\`#user-card-\${id} .au-badge-location\`)?.textContent.trim() || '—';
            const created = document.querySelector(\`#user-card-\${id} .au-meta-date\`)?.textContent.replace('ساخته شده:', '').trim() || '—';
            
            // Extract from DOM
            const statusBadgeHTML = document.getElementById(\`status-badge-\${id}\`)?.outerHTML || '—';
            const connectionBadgeHTML = document.getElementById(\`connection-badge-\${id}\`)?.outerHTML || '—';
            
            const expiryText = document.getElementById(\`expiry-text-\${id}\`)?.textContent || '—';
            let expiredStr = '—';
            let remDaysStr = '';
            if(expiryText !== '—') {
                const match = expiryText.match(/پایان:\\s*(.*?)\\s*\\((.*?)\\)/);
                if(match) {
                    expiredStr = match[1];
                    remDaysStr = '(' + match[2] + ')';
                } else {
                    expiredStr = expiryText;
                }
            }

            const usageLimit = document.getElementById(\`usage-limit-\${id}\`)?.textContent.replace('از ', '').trim() || '—';
            const usageUsed = document.getElementById(\`usage-used-\${id}\`)?.textContent.trim() || '—';
            const usagePct = document.getElementById(\`usage-pct-\${id}\`)?.textContent.trim() || '٪۰';
            const pctVal = usagePct.replace('٪', '').replace(/[۰-۹]/g, w => ['0','1','2','3','4','5','6','7','8','9'][w.charCodeAt(0)-1776] || w);
            
            const progressFillClass = document.getElementById(\`progress-fill-\${id}\`)?.className || 'au-progress-fill';

            document.getElementById('det-username').textContent = username;
            document.getElementById('det-location').textContent = location;
            document.getElementById('det-created').textContent = created;
            
            document.getElementById('det-status').innerHTML = statusBadgeHTML;
            document.getElementById('det-connection').innerHTML = connectionBadgeHTML;
            
            document.getElementById('det-expired').textContent = expiredStr;
            document.getElementById('det-rem-days').textContent = remDaysStr;
            
            document.getElementById('det-usage-limit').textContent = 'از ' + usageLimit;
            document.getElementById('det-usage-used').textContent = usageUsed;
            document.getElementById('det-usage-pct').textContent = usagePct;
            
            const pFill = document.getElementById('det-progress-fill');
            pFill.className = progressFillClass;
            pFill.style.width = pctVal + '%';
        }

        function copyConfigLink() {
            const content = document.getElementById('link-content');
            if(content.textContent.trim() === '' || content.textContent.includes('در حال دریافت')) return;
            
            navigator.clipboard.writeText(content.textContent).then(() => {
                alert('لینک با موفقیت کپی شد!');
            }).catch(err => {
                alert('خطا در کپی کردن متن!');
            });
        }
    </script>


    <script src="js/agent_users.js"></script>
    <style>
        /* Mobile padding adjustment */
        @media (max-width: 1024px) {
            body { --au-mobile-offset: 65px; }
        }
    </style>
</body>
</html>
