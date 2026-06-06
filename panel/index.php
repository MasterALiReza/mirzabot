<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// 1. Basic Stats
$totalUsers = 0;
$newToday = 0;
$totalRevenue = 0;
$todayRevenue = 0;
$activeNow = 0;
$activePanels = 0;
$pendingPay = 0;
$txToday = 0;

try {
    $totalUsers = db_count($pdo, "SELECT COUNT(*) FROM user");
    $newToday = db_count($pdo, "SELECT COUNT(*) FROM user WHERE register > ?", [strtotime('today')]);
} catch (Exception $e) {}

try {
    $totalRevenue = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')")->fetchColumn();
    $todayRevenue = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND time_sell > ?", [strtotime('today')])->fetchColumn();
    $activeNow = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE Status='active'");
} catch (Exception $e) {}

try {
    $activePanels = db_count($pdo, "SELECT COUNT(*) FROM marzban_panel WHERE status = 'active'");
} catch (Exception $e) {}

try {
    $pendingPay = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE payment_Status='waiting'");
    $txToday = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE time > ?", [strtotime('today')]);
} catch (Exception $e) {}

// 2. Chart Data (Dynamic Range)
$range = $_GET['range'] ?? '7d';
$chartLabels = [];
$chartData = [];
$chartSubtitle = '';

$persianDays = [
    'Saturday' => 'شنبه', 'Sunday' => 'یکشنبه', 'Monday' => 'دوشنبه',
    'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه', 'Thursday' => 'پنجشنبه', 'Friday' => 'جمعه'
];

try {
    if ($range === '24h') {
        $chartSubtitle = 'مجموع فروش ۲۴ ساعت گذشته (تومان)';
        for ($i = 23; $i >= 0; $i--) {
            $startOfHour = strtotime("-$i hours", strtotime(date('Y-m-d H:00:00')));
            $endOfHour = $startOfHour + 3599;
            $dayRev = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND time_sell BETWEEN ? AND ?", [$startOfHour, $endOfHour])->fetchColumn();
            
            $chartLabels[] = date('H:00', $startOfHour);
            $chartData[] = $dayRev;
        }
    } elseif ($range === '30d') {
        $chartSubtitle = 'مجموع فروش ۳۰ روز گذشته (تومان)';
        for ($i = 29; $i >= 0; $i--) {
            $startOfDay = strtotime("-$i days 00:00:00");
            $endOfDay = strtotime("-$i days 23:59:59");
            $dayRev = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND time_sell BETWEEN ? AND ?", [$startOfDay, $endOfDay])->fetchColumn();
            
            $chartLabels[] = date('m/d', $startOfDay);
            $chartData[] = $dayRev;
        }
    } else {
        $range = '7d'; // fallback
        $chartSubtitle = 'مجموع فروش ۷ روز گذشته (تومان)';
        for ($i = 6; $i >= 0; $i--) {
            $startOfDay = strtotime("-$i days 00:00:00");
            $endOfDay = strtotime("-$i days 23:59:59");
            $dayRev = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND time_sell BETWEEN ? AND ?", [$startOfDay, $endOfDay])->fetchColumn();
            
            $chartLabels[] = $persianDays[date('l', $startOfDay)];
            $chartData[] = $dayRev;
        }
    }
} catch (Exception $e) {}

// 3. Best Selling Products
$bestSelling = [];
try {
    $bestSelling = db_fetchAll($pdo, "
        SELECT name_product, COUNT(*) as sales_count, SUM(price_product) as total_earned 
        FROM invoice 
        WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') 
        GROUP BY name_product 
        ORDER BY sales_count DESC 
        LIMIT 5
    ");
} catch (Exception $e) {}

// 4. Recent Items
$recentInvoices = [];
$recentUsers = [];
try {
    $recentInvoices = db_fetchAll($pdo, "
        SELECT i.*, u.username, u.namecustom as name 
        FROM invoice i 
        LEFT JOIN user u ON i.id_user = u.id 
        ORDER BY i.time_sell DESC 
        LIMIT 6
    ");
    $recentUsers = db_fetchAll($pdo, "SELECT * FROM user ORDER BY register DESC LIMIT 6");
} catch (Exception $e) {
    error_log('index.php recent query error: ' . $e->getMessage());
}

$pageTitle = $textbotlang['panel']['dashboardTitle'];
$activeNav = 'dashboard';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<!-- Include Chart.js -->
<!-- Top Statistics Cards -->
<div class="stats dash-stats fade-up">
    
    <!-- Stat 1: Total Users -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <div class="dash-card-title"><?= $textbotlang['panel']['dashTotalUsers'] ?></div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <?php if ($newToday > 0): ?>
                    <span class="status-pill success">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 4px;"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                        <?= $newToday ?> <?= $textbotlang['panel']['dashTodaySpan'] ?>
                    </span>
                <?php else: ?>
                    <span class="status-pill neutral"><?= $textbotlang['panel']['dashNoChange'] ?></span>
                <?php endif; ?>
            </div>
            <div class="dash-card-value">
                <?= number_format($totalUsers) ?>
            </div>
        </div>
    </div>
    
    <!-- Stat 2: Total Revenue -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <div class="dash-card-title"><?= $textbotlang['panel']['dashTotalRevenue'] ?></div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <?php if ($todayRevenue > 0): ?>
                    <span class="status-pill success">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 4px;"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                        <?= $todayRevenue >= 1_000_000 ? number_format($todayRevenue / 1_000_000, 1) . ' <small style="font-size: 0.72rem; font-weight: 500; opacity: 0.95;">میلیون تومان</small>' : number_format($todayRevenue) . ' <small style="font-size: 0.72rem; font-weight: 500; opacity: 0.95;">تومان</small>' ?>
                    </span>
                <?php else: ?>
                    <span class="status-pill neutral">0 <small style="font-size: 0.72rem; font-weight: 500; opacity: 0.95;">تومان</small> امروز</span>
                <?php endif; ?>
            </div>
            <div class="dash-card-value-flex">
                <span class="dash-card-value">
                    <?= $totalRevenue >= 1_000_000 ? number_format($totalRevenue / 1_000_000, 1) : number_format($totalRevenue) ?>
                </span>
                <span class="dash-card-unit">
                    <?= $totalRevenue >= 1_000_000 ? 'میلیون تومان' : 'تومان' ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Stat 3: Active Services -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
            <div class="dash-card-title"><?= $textbotlang['panel']['dashActiveService'] ?></div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill panel-pill">
                    <?= $activePanels ?> پنل متصل
                </span>
            </div>
            <div class="dash-card-value">
                <?= number_format($activeNow) ?>
            </div>
        </div>
    </div>

    <!-- Stat 4: Today's Transactions -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <div class="dash-card-title">تراکنش امروز</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <?php if ($pendingPay > 0): ?>
                    <span class="status-pill danger">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 4px;"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                        <?= $pendingPay ?> <?= $textbotlang['panel']['dashPendingPayment'] ?>
                    </span>
                <?php else: ?>
                    <span class="status-pill success">
                        <?= $textbotlang['panel']['dashStatusRegistered'] ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="dash-card-value">
                <?= number_format($txToday) ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="dash-grid-2" style="margin-bottom: 24px;">
    
    <!-- Sales Chart -->
    <div class="card fade-up">
        <div class="card-head" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['dashSalesChartTitle'] ?></div>
                <div class="card-subtitle"><?= $chartSubtitle ?></div>
            </div>
            <div>
                <select class="form-select" style="font-size:0.75rem; padding: 4px 20px 4px 8px; border-radius:6px; background-color:var(--bg); color:var(--ct); border:1px solid var(--bd); cursor:pointer;" onchange="window.location.href='index.php?range='+this.value">
                    <option value="24h" <?= $range === '24h' ? 'selected' : '' ?>>۲۴ ساعت گذشته</option>
                    <option value="7d" <?= $range === '7d' ? 'selected' : '' ?>>۷ روز گذشته</option>
                    <option value="30d" <?= $range === '30d' ? 'selected' : '' ?>>۱ ماه گذشته</option>
                </select>
            </div>
        </div>
        <div class="card-body" style="position: relative; height: 300px; width: 100%; padding-top: 10px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Best Selling Chart -->
    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['dashBestSellingTitle'] ?></div>
                <div class="card-subtitle">برترین محصولات ربات (تعداد فروش)</div>
            </div>
        </div>
        <div class="card-body" style="position: relative; height: 300px; width: 100%; padding-top: 10px;">
            <?php if (empty($bestSelling)): ?>
                <div class="empty" style="height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                    <p>داده‌ای برای نمایش وجود ندارد</p>
                </div>
            <?php else: ?>
                <canvas id="bestSellingChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Tables Grid -->
<div class="dash-grid-2" style="margin-bottom: 20px;">
    
    <!-- Recent Orders -->
    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title" style="font-size: 1.35rem; font-weight: bold;"><?= $textbotlang['panel']['dashRecentOrders'] ?></div>
                <div class="card-subtitle"><?= count($recentInvoices) ?> <?= $textbotlang['panel']['dashRecentItem'] ?></div>
            </div>
            <a href="invoice.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['dashViewAll'] ?></a>
        </div>
        <div class="tbl-wrap dash-orders">
            <table class="tbl-sm">
                <thead>
                    <tr>
                        <th style="text-align:right;"><?= $textbotlang['panel']['dashColUser'] ?></th>
                        <th class="desktop-text-center" style="text-align:right;"><?= $textbotlang['panel']['dashColProduct'] ?></th>
                        <th class="desktop-text-center" style="text-align:right;"><?= $textbotlang['panel']['dashColAmount'] ?></th>
                        <th class="desktop-text-center" style="text-align:right;">تاریخ ثبت</th>
                        <th class="desktop-text-center" style="text-align:right;"><?= $textbotlang['panel']['dashColStatus'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentInvoices)): ?>
                        <tr>
                            <td colspan="5" class="no-label">
                                <div class="empty" style="padding:24px">
                                    <p><?= $textbotlang['panel']['dashNoOrdersYet'] ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $statusMap = [
                            'active' => ['status-pill success', $textbotlang['panel']['dashStatusActive']],
                            'disabled' => ['status-pill danger', $textbotlang['panel']['panelsStatusInactive']],
                            'unpaid' => ['status-pill neutral', $textbotlang['panel']['invoiceStatusUnpaid']],
                            'end_of_time' => ['status-pill warning', $textbotlang['panel']['dashStatusExpired']],
                            'end_of_volume' => ['status-pill danger', $textbotlang['panel']['dashStatusVolumeFinished']],
                            'sendedwarn' => ['status-pill warning', $textbotlang['panel']['dashStatusWarning']],
                            'send_on_hold' => ['status-pill neutral', $textbotlang['panel']['dashStatusWaiting']],
                        ];
                        foreach ($recentInvoices as $inv):
                            $rawStatus = strtolower(trim($inv['Status'] ?? ''));
                            [$pillClass, $label] = $statusMap[$rawStatus] ?? ['status-pill neutral', $inv['Status'] ?? '—'];
                            ?>
                            <tr style="border-bottom: 1px solid var(--bd);">
                                <td data-label="<?= $textbotlang['panel']['dashColUser'] ?>" class="no-label" style="text-align:right;">
                                    <div class="user-profile-cell" style="display:flex; justify-content:flex-start; align-items:center; width:100%; gap:12px;">
                                        <div class="avatar-icon" style="background: rgba(var(--ac-rgb), 0.1); color: var(--ac); padding: 8px; border-radius: 50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <?= icon('user', 18) ?>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                            <span class="profile-name" style="font-weight:700; font-size:0.95rem; color:var(--text);">
                                                <?php 
                                                $displayName = trim($inv['name'] ?? '');
                                                if ($displayName === 'none') $displayName = '';
                                                $displayUname = trim($inv['username'] ?? '');
                                                if ($displayUname === 'none') $displayUname = '';
                                                
                                                if (!empty($displayName)): ?>
                                                    <?= htmlspecialchars(trunc($displayName, 18)) ?>
                                                <?php elseif (!empty($displayUname)): ?>
                                                    <span class="cm" dir="ltr" style="display:inline-block;">@<?= htmlspecialchars(trunc($displayUname, 18)) ?></span>
                                                <?php else: ?>
                                                    <span style="opacity:0.8;">کاربر بی‌نام</span>
                                                <?php endif; ?>
                                            </span>
                                            <div style="display:flex; align-items:center; gap:6px; font-size:0.8rem;">
                                                <?php if (!empty($displayName) && !empty($displayUname)): ?>
                                                    <span class="cm" style="color:var(--ac); direction:ltr; display:inline-block; font-weight:600;">@<?= htmlspecialchars(trunc($displayUname, 12)) ?></span>
                                                    <span style="color:var(--bd);">|</span>
                                                <?php endif; ?>
                                                <div class="profile-id-box" style="display:flex; align-items:center; gap:4px; color: var(--mute);">
                                                    <?= icon('hash', 12) ?>
                                                    <span class="cn" style="font-size:0.85rem; font-weight:600;"><?= htmlspecialchars($inv['id_user']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColProduct'] ?>" class="cs desktop-text-center" style="text-align:right;">
                                    <div class="desktop-vertical-stack">
                                        <span class="icon-span hide-on-mobile" style="color:var(--mute)"><?= icon('package', 14) ?></span>
                                        <span style="font-weight:700; color:var(--text);"><?= htmlspecialchars($inv['name_product'] ?? '—') ?></span>
                                    </div>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColAmount'] ?>" class="cn desktop-text-center" style="text-align:right;">
                                    <div class="desktop-vertical-stack mobile-flex-between">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span class="icon-span" style="color:var(--mute)"><?= icon('wallet', 14) ?></span>
                                            <span class="mobile-label" style="display:none; color:var(--mute); font-weight:normal;"><?= $textbotlang['panel']['dashColAmount'] ?>:</span>
                                        </div>
                                        <span class="cn" style="font-weight:600; font-size:1rem; color:var(--ac);">
                                            <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem"><?= $textbotlang['panel']['dashTomanShort'] ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="تاریخ ثبت" class="desktop-text-center" style="text-align:right;">
                                    <div class="desktop-vertical-stack mobile-flex-between">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span class="icon-span" style="color:var(--mute)"><?= icon('calendar', 14) ?></span>
                                            <span class="mobile-label" style="display:none; color:var(--mute); font-weight:normal;">تاریخ ثبت:</span>
                                        </div>
                                        <span class="cn" style="font-weight:500; color:var(--text); display:inline-flex; align-items:center; gap:12px;">
                                            <span><?= safe_date($inv['time_sell'] ?? null, 'Y/m/d') ?></span>
                                            <span style="opacity:0.2; font-size:0.85em;">|</span>
                                            <span style="opacity:0.8; font-size:0.95em;"><?= safe_date($inv['time_sell'] ?? null, 'H:i') ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColStatus'] ?>" class="desktop-text-center" style="text-align:right;">
                                    <div class="desktop-vertical-stack">
                                        <span class="icon-span hide-on-mobile" style="color:var(--mute)"><?= icon('check-circle', 14) ?></span>
                                        <span class="<?= $pillClass ?>"><?= $label ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Recent Users -->
    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title" style="font-size: 1.35rem; font-weight: bold;"><?= $textbotlang['panel']['dashRecentUsers'] ?></div>
                <div class="card-subtitle"><?= count($recentUsers) ?> <?= $textbotlang['panel']['dashRecentItem2'] ?></div>
            </div>
            <a href="users.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['dashViewAll2'] ?></a>
        </div>
        <div class="tbl-wrap dash-users">
            <table class="tbl-sm">
                <thead>
                    <tr>
                        <th><?= $textbotlang['panel']['dashColName'] ?></th>
                        <th class="desktop-text-center" style="text-align:right;"><?= $textbotlang['panel']['dashColBalance'] ?></th>
                        <th class="desktop-text-center" style="text-align:right;">تاریخ عضویت</th>
                        <th class="desktop-text-center" style="text-align:right;"><?= $textbotlang['panel']['dashColGroup'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr>
                            <td colspan="4" class="no-label">
                                <div class="empty" style="padding:24px"><p><?= $textbotlang['panel']['dashNoUsersYet'] ?></p></div>
                            </td>
                        </tr>
                    <?php else:
                        foreach ($recentUsers as $u):
                            $agent = $u['agent'] ?? 'f';
                            $isBlocked = ($u['User_Status'] ?? '') === 'block';
                            $name = $u['namecustom'] ?? '';
                            if ($name === 'none') $name = '';
                            $uname = $u['username'] ?? '';
                            if ($uname === 'none') $uname = '';
                            
                            $roleLabel = user_role_label($agent);
                            $roleClass = 'status-pill ';
                            if ($agent === 'all') $roleClass .= 'danger';
                            elseif ($agent === 'n' || $agent === 'n2') $roleClass .= 'success';
                            else $roleClass .= 'neutral';
                            ?>
                            <tr style="border-bottom: 1px solid var(--bd);">
                                <td data-label="<?= $textbotlang['panel']['dashColName'] ?>" class="no-label" style="text-align:right;">
                                    <div class="user-profile-cell" style="display:flex; justify-content:flex-start; align-items:center; width:100%; gap:12px;">
                                        <div class="avatar-icon" style="background: rgba(var(--ac-rgb), 0.1); color: var(--ac); padding: 8px; border-radius: 50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <?= icon('user', 18) ?>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                            <span class="profile-name" style="font-weight:700; font-size:0.95rem; color:var(--text);">
                                                <?php if ($name): ?>
                                                    <?= htmlspecialchars(trunc($name, 14)) ?>
                                                <?php elseif ($uname): ?>
                                                    <span dir="ltr" style="display:inline-block;">@<?= htmlspecialchars(trunc($uname, 12)) ?></span>
                                                <?php else: ?>
                                                    <span style="opacity:0.8;"><?= $textbotlang['panel']['dashColName'] ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <div style="display:flex; align-items:center; gap:6px; font-size:0.8rem;">
                                                <?php if ($uname && $name): ?>
                                                    <span class="cm" style="color:var(--ac); direction:ltr; display:inline-block; font-weight:600;">@<?= htmlspecialchars(trunc($uname, 12)) ?></span>
                                                    <span style="color:var(--bd);">|</span>
                                                <?php endif; ?>
                                                <div class="profile-id-box" style="display:flex; align-items:center; gap:4px; color: var(--mute);">
                                                    <?= icon('hash', 12) ?>
                                                    <span class="cn" style="font-size:0.85rem; font-weight:600;"><?= htmlspecialchars($u['id']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColBalance'] ?>" class="cn desktop-text-center" style="text-align:right;">
                                    <div class="desktop-vertical-stack mobile-flex-between">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span class="icon-span" style="color:var(--mute)"><?= icon('wallet', 14) ?></span>
                                            <span class="mobile-label" style="display:none; color:var(--mute); font-weight:normal;">موجودی کاربر:</span>
                                        </div>
                                        <span class="cn" style="font-weight:600; font-size:1rem; color:var(--ac);">
                                            <?= number_format((int) ($u['Balance'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem"><?= $textbotlang['panel']['dashTomanShort2'] ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="تاریخ عضویت" class="desktop-text-center" style="text-align:right;">
                                    <div class="desktop-vertical-stack mobile-flex-between" style="font-size:0.85rem; color:var(--mute);">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span class="icon-span" style="color:var(--mute)"><?= icon('clock', 14) ?></span>
                                            <span class="mobile-label" style="display:none; color:var(--mute); font-weight:normal;">تاریخ عضویت:</span>
                                        </div>
                                        <span class="cn" style="font-weight:500; color:var(--text); display:inline-flex; align-items:center; gap:12px;">
                                            <span><?= safe_date($u['register'] ?? null, 'Y/m/d') ?></span>
                                            <span style="opacity:0.2; font-size:0.85em;">|</span>
                                            <span style="opacity:0.8; font-size:0.95em;"><?= safe_date($u['register'] ?? null, 'H:i') ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColGroup'] ?>">
                                    <?php if ($isBlocked): ?>
                                        <span class="status-pill danger"><?= $textbotlang['panel']['dashLabelBlocked'] ?></span>
                                    <?php else: ?>
                                        <span class="<?= $roleClass ?>"><?= $roleLabel ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php 
// Prepare data for Best Selling Bar Chart
$bestSellingLabels = [];
$bestSellingData = [];
$bestSellingColors = ['#0EA5E9', '#6366F1', '#14B8A6', '#F59E0B', '#8B5CF6']; // Soft Structuralism premium colors
if (!empty($bestSelling)) {
    foreach ($bestSelling as $prod) {
        $bestSellingLabels[] = trunc($prod['name_product'], 15);
        $bestSellingData[] = (int)$prod['sales_count'];
    }
}
?>

<script>
(function() {
    // Check if the theme is dark or light from CSS variables
    const isDark = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim() === '#0f172a' || document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)';

    // Persian Number Converter
    const toPersianNum = (num) => {
        const farsiDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return num.toString().replace(/\d/g, x => farsiDigits[x]);
    };

    // 1. Sales Line Chart
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Vazirmatn', system-ui, sans-serif";
    }
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        if (window.salesChartInstance) {
            window.salesChartInstance.destroy();
        }
        const labels = <?= json_encode($chartLabels) ?>;
        const data = <?= json_encode($chartData) ?>;

        const rootStyles = getComputedStyle(document.documentElement);
        const accentColor = rootStyles.getPropertyValue('--ac').trim() || '#3b82f6';

        const gradient = salesCtx.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, accentColor + '40'); // 25% opacity hex
        gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');

        window.salesChartInstance = new Chart(salesCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'درآمد',
                    data: data,
                    borderColor: accentColor,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: accentColor,
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                        titleColor: isDark ? '#f8fafc' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        titleFont: { family: 'Vazirmatn' },
                        bodyFont: { family: 'Vazirmatn', size: 13, weight: 'bold' },
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                return toPersianNum(new Intl.NumberFormat('en-US').format(context.parsed.y)) + ' تومان';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { 
                            color: textColor, 
                            font: { family: 'Vazirmatn, sans-serif', size: 11 },
                            callback: function(value) {
                                return this.getLabelForValue(value); // Let labels remain text like "شنبه"
                            }
                        }
                    },
                    y: {
                        grid: { color: isDark ? 'rgba(255, 255, 255, 0.03)' : 'rgba(0, 0, 0, 0.04)', drawBorder: false, borderDash: [5, 5] },
                        ticks: { 
                            color: textColor,
                            font: { family: 'Vazirmatn, sans-serif', size: 11 },
                            callback: function(value) {
                                if (value >= 1000000) return toPersianNum(value / 1000000) + ' میلیون';
                                if (value >= 1000) return toPersianNum(value / 1000) + ' هزار';
                                return toPersianNum(value);
                            }
                        },
                        beginAtZero: true
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    }

    // 2. Best Selling Bar Chart
    const bestCtx = document.getElementById('bestSellingChart');
    if (bestCtx) {
        if (window.bestChartInstance) {
            window.bestChartInstance.destroy();
        }
        const bsLabels = <?= json_encode($bestSellingLabels) ?>;
        const bsData = <?= json_encode($bestSellingData) ?>;
        const bsColors = <?= json_encode($bestSellingColors) ?>;

        window.bestChartInstance = new Chart(bestCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: bsLabels,
                datasets: [{
                    label: 'تعداد فروش',
                    data: bsData,
                    backgroundColor: bsColors,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 32 // Polish the bars width
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                        titleColor: isDark ? '#f8fafc' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        titleFont: { family: 'Vazirmatn' },
                        bodyFont: { family: 'Vazirmatn', size: 13, weight: 'bold' },
                        callbacks: {
                            label: function(context) {
                                return toPersianNum(context.parsed.y) + ' فروش';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { 
                            color: textColor, 
                            font: { family: 'Vazirmatn, sans-serif', size: 11 },
                            maxRotation: 45,
                            minRotation: 0,
                            callback: function(value) {
                                return toPersianNum(this.getLabelForValue(value));
                            }
                        }
                    },
                    y: {
                        grid: { color: isDark ? 'rgba(255, 255, 255, 0.03)' : 'rgba(0, 0, 0, 0.04)', drawBorder: false, borderDash: [5, 5] },
                        ticks: { 
                            color: textColor,
                            font: { family: 'Vazirmatn, sans-serif', size: 11 },
                            stepSize: 1,
                            callback: function(value) {
                                if (value >= 1000) return toPersianNum(value / 1000) + ' هزار';
                                return toPersianNum(value);
                            }
                        },
                        beginAtZero: true
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    }
})();
</script>

<style>
.dash-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.dash-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

/* Dash Card Layouts */
.dash-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 140px;
}
.dash-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-direction: row-reverse; /* Icon Left, Title Right */
    margin-bottom: 16px;
}
.dash-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--mute);
}
.icon-glow {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
}
.icon-glow.bg-blue { background: #0ea5e9; box-shadow: 0 6px 14px -3px rgba(14, 165, 233, 0.4); }
.icon-glow.bg-emerald { background: #10b981; box-shadow: 0 6px 14px -3px rgba(16, 185, 129, 0.4); }
.icon-glow.bg-purple { background: #8b5cf6; box-shadow: 0 6px 14px -3px rgba(139, 92, 246, 0.4); }
.icon-glow.bg-orange { background: #f59e0b; box-shadow: 0 6px 14px -3px rgba(245, 158, 11, 0.4); }
.icon-glow svg { width: 22px; height: 22px; }

.dash-card-footer {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-direction: row-reverse; /* Pill Left, Value Right */
}
.dash-card-value {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.dash-card-value-flex {
    display: flex;
    align-items: baseline;
    gap: 6px;
}
.dash-card-unit {
    font-size: 0.85rem;
    color: var(--mute);
    font-weight: 600;
}
.dash-card-pill .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
}
.status-pill.success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-pill.danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.status-pill.warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-pill.neutral { background: rgba(148, 163, 184, 0.15); color: var(--mute); }
.status-pill.panel-pill { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }

/* Table enhancements */
.tbl-sm th {
    padding: 16px 20px;
    font-size: 0.75rem;
    color: var(--mute);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--bd);
}
.tbl-sm td {
    padding: 18px 20px;
    font-weight: 500;
}

/* Responsive tweaks for the new dashboard grid */
@media (max-width: 1024px) {
    .dash-grid-2 {
        grid-template-columns: 1fr !important;
    }
}

/* Mobile optimizations for stats cards */
@media (max-width: 640px) {
    .dash-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
}
</style>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
