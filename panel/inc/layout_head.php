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
  <meta name="theme-color" content="#0F172A" id="mtc">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= $textbotlang['panel']['layoutBrandName'] ?></title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/mobile_optimizations.css">
  <script>
    (function () {
      var _LIGHT = ['light', 'linen', 'mint', 'lavender'];
      var bg = {
        navy: '#0F172A', purple: '#180D2E', emerald: '#0A1F1C',
        sunset: '#1A0D0D', slate: '#080808', light: '#F1F5F9',
        linen: '#FAF7F2', mint: '#F0FDF4', lavender: '#FAF5FF'
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
      root.style.backgroundColor = bg[t] || '#0F172A';
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
    window.PANEL_I18N = <?= json_encode(
      array_filter(
        $textbotlang['panel'] ?? [],
        fn($k) => strncmp($k, 'js_', 3) === 0,
        ARRAY_FILTER_USE_KEY
      ),
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    window.t = function (key, vars) {
      var s = (window.PANEL_I18N && window.PANEL_I18N[key]) || key;
      if (vars) {
        for (var k in vars) {
          if (Object.prototype.hasOwnProperty.call(vars, k)) {
            s = s.replace('{' + k + '}', vars[k]);
          }
        }
      }
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
                if(node.parentElement && node.parentElement.tagName !== 'SCRIPT' && node.parentElement.tagName !== 'STYLE' && node.parentElement.tagName !== 'TEXTAREA') {
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
            document.querySelectorAll('.sidebar-nav .nav-item, .bottom-nav .bnav-item').forEach(function(el) {
                if(el.getAttribute('href') === path) el.classList.add('active');
                else el.classList.remove('active');
            });
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
        <div class="nav-section">
          <div class="nav-heading"><?= $textbotlang['panel']['layoutNavKeyboard'] ?></div>
          <a href="index.php" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleDashboard'] ?>">
            <span class="nav-icon"><?= icon('dashboard') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutNavSettings'] ?></span>
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-heading"><?= $textbotlang['panel']['layoutNavLogout'] ?></div>
          <a href="users.php" class="nav-item <?= $activeNav === 'users' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleUsers'] ?>">
            <span class="nav-icon"><?= icon('users') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutMenuSectionMain'] ?></span>
          </a>

          <a href="broadcast.php" class="nav-item <?= $activeNav === 'broadcast' ? 'active' : '' ?>"
            title="ارسال پیام همگانی">
            <span class="nav-icon"><?= icon('send') ?></span><span
              class="nav-label">پیام همگانی</span>
          </a>
          <a href="invoice.php" class="nav-item <?= $activeNav === 'invoice' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleInvoice'] ?>">
            <span class="nav-icon"><?= icon('invoice') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutMenuSectionManagement'] ?></span>
          </a>
          <a href="service.php" class="nav-item <?= $activeNav === 'service' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleService'] ?>">
            <span class="nav-icon"><?= icon('server') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutMenuSectionFinancial'] ?></span>
          </a>
          <a href="product.php" class="nav-item <?= $activeNav === 'product' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleProduct'] ?>">
            <span class="nav-icon"><?= icon('package') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutMenuSectionSystem'] ?></span>
          </a>
          <a href="payment.php" class="nav-item <?= $activeNav === 'payment' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitlePayment'] ?>">
            <span class="nav-icon"><?= icon('card') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutSearchBoxPlaceholder'] ?></span>
          </a>
          <a href="panels_manage.php" class="nav-item <?= $activeNav === 'panels_manage' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitlePanels'] ?>">
            <span class="nav-icon"><?= icon('server') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutNavPanels'] ?></span>
          </a>
          <a href="bot_settings.php" class="nav-item <?= $activeNav === 'bot_settings' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleBotSettings'] ?>">
            <span class="nav-icon"><?= icon('cpu') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutNavBotSettings'] ?></span>
          </a>
          <a href="keyboard.php" class="nav-item <?= $activeNav === 'keyboard' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleKeyboard'] ?>">
            <span class="nav-icon"><?= icon('sliders') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutThemeToggleLabel'] ?></span>
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-heading"><?= $textbotlang['panel']['layoutSidebarToggleLabel'] ?></div>
          <a href="settings.php" class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>"
            title="<?= $textbotlang['panel']['layoutPageTitleSettings'] ?>">
            <span class="nav-icon"><?= icon('settings') ?></span><span
              class="nav-label">پیکربندی پنل</span>
          </a>
          <a href="logout.php" class="nav-item" title="<?= $textbotlang['panel']['layoutPageTitleLogout'] ?>">
            <span class="nav-icon"><?= icon('logout') ?></span><span
              class="nav-label"><?= $textbotlang['panel']['layoutNotificationsLabel'] ?></span>
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