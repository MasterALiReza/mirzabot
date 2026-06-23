<?php
require_once __DIR__ . '/icons.php';
$pageLede = $pageLede ?? '';
$activeNav = $activeNav ?? '';
$showPageHead = $showPageHead ?? true;
$currentUser = $_SESSION['admin_user'] ?? $textbotlang['panel']['layoutDefaultAdminName'];
$initials = mb_strtoupper(mb_substr($currentUser, 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <meta name="theme-color" content="#222831" id="mtc">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= $textbotlang['panel']['layoutBrandName'] ?></title>
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="css/mobile_optimizations.css?v=<?= time() ?>">
  <script>
    (function () {
      var _LIGHT = ['light'];
      var bg = {
        navy: '#222831', light: '#F1F5F9'
      };

      // Auto-detect on first visit using device preference
      var saved = localStorage.getItem('panel-theme');
      var t;
      if (!saved) {
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        t = prefersDark ? 'navy' : 'light';
        localStorage.setItem('panel-theme', t);
      } else {
        t = saved;
      }

      var root = document.documentElement;
      root.style.backgroundColor = bg[t] || '#222831';
      root.setAttribute('data-theme', t);
      var isLight = _LIGHT.indexOf(t) >= 0;
      root.style.colorScheme = isLight ? 'light' : 'dark';
      var mtc = document.getElementById('mtc');
      if (mtc && bg[t]) mtc.content = bg[t];
      if (localStorage.getItem('panel-sb-collapsed') === '1' && window.innerWidth > 768)
        root.classList.add('sb-pre-collapsed');
    }());
  </script>
  <script>
    <?php
    $js_i18n = [];
    if (isset($textbotlang['panel']) && is_array($textbotlang['panel'])) {
        foreach ($textbotlang['panel'] as $k => $v) {
            if (substr($k, 0, 2) === 'js') {
                $js_i18n[$k] = $v;
            }
        }
    }
    ?>
    window.PANEL_I18N = <?= json_encode($js_i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.t = function (key, vars) {
      var s = (window.PANEL_I18N && window.PANEL_I18N[key]) || key;
      if (vars) {
        for (var k in vars) {
          if (Object.prototype.hasOwnProperty.call(vars, k)) {
            s = s.replace('{' + k + '}', vars[k]);
          }
        }
      }
      return s;
    };
  </script>
  <script src="js/chart.min.js"></script>
</head>

<body hx-boost="true">
  <script>
    // Farsi digits conversion
    document.addEventListener("DOMContentLoaded", function() {
        const p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
        const e2p = s => s.replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        function walk(node) {
            if (node.nodeType === 3) {
                if(node.parentElement && node.parentElement.tagName !== 'SCRIPT' && node.parentElement.tagName !== 'STYLE' && node.parentElement.tagName !== 'TEXTAREA' && (!node.parentElement.closest || (!node.parentElement.closest('.en-num') && !node.parentElement.closest('.cm') && !node.parentElement.closest('.cell-mono')))) {
                    node.nodeValue = e2p(node.nodeValue);
                }
            } else if (node.nodeType === 1) {
                if (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA') return;
                for (var i = 0; i < node.childNodes.length; i++) {
                    walk(node.childNodes[i]);
                }
            }
        }
        walk(document.body);
        document.body.addEventListener('htmx:afterSwap', function(e) {
            walk(e.detail.elt);
            // Update active nav links
            var path = window.location.pathname.split('/').pop() || 'index.php';
            var search = window.location.search; // e.g. "?tab=admins"
            var fullPath = path + search;
            
            // Clear all active and open states
            document.querySelectorAll('.sidebar-nav .nav-item, .sidebar-nav .nav-sub-item, .sidebar-nav .nav-group-btn, .bottom-nav .bnav-item').forEach(function(el) {
                el.classList.remove('active');
            });
            document.querySelectorAll('.sidebar-nav .nav-group').forEach(function(el) {
                el.classList.remove('open');
            });

            // Find the active link (try exact match with query string first)
            var activeLink = document.querySelector('.sidebar-nav a[href="' + fullPath + '"], .bottom-nav a[href="' + fullPath + '"]');
            
            // If no exact match, try matching just the path (ignoring query string)
            if (!activeLink) {
                activeLink = document.querySelector('.sidebar-nav a[href="' + path + '"], .bottom-nav a[href="' + path + '"]');
            }

            if (activeLink) {
                activeLink.classList.add('active');
                // If it's a sub-item, open its parent group
                var group = activeLink.closest('.nav-group');
                if (group) {
                    group.classList.add('open');
                    var groupBtn = group.querySelector('.nav-group-btn');
                    if (groupBtn) groupBtn.classList.add('active');
                }
            }
        });
    });
  </script>
  <div id="load-bar"></div>
  <div id="toast-area"></div>

  <div class="confirm-veil" id="confirm-veil">
    <div class="confirm-box">
      <div class="confirm-icon"><?= icon('block', 26) ?></div>
      <h4 id="confirm-title"><?= $textbotlang['panel']['layoutNavDashboard'] ?></h4>
      <p id="confirm-msg"><?= $textbotlang['panel']['layoutNavUsers'] ?></p>
      <div class="confirm-btns">
        <button class="btn btn-no" id="confirm-ok"><?= $textbotlang['panel']['layoutNavOrders'] ?></button>
        <button class="btn btn-ghost"
          onclick="closeConfirm()"><?= $textbotlang['panel']['layoutNavServices'] ?></button>
      </div>
    </div>
  </div>

  <div class="app">
    <div class="sidebar-backdrop" id="backdrop" onclick="closeSidebar()"></div>

    <aside class="sidebar" id="sidebar" hx-preserve="true">
      <div class="sidebar-brand">
        <div class="brand-mark">M</div>
        <div class="brand-name"><?= $textbotlang['panel']['layoutNavProducts'] ?><span>
            <?= $textbotlang['panel']['layoutNavPayments'] ?></span></div>
      </div>
      <nav class="sidebar-nav">
        <!-- داشبورد -->
        <div class="nav-section" style="margin-bottom: 12px;">
          <a href="index.php" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleDashboard'] ?? 'داشبورد' ?>">
            <span class="nav-icon"><?= icon('dashboard') ?></span>
            <span class="nav-label"><?= $textbotlang['panel']['layoutPageTitleDashboard'] ?? 'داشبورد' ?></span>
          </a>
        </div>

        <div class="nav-section">
          <!-- مدیریت کاربران -->
          <div class="nav-group <?= in_array($activeNav, ['users', 'broadcast']) ? 'open' : '' ?>">
            <button class="nav-group-btn <?= in_array($activeNav, ['users', 'broadcast']) ? 'active' : '' ?>">
              <div class="nav-group-title">
                <span class="nav-icon"><?= icon('users') ?></span>
                <span class="nav-label">مدیریت کاربران</span>
              </div>
              <svg class="nav-group-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="nav-sub">
              <a href="users.php" class="nav-sub-item <?= ($activeNav === 'users' && !isset($_GET['tab']) && !isset($_GET['role'])) ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitleUsers'] ?? 'لیست کاربران' ?>">
                <div class="nav-sub-dot"></div>لیست کاربران
              </a>
              <a href="users.php?tab=admins" class="nav-sub-item <?= (isset($_GET['tab']) && $_GET['tab'] === 'admins') ? 'active' : '' ?>" title="مدیران پنل">
                <div class="nav-sub-dot"></div>مدیران و همکاران پنل
              </a>

              <a href="broadcast.php" class="nav-sub-item <?= $activeNav === 'broadcast' ? 'active' : '' ?>" title="ارسال پیام همگانی">
                <div class="nav-sub-dot"></div>پیام همگانی
              </a>
            </div>
          </div>

          <!-- همکاری در فروش -->
          <div class="nav-group <?= in_array($activeNav, ['affiliates', 'settings_affiliates']) ? 'open' : '' ?>">
            <button class="nav-group-btn <?= in_array($activeNav, ['affiliates', 'settings_affiliates']) ? 'active' : '' ?>">
              <div class="nav-group-title">
                <span class="nav-icon"><?= icon('percent') ?></span>
                <span class="nav-label">همکاری در فروش</span>
              </div>
              <svg class="nav-group-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="nav-sub">
              <a href="affiliates.php" class="nav-sub-item <?= $activeNav === 'affiliates' ? 'active' : '' ?>" title="لیست زیرمجموعه‌ها">
                <div class="nav-sub-dot"></div>لیست زیرمجموعه‌ها
              </a>
              <a href="settings_affiliates.php" class="nav-sub-item <?= $activeNav === 'settings_affiliates' ? 'active' : '' ?>" title="تنظیمات پورسانت">
                <div class="nav-sub-dot"></div>تنظیمات پورسانت
              </a>
            </div>
          </div>

          <!-- نماینده‌ها -->
          <div class="nav-group <?= (isset($_GET['role']) && $_GET['role'] === 'agents') || $activeNav === 'settings_agents' ? 'open' : '' ?>">
            <button class="nav-group-btn <?= (isset($_GET['role']) && $_GET['role'] === 'agents') || $activeNav === 'settings_agents' ? 'active' : '' ?>">
              <div class="nav-group-title">
                <span class="nav-icon"><?= icon('briefcase') ?></span>
                <span class="nav-label">نماینده‌ها</span>
              </div>
              <svg class="nav-group-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="nav-sub">
              <a href="users.php?role=agents" class="nav-sub-item <?= (isset($_GET['role']) && $_GET['role'] === 'agents') ? 'active' : '' ?>" title="لیست نماینده‌ها">
                <div class="nav-sub-dot"></div>لیست نماینده‌ها
              </a>
              <a href="settings_agents.php" class="nav-sub-item <?= $activeNav === 'settings_agents' ? 'active' : '' ?>" title="تنظیمات نماینده‌ها">
                <div class="nav-sub-dot"></div>تنظیمات نماینده‌ها
              </a>
            </div>
          </div>

          <!-- فروشگاه و خدمات -->
          <div class="nav-group <?= in_array($activeNav, ['product', 'service', 'panels_manage', 'panel_categories', 'categories_manage', 'settings_shop']) ? 'open' : '' ?>">
            <button class="nav-group-btn <?= in_array($activeNav, ['product', 'service', 'panels_manage', 'panel_categories', 'categories_manage', 'settings_shop']) ? 'active' : '' ?>">
              <div class="nav-group-title">
                <span class="nav-icon"><?= icon('package') ?></span>
                <span class="nav-label">فروشگاه و محصولات</span>
              </div>
              <svg class="nav-group-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="nav-sub">
              <a href="product.php" class="nav-sub-item <?= $activeNav === 'product' ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitleProduct'] ?? 'محصولات' ?>">
                <div class="nav-sub-dot"></div><?= $textbotlang['panel']['layoutMenuSectionSystem'] ?? 'محصولات' ?>
              </a>
              <a href="service.php" class="nav-sub-item <?= $activeNav === 'service' ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitleService'] ?? 'خدمات' ?>">
                <div class="nav-sub-dot"></div><?= $textbotlang['panel']['layoutMenuSectionFinancial'] ?? 'خدمات' ?>
              </a>
              <a href="panels_manage.php" class="nav-sub-item <?= $activeNav === 'panels_manage' ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitlePanels'] ?? 'مدیریت پنل‌ها' ?>">
                <div class="nav-sub-dot"></div><?= $textbotlang['panel']['layoutNavPanels'] ?? 'مدیریت پنل‌ها' ?>
              </a>
              <a href="panel_categories.php" class="nav-sub-item <?= $activeNav === 'panel_categories' ? 'active' : '' ?>" title="دسته‌بندی پنل‌ها">
                <div class="nav-sub-dot"></div>دسته‌بندی پنل‌ها
              </a>
              <a href="settings_shop.php" class="nav-sub-item <?= $activeNav === 'settings_shop' ? 'active' : '' ?>" title="تنظیمات فروشگاه">
                <div class="nav-sub-dot"></div>تنظیمات فروشگاه
              </a>
            </div>
          </div>

          <!-- مالی و سفارشات -->
          <div class="nav-group <?= in_array($activeNav, ['invoice', 'payment', 'settings_financial']) ? 'open' : '' ?>">
            <button class="nav-group-btn <?= in_array($activeNav, ['invoice', 'payment', 'settings_financial']) ? 'active' : '' ?>">
              <div class="nav-group-title">
                <span class="nav-icon"><?= icon('card') ?></span>
                <span class="nav-label">مالی و سفارشات</span>
              </div>
              <svg class="nav-group-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="nav-sub">
              <a href="invoice.php" class="nav-sub-item <?= $activeNav === 'invoice' ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitleInvoice'] ?? 'سفارشات' ?>">
                <div class="nav-sub-dot"></div><?= $textbotlang['panel']['layoutMenuSectionManagement'] ?? 'سفارشات' ?>
              </a>
              <a href="payment.php" class="nav-sub-item <?= $activeNav === 'payment' ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitlePayment'] ?? 'پرداختی‌ها' ?>">
                <div class="nav-sub-dot"></div><?= $textbotlang['panel']['layoutSearchBoxPlaceholder'] ?? 'پرداختی‌ها' ?>
              </a>
              <a href="settings_financial.php" class="nav-sub-item <?= $activeNav === 'settings_financial' ? 'active' : '' ?>" title="تنظیمات مالی">
                <div class="nav-sub-dot"></div>تنظیمات مالی
              </a>
            </div>
          </div>

          <!-- تنظیمات -->
          <div class="nav-group <?= (in_array($activeNav, ['bot_settings', 'keyboard', 'settings', 'settings_channels']) && (!isset($_GET['tab']) || $_GET['tab'] !== 'agents')) ? 'open' : '' ?>">
            <button class="nav-group-btn <?= (in_array($activeNav, ['bot_settings', 'keyboard', 'settings', 'settings_channels']) && (!isset($_GET['tab']) || $_GET['tab'] !== 'agents')) ? 'active' : '' ?>">
              <div class="nav-group-title">
                <span class="nav-icon"><?= icon('settings') ?></span>
                <span class="nav-label">تنظیمات</span>
              </div>
              <svg class="nav-group-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="nav-sub">
              <a href="bot_settings.php" class="nav-sub-item <?= ($activeNav === 'bot_settings' && (!isset($_GET['tab']) || $_GET['tab'] !== 'agents')) ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitleBotSettings'] ?? 'تنظیمات ربات' ?>">
                <div class="nav-sub-dot"></div><?= $textbotlang['panel']['layoutNavBotSettings'] ?? 'تنظیمات ربات' ?>
              </a>
              <a href="keyboard.php" class="nav-sub-item <?= $activeNav === 'keyboard' ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitleKeyboard'] ?? 'چیدمان کیبورد' ?>">
                <div class="nav-sub-dot"></div><?= $textbotlang['panel']['layoutThemeToggleLabel'] ?? 'چیدمان کیبورد' ?>
              </a>
              <a href="settings.php" class="nav-sub-item <?= $activeNav === 'settings' ? 'active' : '' ?>" title="<?= $textbotlang['panel']['layoutPageTitleSettings'] ?? 'پیکربندی پنل' ?>">
                <div class="nav-sub-dot"></div>پیکربندی پنل
              </a>
              <a href="settings_channels.php" class="nav-sub-item <?= $activeNav === 'settings_channels' ? 'active' : '' ?>" title="تنظیمات کانال‌های اجباری">
                <div class="nav-sub-dot"></div>تنظیمات کانال‌ها
              </a>
            </div>
          </div>
        </div>

        <div class="nav-section" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--bd);">
          <a href="logout.php" class="nav-item" title="<?= $textbotlang['panel']['layoutPageTitleLogout'] ?? 'خروج از حساب' ?>">
            <span class="nav-icon"><?= icon('logout') ?></span>
            <span class="nav-label"><?= $textbotlang['panel']['layoutNotificationsLabel'] ?? 'خروج از حساب' ?></span>
          </a>
        </div>
      </nav>
      <div class="sidebar-foot">
        <div class="user-pill">
          <div class="user-mono"><?= htmlspecialchars($initials) ?></div>
          <div class="user-info">
            <div class="uname"><?= htmlspecialchars($currentUser) ?></div>
            <div class="urole"><?= $textbotlang['panel']['layoutMobileMenuLabel'] ?></div>
          </div>
        </div>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div class="topbar-left">
          <style>
            @media (max-width: 768px) {
              .sb-toggle { display: none !important; }
              .menu-toggle { display: flex !important; align-items: center; justify-content: center; }
            }
            @media (min-width: 769px) {
              .menu-toggle { display: none !important; }
              .sb-toggle { display: flex !important; align-items: center; justify-content: center; }
            }
          </style>
          <button class="icon-btn menu-toggle" onclick="openSidebar()"><?= icon('menu', 18) ?></button>
          <button class="icon-btn sb-toggle" onclick="toggleSidebar()"><?= icon('menu', 17) ?></button>
          <div>
            <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
            <div class="crumb"><span><?= $textbotlang['panel']['layoutPageTitleSuffix'] ?></span><span
                style="opacity:.4;margin:0 3px">/</span><span><?= htmlspecialchars($pageTitle) ?></span></div>
          </div>
        </div>
        <div class="topbar-tools">
          <button id="theme-quick-toggle" class="icon-btn" onclick="toggleDarkLight()" title="تغییر تم" aria-label="تغییر تم روشن/تیره"
            style="position:relative;overflow:hidden;transition:background var(--tf),transform var(--tf)">
            <!-- Icon injected by applyTheme() on load -->
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          </button>
          <a href="logout.php" class="icon-btn"
            title="<?= $textbotlang['panel']['layoutPageTitleLogout'] ?>"><?= icon('logout', 16) ?></a>
        </div>
        <script>
        // Sync toggle icon with current theme immediately after DOM ready
        (function(){
          var _LIGHT = ['light','linen','mint','lavender'];
          var t = localStorage.getItem('panel-theme') || 'navy';
          var isLight = _LIGHT.indexOf(t) >= 0;
          var btn = document.getElementById('theme-quick-toggle');
          if (btn) {
            btn.setAttribute('data-dark', isLight ? '0' : '1');
            btn.title = isLight ? 'تم تیره' : 'تم روشن';
            btn.innerHTML = isLight
              ? '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
              : '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
          }
        }());
        </script>
      </header>
      <main class="content">
        <?php
        $s = get_flash('success');
        $e = get_flash('error');
        $w = get_flash('warning');
        if ($s): ?>
          <div class="notice notice-ok"><?= htmlspecialchars($s) ?></div><?php endif;
        if ($e): ?>
          <div class="notice notice-no"><?= htmlspecialchars($e) ?></div><?php endif;
        if ($w): ?>
          <div class="notice notice-warn"><?= htmlspecialchars($w) ?></div><?php endif;
        if ($showPageHead): ?>
          <div class="page-head fade-up">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if ($pageLede): ?>
              <p><?= htmlspecialchars($pageLede) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>
