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
        LIMIT 8
    ");
    $recentUsers = db_fetchAll($pdo, "SELECT * FROM user ORDER BY register DESC LIMIT 8");
} catch (Exception $e) {
    error_log('index.php recent query error: ' . $e->getMessage());
}
$pageTitle = $textbotlang['panel']['dashboardTitle'];
$activeNav = 'dashboard';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>
<div class="stats dash-stats fade-up">
    <!-- Stat 1: Total Users -->
    <div class="dash-card" style="border-top: 3px solid #00f2fe !important;">
        <div class="dash-card-header">
            <div class="icon-glow" style="background: rgba(0, 242, 254, 0.15); color: #00f2fe; box-shadow: 0 4px 20px -2px rgba(0, 242, 254, 0.3);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                <div class="dash-card-title"><?= $textbotlang['panel']['dashTotalUsers'] ?></div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span class="glow-dot info"></span>
                    <span style="font-size: 0.72rem; color: var(--mute); font-weight: 600;">سرویس فعال کاربران</span>
                </div>
            </div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <?php if ($newToday > 0): ?>
                    <span class="status-pill success" style="font-weight: 700;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 4px; transform: rotate(180deg);"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                        <?= $newToday ?> <?= $textbotlang['panel']['dashTodaySpan'] ?>
                    </span>
                <?php else: ?>
                    <span class="status-pill neutral"><?= $textbotlang['panel']['dashNoChange'] ?></span>
                <?php endif; ?>
            </div>
            <div class="dash-card-value-flex">
                <span class="dash-card-value cn"><?= number_format($totalUsers) ?></span>
            </div>
        </div>
    </div>
    
    <!-- Stat 2: Total Revenue -->
    <div class="dash-card" style="border-top: 3px solid #10b981 !important;">
        <div class="dash-card-header">
            <div class="icon-glow" style="background: rgba(16, 185, 129, 0.15); color: #10b981; box-shadow: 0 4px 20px -2px rgba(16, 185, 129, 0.3);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                <div class="dash-card-title"><?= $textbotlang['panel']['dashTotalRevenue'] ?></div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span class="glow-dot success"></span>
                    <span style="font-size: 0.72rem; color: var(--mute); font-weight: 600;">درآمد تایید شده</span>
                </div>
            </div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <?php if ($todayRevenue > 0): ?>
                    <span class="status-pill success" style="font-weight: 700;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 4px; transform: rotate(180deg);"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                        <?= $todayRevenue >= 1_000_000 ? number_format($todayRevenue / 1_000_000, 1) . 'M' : number_format($todayRevenue) ?>
                    </span>
                <?php else: ?>
                    <span class="status-pill neutral">۰ تومان امروز</span>
                <?php endif; ?>
            </div>
            <div class="dash-card-value-flex">
                <span class="dash-card-value cn">
                    <?= $totalRevenue >= 1_000_000 ? number_format($totalRevenue / 1_000_000, 1) : number_format($totalRevenue) ?>
                </span>
                <span class="dash-card-unit">
                    <?= $totalRevenue >= 1_000_000 ? 'میلیون تومان' : 'تومان' ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Stat 3: Active Services -->
    <div class="dash-card" style="border-top: 3px solid #8b5cf6 !important;">
        <div class="dash-card-header">
            <div class="icon-glow" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; box-shadow: 0 4px 20px -2px rgba(139, 92, 246, 0.3);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                <div class="dash-card-title"><?= $textbotlang['panel']['dashActiveService'] ?></div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span class="glow-dot warning"></span>
                    <span style="font-size: 0.72rem; color: var(--mute); font-weight: 600;">سرورهای متصل</span>
                </div>
            </div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill panel-pill" style="font-weight: 700;">
                    <?= $activePanels ?> پنل متصل
                </span>
            </div>
            <div class="dash-card-value-flex">
                <span class="dash-card-value cn"><?= number_format($activeNow) ?></span>
            </div>
        </div>
    </div>

    <!-- Stat 4: Today's Transactions -->
    <div class="dash-card" style="border-top: 3px solid #f59e0b !important;">
        <div class="dash-card-header">
            <div class="icon-glow" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; box-shadow: 0 4px 20px -2px rgba(245, 158, 11, 0.3);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                <div class="dash-card-title">تراکنش امروز</div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span class="glow-dot danger"></span>
                    <span style="font-size: 0.72rem; color: var(--mute); font-weight: 600;">گزارش‌های واریز</span>
                </div>
            </div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <?php if ($pendingPay > 0): ?>
                    <span class="status-pill danger" style="font-weight: 700;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 4px;"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                        <?= $pendingPay ?> <?= $textbotlang['panel']['dashPendingPayment'] ?>
                    </span>
                <?php else: ?>
                    <span class="status-pill success" style="font-weight: 700;">
                        تراکنش بدون مشکل
                    </span>
                <?php endif; ?>
            </div>
            <div class="dash-card-value-flex">
                <span class="dash-card-value cn"><?= number_format($txToday) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Charts Grid (Asymmetric Layout) -->
<div class="dash-grid-asym">
    <!-- Sales Chart -->
    <div class="card fade-up">
        <div class="card-head" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['dashSalesChartTitle'] ?></div>
                <div class="card-subtitle"><?= $chartSubtitle ?></div>
            </div>
            <div>
                <select class="form-select" style="font-size:0.75rem; padding: 6px 24px 6px 12px; border-radius:8px; background-color:var(--sf2); color:var(--text); border:1px solid var(--bd); cursor:pointer;" onchange="window.location.href='index.php?range='+this.value">
                    <option value="24h" <?= $range === '24h' ? 'selected' : '' ?>>۲۴ ساعت گذشته</option>
                    <option value="7d" <?= $range === '7d' ? 'selected' : '' ?>>۷ روز گذشته</option>
                    <option value="30d" <?= $range === '30d' ? 'selected' : '' ?>>۱ ماه گذشته</option>
                </select>
            </div>
        </div>
        <div class="card-body" style="position: relative; height: 300px; width: 100%; padding: 16px 20px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Best Selling Products List -->
    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['dashBestSellingTitle'] ?></div>
                <div class="card-subtitle">برترین محصولات ربات (سهم از فروش)</div>
            </div>
        </div>
        <div class="card-body" style="padding: 10px 0;">
            <?php if (empty($bestSelling)): ?>
                <div class="empty" style="height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                    <p>داده‌ای برای نمایش وجود ندارد</p>
                </div>
            <?php else: ?>
                <div class="progress-list">
                    <?php 
                    $maxSales = max(array_column($bestSelling, 'sales_count')) ?: 1;
                    foreach ($bestSelling as $prod): 
                        $percentage = round(($prod['sales_count'] / $maxSales) * 100);
                    ?>
                        <div class="progress-item">
                            <div class="progress-item-header">
                                <span class="progress-item-title"><?= htmlspecialchars(trunc($prod['name_product'], 20)) ?></span>
                                <span class="progress-item-meta">
                                    <span class="cn" style="color: var(--ac); font-weight: 700;"><?= number_format($prod['sales_count']) ?></span> فروش
                                    <span style="color: var(--bd); margin: 0 4px;">|</span>
                                    <span class="cn" style="font-weight: 700;"><?= number_format($prod['total_earned']) ?></span> <small style="font-size:0.7rem; color:var(--mute);">ت</small>
                                </span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?= $percentage ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
                <div class="card-title" style="font-size: 1.15rem; font-weight: 800;"><?= $textbotlang['panel']['dashRecentOrders'] ?></div>
                <div class="card-subtitle"><?= count($recentInvoices) ?> <?= $textbotlang['panel']['dashRecentItem'] ?></div>
            </div>
            <a href="invoice.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['dashViewAll'] ?></a>
        </div>
        <div class="tbl-wrap dash-orders">
            <!-- Desktop Table View -->
            <table class="tbl-sm responsive-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="text-align:right;"><?= $textbotlang['panel']['dashColUser'] ?></th>
                        <th style="text-align:right;"><?= $textbotlang['panel']['dashColProduct'] ?></th>
                        <th style="text-align:right;"><?= $textbotlang['panel']['dashColAmount'] ?></th>
                        <th style="text-align:right;">تاریخ ثبت</th>
                        <th style="text-align:right;"><?= $textbotlang['panel']['dashColStatus'] ?></th>
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
                                    <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                                        <div style="background: var(--acs); color: var(--ac); width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <?= icon('user', 16) ?>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:2px; min-width:0; overflow:hidden;">
                                            <span style="font-weight:700; font-size:0.85rem; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                <?php
                                                $displayName = trim($inv['name'] ?? '');
                                                if ($displayName === 'none') $displayName = '';
                                                $displayUname = trim($inv['username'] ?? '');
                                                if ($displayUname === 'none') $displayUname = '';
                                                if (!empty($displayName)) echo htmlspecialchars(trunc($displayName, 16));
                                                elseif (!empty($displayUname)) echo '@' . htmlspecialchars(trunc($displayUname, 16));
                                                else echo 'کاربر بی‌نام';
                                                ?>
                                            </span>
                                            <span class="cm" style="font-size:0.75rem; color:var(--mute);">#<?= htmlspecialchars($inv['id_user']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:right;">
                                    <span style="font-weight:600; font-size:0.85rem; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;"><?= htmlspecialchars(trunc($inv['name_product'] ?? '—', 16)) ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-weight:700; font-size:0.9rem; color:var(--ac); white-space:nowrap;">
                                        <?= number_format((int) ($inv['price_product'] ?? 0)) ?>
                                        <small style="font-size:0.7rem; color:var(--mute); font-weight:500;"><?= $textbotlang['panel']['dashTomanShort'] ?></small>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div style="font-size:0.8rem; color:var(--text2); white-space:nowrap;">
                                        <div><?= safe_date($inv['time_sell'] ?? null, 'Y/m/d') ?></div>
                                        <div style="font-size:0.72rem; color:var(--mute);"><?= safe_date($inv['time_sell'] ?? null, 'H:i') ?></div>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <span class="<?= $pillClass ?>" style="font-size:0.72rem; padding:4px 8px; white-space:nowrap;"><?= $label ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- Mobile Card View -->
            <div class="mobile-card-list">
                <?php if (empty($recentInvoices)): ?>
                    <div class="empty" style="padding:24px">
                        <p><?= $textbotlang['panel']['dashNoOrdersYet'] ?></p>
                    </div>
                <?php else:
                    foreach ($recentInvoices as $inv):
                        $rawStatus = strtolower(trim($inv['Status'] ?? ''));
                        [$pillClass, $label] = $statusMap[$rawStatus] ?? ['status-pill neutral', $inv['Status'] ?? '—'];
                        $displayName = trim($inv['name'] ?? '');
                        if ($displayName === 'none') $displayName = '';
                        $displayUname = trim($inv['username'] ?? '');
                        if ($displayUname === 'none') $displayUname = '';
                        $userShow = !empty($displayName) ? $displayName : (!empty($displayUname) ? '@' . $displayUname : 'کاربر بی‌نام');
                        ?>
                        <div class="mobile-card-item">
                            <div class="mobile-card-row">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="background: var(--acs); color: var(--ac); width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                                        <?= icon('user', 14) ?>
                                    </div>
                                    <span style="font-weight:700; font-size:0.85rem; color:var(--text);"><?= htmlspecialchars(trunc($userShow, 18)) ?></span>
                                </div>
                                <span class="<?= $pillClass ?>" style="font-size:0.7rem; padding:2px 8px;"><?= $label ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">محصول:</span>
                                <span class="mobile-card-value"><?= htmlspecialchars($inv['name_product'] ?? '—') ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">مبلغ:</span>
                                <span class="mobile-card-value" style="color: var(--ac);"><?= number_format((int)($inv['price_product'] ?? 0)) ?> تومان</span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">تاریخ ثبت:</span>
                                <span class="mobile-card-value" style="font-size:0.75rem; color: var(--mute);"><?= safe_date($inv['time_sell'] ?? null, 'Y/m/d H:i') ?></span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Users -->
    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title" style="font-size: 1.15rem; font-weight: 800;"><?= $textbotlang['panel']['dashRecentUsers'] ?></div>
                <div class="card-subtitle"><?= count($recentUsers) ?> <?= $textbotlang['panel']['dashRecentItem2'] ?></div>
            </div>
            <a href="users.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['dashViewAll2'] ?></a>
        </div>
        <div class="tbl-wrap dash-users">
            <!-- Desktop Table View -->
            <table class="tbl-sm responsive-table">
                <thead>
                    <tr>
                        <th><?= $textbotlang['panel']['dashColName'] ?></th>
                        <th style="text-align:center;"><?= $textbotlang['panel']['dashColBalance'] ?></th>
                        <th style="text-align:center;">تاریخ عضویت</th>
                        <th style="text-align:center;"><?= $textbotlang['panel']['dashColGroup'] ?></th>
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
                                                    <span class="cn cm" style="font-size:0.85rem; font-weight:600;"><?= htmlspecialchars($u['id']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColBalance'] ?>" class="cn" style="text-align:center;">
                                    <span class="cn" style="font-weight:600; font-size:1rem; color:var(--ac);">
                                        <?= number_format((int) ($u['Balance'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem"><?= $textbotlang['panel']['dashTomanShort2'] ?></span>
                                    </span>
                                </td>
                                <td data-label="تاریخ عضویت" style="text-align:center;">
                                    <span class="cn" style="font-weight:500; color:var(--text); display:inline-flex; align-items:center; gap:12px;">
                                        <span><?= safe_date($u['register'] ?? null, 'Y/m/d') ?></span>
                                        <span style="opacity:0.2; font-size:0.85em;">|</span>
                                        <span style="opacity:0.8; font-size:0.95em;"><?= safe_date($u['register'] ?? null, 'H:i') ?></span>
                                    </span>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColGroup'] ?>" style="text-align:center;">
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

            <!-- Mobile Card View -->
            <div class="mobile-card-list">
                <?php if (empty($recentUsers)): ?>
                    <div class="empty" style="padding:24px"><p><?= $textbotlang['panel']['dashNoUsersYet'] ?></p></div>
                <?php else:
                    foreach ($recentUsers as $u):
                        $agent = $u['agent'] ?? 'f';
                        $isBlocked = ($u['User_Status'] ?? '') === 'block';
                        $name = $u['namecustom'] ?? '';
                        if ($name === 'none') $name = '';
                        $uname = $u['username'] ?? '';
                        if ($uname === 'none') $uname = '';
                        $userShow = !empty($name) ? $name : (!empty($uname) ? '@' . $uname : 'کاربر بی‌نام');
                        
                        $roleLabel = user_role_label($agent);
                        $roleClass = 'status-pill ';
                        if ($agent === 'all') $roleClass .= 'danger';
                        elseif ($agent === 'n' || $agent === 'n2') $roleClass .= 'success';
                        else $roleClass .= 'neutral';
                        ?>
                        <div class="mobile-card-item">
                            <div class="mobile-card-row">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="background: rgba(var(--ac-rgb), 0.1); color: var(--ac); padding: 6px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                                        <?= icon('user', 14) ?>
                                    </div>
                                    <span style="font-weight:700; font-size:0.85rem; color:var(--text);"><?= htmlspecialchars(trunc($userShow, 18)) ?></span>
                                </div>
                                <?php if ($isBlocked): ?>
                                    <span class="status-pill danger" style="font-size:0.7rem; padding:2px 8px;"><?= $textbotlang['panel']['dashLabelBlocked'] ?></span>
                                <?php else: ?>
                                    <span class="<?= $roleClass ?>" style="font-size:0.7rem; padding:2px 8px;"><?= $roleLabel ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">شناسه کاربر:</span>
                                <span class="mobile-card-value" style="font-family: var(--mono); font-size:0.78rem;">#<?= htmlspecialchars($u['id']) ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">موجودی کیف پول:</span>
                                <span class="mobile-card-value" style="color: var(--ac);"><?= number_format((int)($u['Balance'] ?? 0)) ?> تومان</span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">تاریخ عضویت:</span>
                                <span class="mobile-card-value" style="font-size:0.75rem; color: var(--mute);"><?= safe_date($u['register'] ?? null, 'Y/m/d H:i') ?></span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
(function() {
    // Check if the theme is dark or light from CSS variables
    const isDark = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim() === '#090d16' || document.documentElement.getAttribute('data-theme') === 'navy';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.04)' : 'rgba(0, 0, 0, 0.05)';

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
        const accentColor = rootStyles.getPropertyValue('--ac').trim() || '#00f2fe';

        const gradient = salesCtx.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, accentColor + '30'); // Hex alpha for ~18% opacity
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
                    pointBackgroundColor: isDark ? '#090d16' : '#ffffff',
                    pointBorderColor: accentColor,
                    pointBorderWidth: 2.5,
                    pointRadius: 4.5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.38
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.85)' : 'rgba(255, 255, 255, 0.92)',
                        titleColor: isDark ? '#f8fafc' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.08)',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 12,
                        displayColors: false,
                        titleFont: { family: 'Vazirmatn', weight: 'bold' },
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
                                return this.getLabelForValue(value);
                            }
                        }
                    },
                    y: {
                        grid: { color: gridColor, drawBorder: false, borderDash: [6, 6] },
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
})();
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
