<?php
session_start();
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';

if (!isset($_SESSION['agent_id'])) {
    header("Location: agent_login.php");
    exit;
}

$agent_id = $_SESSION['agent_id'];

// Get agent name or details if needed
$stmt = $pdo->prepare("SELECT namecustom FROM user WHERE id = :id");
$stmt->execute([':id' => $agent_id]);
$agentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
$agentUsername = !empty($agentUserRow['namecustom']) ? $agentUserRow['namecustom'] : 'نماینده ' . $agent_id;

$initials = mb_strtoupper(mb_substr($agentUsername, 0, 1, 'UTF-8'), 'UTF-8');
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
        <div style="font-weight: 700; font-size: 1.1rem;">پنل نمایندگی</div>
        <button id="au-mobile-toggle" class="au-btn-icon" style="border: none; background: transparent;">
            <?= icon('menu', 24) ?>
        </button>
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

    <script>
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
                    const statusColor = user.status === 'active' ? '' : 'background: rgba(239, 68, 68, 0.15); color: var(--au-danger);';
                    const activeClass = user.status === 'active' ? 'au-badge-active' : '';
                    const dotStyle = user.status === 'active' ? '' : 'background: var(--au-danger);';

                    html += `
                    <div class="au-card">
                        <div class="au-card-info">
                            <div class="au-card-icon">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div class="au-card-details">
                                <h3>
                                    ${user.username} 
                                    <div class="au-status-dot" style="${dotStyle}"></div>
                                </h3>
                                <div class="au-card-meta">
                                    <span><i class="fa-solid fa-map-pin"></i> ${user.location}</span>
                                    <span>ایجاد: ${user.created_at}</span>
                                </div>
                            </div>
                        </div>

                        <div class="au-card-status">
                            <div class="au-badges">
                                <span class="au-badge ${activeClass}" style="${statusColor}">
                                    ${user.status_label}
                                </span>
                            </div>
                            <div class="au-expiry">
                                پایان: ${user.expires_at}
                            </div>
                        </div>

                        <div class="au-card-usage">
                            <div class="au-usage-header" style="justify-content: center;">
                                <span><i class="fa-solid fa-database"></i> حجم: ${user.total_gb} | <i class="fa-solid fa-clock"></i> مدت: ${user.service_time_str}</span>
                            </div>
                        </div>

                        <div class="au-card-actions">
                            <button class="au-btn-icon au-btn-dropdown" data-target="dropdown-${user.id}" style="border:none; background:transparent; width:36px; height:36px;" title="بیشتر">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            
                            <div class="au-dropdown" id="dropdown-${user.id}">
                                <a href="#" class="au-dropdown-item"><i class="fa-solid fa-pen"></i> ویرایش کاربر</a>
                                <a href="#" class="au-dropdown-item"><i class="fa-solid fa-rotate-right"></i> تمدید سرویس</a>
                                <a href="#" class="au-dropdown-item danger"><i class="fa-solid fa-trash"></i> حذف کاربر</a>
                            </div>
                        </div>
                    </div>`;
                });

                container.innerHTML = html;

                // Re-bind dropdowns
                bindDropdowns();

                // Render pagination
                renderPagination(pageInfo.page, pageInfo.total_pages);

            } catch (err) {
                container.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--au-danger);">خطای ارتباط با سرور</div>';
            }
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
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentSearch = e.target.value;
                    loadUsers(1);
                }, 500);
            });
        });
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
