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
    $recentInvoices = db_fetchAll($pdo, "SELECT * FROM invoice ORDER BY time_sell DESC LIMIT 6");
    $recentUsers = db_fetchAll($pdo, "SELECT * FROM user ORDER BY register DESC LIMIT 6");
} catch (Exception $e) {}

$pageTitle = $textbotlang['panel']['dashboardTitle'];
$activeNav = 'dashboard';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Top Statistics Cards -->
<div class="stats fade-up" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <!-- Stat 1: Total Users -->
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;"><?= $textbotlang['panel']['dashTotalUsers'] ?></div>
            <div class="icon-glow bg-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($totalUsers) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <?php if ($newToday > 0): ?>
                <span class="status-pill success">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 2px;"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    <?= $newToday ?> <?= $textbotlang['panel']['dashTodaySpan'] ?>
                </span>
            <?php else: ?>
                <span class="status-pill neutral"><?= $textbotlang['panel']['dashNoChange'] ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Stat 2: Total Revenue -->
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;"><?= $textbotlang['panel']['dashTotalRevenue'] ?></div>
            <div class="icon-glow bg-emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1; direction: ltr; display:flex; justify-content:flex-end;">
            <?= $totalRevenue >= 1_000_000
                ? number_format($totalRevenue / 1_000_000, 1) . '<span style="font-size:1.2rem;font-weight:600;margin-left:6px;align-self:flex-end;margin-bottom:2px">M</span>'
                : number_format($totalRevenue) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; justify-content: flex-end; gap: 6px;">
            <?php if ($todayRevenue > 0): ?>
                <span class="status-pill success">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 2px;"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    <?= $todayRevenue >= 1_000_000 ? number_format($todayRevenue / 1_000_000, 1) . 'M' : number_format($todayRevenue) ?> <?= $textbotlang['panel']['dashUnitToman'] ?>
                </span>
            <?php else: ?>
                <span class="status-pill neutral">0 <?= $textbotlang['panel']['dashUnitToman'] ?> امروز</span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Stat 3: Active Services -->
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;"><?= $textbotlang['panel']['dashActiveService'] ?></div>
            <div class="icon-glow bg-purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($activeNow) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <span class="status-pill" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                <?= $activePanels ?> <?= $textbotlang['panel']['dashActivePanels'] ?>
            </span>
        </div>
    </div>

    <!-- Stat 4: Today's Transactions -->
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;">تراکنش امروز</div>
            <div class="icon-glow bg-orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($txToday) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <?php if ($pendingPay > 0): ?>
                <span class="status-pill danger">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 2px;"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                    <?= $pendingPay ?> <?= $textbotlang['panel']['dashPendingPayment'] ?>
                </span>
            <?php else: ?>
                <span class="status-pill success">
                    <?= $textbotlang['panel']['dashStatusRegistered'] ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="dash-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    
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
<div class="dash-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    
    <!-- Recent Orders -->
    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['dashRecentOrders'] ?></div>
                <div class="card-subtitle"><?= count($recentInvoices) ?> <?= $textbotlang['panel']['dashRecentItem'] ?></div>
            </div>
            <a href="invoice.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['dashViewAll'] ?></a>
        </div>
        <div class="tbl-wrap">
            <table class="tbl-sm">
                <thead>
                    <tr>
                        <th><?= $textbotlang['panel']['dashColUser'] ?></th>
                        <th><?= $textbotlang['panel']['dashColProduct'] ?></th>
                        <th><?= $textbotlang['panel']['dashColAmount'] ?></th>
                        <th><?= $textbotlang['panel']['dashColStatus'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentInvoices)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty" style="padding:24px">
                                    <p><?= $textbotlang['panel']['dashNoOrdersYet'] ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        $statusMap = [
                            'active' => ['status-pill success', $textbotlang['panel']['dashStatusActive']],
                            'end_of_time' => ['status-pill warning', $textbotlang['panel']['dashStatusExpired']],
                            'end_of_volume' => ['status-pill danger', $textbotlang['panel']['dashStatusVolumeFinished']],
                            'sendedwarn' => ['status-pill warning', $textbotlang['panel']['dashStatusWarning']],
                            'send_on_hold' => ['status-pill neutral', $textbotlang['panel']['dashStatusWaiting']],
                        ];
                        foreach ($recentInvoices as $inv):
                            [$pillClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['status-pill neutral', $inv['Status'] ?? '—'];
                            ?>
                            <tr style="border-bottom: 1px solid var(--bd);">
                                <td data-label="<?= $textbotlang['panel']['dashColUser'] ?>" class="cm cf"><div style="display:flex; align-items:center; gap:8px;">
                                    <div style="width:28px;height:28px;border-radius:50%;background:rgba(148, 163, 184, 0.1);color:var(--cf);display:flex;align-items:center;justify-content:center;font-size:10px;"><i data-lucide="user"></i></div>
                                    <?= htmlspecialchars($inv['id_user'] ?? '—') ?>
                                </div></td>
                                <td data-label="<?= $textbotlang['panel']['dashColProduct'] ?>" class="cs" style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <span style="font-weight:600; color:var(--ct)"><?= htmlspecialchars(trunc($inv['name_product'] ?? '—', 20)) ?></span>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColAmount'] ?>" class="cn" style="white-space:nowrap; font-weight:500;">
                                    <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem"><?= $textbotlang['panel']['dashTomanShort'] ?></span>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColStatus'] ?>"><span class="<?= $pillClass ?>"><?= $label ?></span></td>
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
                <div class="card-title"><?= $textbotlang['panel']['dashRecentUsers'] ?></div>
                <div class="card-subtitle"><?= count($recentUsers) ?> <?= $textbotlang['panel']['dashRecentItem2'] ?></div>
            </div>
            <a href="users.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['dashViewAll2'] ?></a>
        </div>
        <div class="tbl-wrap">
            <table class="tbl-sm">
                <thead>
                    <tr>
                        <th><?= $textbotlang['panel']['dashColName'] ?></th>
                        <th><?= $textbotlang['panel']['dashColBalance'] ?></th>
                        <th><?= $textbotlang['panel']['dashColGroup'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr>
                            <td colspan="3">
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
                                <td data-label="<?= $textbotlang['panel']['dashColName'] ?>">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:32px;height:32px;border-radius:10px;background:var(--bg);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;color:var(--cf);">
                                            <i data-lucide="user"></i>
                                        </div>
                                        <div style="display:flex; flex-direction:column; justify-content:center;">
                                            <?php if ($name): ?>
                                                <span class="cs" style="font-weight:600; line-height:1.2;"><?= htmlspecialchars(trunc($name, 14)) ?></span>
                                                <span style="font-size:0.7rem; color:var(--cf); line-height:1.2; margin-top:2px;"><?= htmlspecialchars($u['id']) ?></span>
                                            <?php elseif ($uname): ?>
                                                <span class="cm" style="color:var(--ac); font-weight:600; line-height:1.2;">@<?= htmlspecialchars(trunc($uname, 12)) ?></span>
                                                <span style="font-size:0.7rem; color:var(--cf); line-height:1.2; margin-top:2px;"><?= htmlspecialchars($u['id']) ?></span>
                                            <?php else: ?>
                                                <span class="cf" style="font-weight:600;"><?= htmlspecialchars($u['id']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="<?= $textbotlang['panel']['dashColBalance'] ?>" class="cn" style="white-space:nowrap; font-weight:500;">
                                    <?= number_format((int) ($u['Balance'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem"><?= $textbotlang['panel']['dashTomanShort2'] ?></span>
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
$bestSellingColors = ['#3b82f6', '#8b5cf6', '#14b8a6', '#f59e0b', '#ec4899']; // Colors for bars
if (!empty($bestSelling)) {
    foreach ($bestSelling as $prod) {
        $bestSellingLabels[] = trunc($prod['name_product'], 15);
        $bestSellingData[] = (int)$prod['sales_count'];
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        const labels = <?= json_encode($chartLabels) ?>;
        const data = <?= json_encode($chartData) ?>;

        const gradient = salesCtx.getContext('2d').createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.25)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

        new Chart(salesCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'درآمد',
                    data: data,
                    borderColor: '#3b82f6', // Match reference blue line
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#3b82f6',
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
        const bsLabels = <?= json_encode($bestSellingLabels) ?>;
        const bsData = <?= json_encode($bestSellingData) ?>;
        const bsColors = <?= json_encode($bestSellingColors) ?>;

        new Chart(bestCtx.getContext('2d'), {
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
});
</script>

<style>
.dash-card {
    position: relative;
    overflow: hidden;
    background: var(--bg); /* Assume there's a card background variable, or just use var(--bg) */
    border: 1px solid var(--bd);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.dash-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.06);
}
.icon-glow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    margin-bottom: 0px;
}
.icon-glow svg {
    width: 24px;
    height: 24px;
}
.bg-purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.bg-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.bg-emerald { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.bg-orange { background: rgba(249, 115, 22, 0.1); color: #f97316; }

.status-pill {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}
.status-pill.success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-pill.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.status-pill.danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.status-pill.neutral { background: rgba(100, 116, 139, 0.1); color: #64748b; }

/* Table enhancements */
.tbl-sm th {
    padding: 12px 16px;
    font-size: 0.8rem;
    color: var(--cf);
    text-transform: uppercase;
    font-weight: 600;
}
.tbl-sm td {
    padding: 14px 16px;
}

/* Responsive tweaks for the new dashboard grid */
@media (max-width: 1024px) {
    .dash-grid-2 {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>