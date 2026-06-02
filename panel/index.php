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

// 2. Chart Data (Last 7 Days)
$chartLabels = [];
$chartData = [];
$persianDays = [
    'Saturday' => 'شنبه', 'Sunday' => 'یکشنبه', 'Monday' => 'دوشنبه',
    'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه', 'Thursday' => 'پنجشنبه', 'Friday' => 'جمعه'
];

try {
    for ($i = 6; $i >= 0; $i--) {
        $startOfDay = strtotime("-$i days 00:00:00");
        $endOfDay = strtotime("-$i days 23:59:59");
        $dayRev = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND time_sell BETWEEN ? AND ?", [$startOfDay, $endOfDay])->fetchColumn();
        
        $chartLabels[] = $persianDays[date('l', $startOfDay)];
        $chartData[] = $dayRev;
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
    <div class="card" style="padding: 24px; text-align: center;">
        <div style="font-size: 0.95rem; color: var(--cf); margin-bottom: 15px; font-weight: 500;"><?= $textbotlang['panel']['dashTotalUsers'] ?></div>
        <div style="font-size: 2.8rem; font-weight: 700; color: var(--ct); margin-bottom: 10px; line-height: 1;">
            <?= number_format($totalUsers) ?>
        </div>
        <div style="font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 5px;">
            <?php if ($newToday > 0): ?>
                <span style="color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 4px 8px; border-radius: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    <?= $newToday ?> <?= $textbotlang['panel']['dashTodaySpan'] ?>
                </span>
            <?php else: ?>
                <span style="color: var(--cf);"><?= $textbotlang['panel']['dashNoChange'] ?></span>
            <?php endif; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--cf); margin-top: 10px; opacity: 0.8;">در مقایسه با روزهای قبل</div>
    </div>
    
    <!-- Stat 2: Total Revenue -->
    <div class="card" style="padding: 24px; text-align: center;">
        <div style="font-size: 0.95rem; color: var(--cf); margin-bottom: 15px; font-weight: 500;"><?= $textbotlang['panel']['dashTotalRevenue'] ?></div>
        <div style="font-size: 2.8rem; font-weight: 700; color: var(--ct); margin-bottom: 10px; line-height: 1; direction: ltr;">
            <?= $totalRevenue >= 1_000_000
                ? number_format($totalRevenue / 1_000_000, 1) . '<span style="font-size:1rem;font-weight:500;margin-left:4px">M</span>'
                : number_format($totalRevenue) ?>
        </div>
        <div style="font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 5px;">
            <?php if ($todayRevenue > 0): ?>
                <span style="color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 4px 8px; border-radius: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    <?= $todayRevenue >= 1_000_000 ? number_format($todayRevenue / 1_000_000, 1) . 'M' : number_format($todayRevenue) ?> <?= $textbotlang['panel']['dashUnitToman'] ?>
                </span>
            <?php else: ?>
                <span style="color: var(--cf);">0 <?= $textbotlang['panel']['dashUnitToman'] ?> امروز</span>
            <?php endif; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--cf); margin-top: 10px; opacity: 0.8;">درآمد خالص امروز</div>
    </div>
    
    <!-- Stat 3: Active Services -->
    <div class="card" style="padding: 24px; text-align: center;">
        <div style="font-size: 0.95rem; color: var(--cf); margin-bottom: 15px; font-weight: 500;"><?= $textbotlang['panel']['dashActiveService'] ?></div>
        <div style="font-size: 2.8rem; font-weight: 700; color: var(--ct); margin-bottom: 10px; line-height: 1;">
            <?= number_format($activeNow) ?>
        </div>
        <div style="font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 5px;">
            <span style="color: #6366f1; background: rgba(99, 102, 241, 0.1); padding: 4px 8px; border-radius: 6px;">
                <?= $activePanels ?> <?= $textbotlang['panel']['dashActivePanels'] ?>
            </span>
        </div>
        <div style="font-size: 0.75rem; color: var(--cf); margin-top: 10px; opacity: 0.8;">سرویس‌های در حال استفاده</div>
    </div>

    <!-- Stat 4: Today's Transactions -->
    <div class="card" style="padding: 24px; text-align: center;">
        <div style="font-size: 0.95rem; color: var(--cf); margin-bottom: 15px; font-weight: 500;">تراکنش امروز</div>
        <div style="font-size: 2.8rem; font-weight: 700; color: var(--ct); margin-bottom: 10px; line-height: 1;">
            <?= number_format($txToday) ?>
        </div>
        <div style="font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 5px;">
            <?php if ($pendingPay > 0): ?>
                <span style="color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 4px 8px; border-radius: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                    <?= $pendingPay ?> <?= $textbotlang['panel']['dashPendingPayment'] ?>
                </span>
            <?php else: ?>
                <span style="color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 4px 8px; border-radius: 6px;">
                    <?= $textbotlang['panel']['dashStatusRegistered'] ?>
                </span>
            <?php endif; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--cf); margin-top: 10px; opacity: 0.8;">
            <?= $pendingPay > 0 ? $textbotlang['panel']['dashReviewLink'] : 'تمامی پرداخت‌ها تایید شده' ?>
        </div>
    </div>

</div>

<!-- Charts Grid -->
<div class="dash-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    
    <!-- Sales Chart -->
    <div class="card fade-up">
        <div class="card-head">
            <div>
                <div class="card-title"><?= $textbotlang['panel']['dashSalesChartTitle'] ?></div>
                <div class="card-subtitle">مجموع فروش ۷ روز گذشته (تومان)</div>
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
                            'active' => ['tag-ok', $textbotlang['panel']['dashStatusActive']],
                            'end_of_time' => ['tag-warn', $textbotlang['panel']['dashStatusExpired']],
                            'end_of_volume' => ['tag-no', $textbotlang['panel']['dashStatusVolumeFinished']],
                            'sendedwarn' => ['tag-warn', $textbotlang['panel']['dashStatusWarning']],
                            'send_on_hold' => ['tag-plain', $textbotlang['panel']['dashStatusWaiting']],
                        ];
                        foreach ($recentInvoices as $inv):
                            [$tagClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['tag-plain', $inv['Status'] ?? '—'];
                            ?>
                            <tr>
                                <td class="cm cf"><?= htmlspecialchars($inv['id_user'] ?? '—') ?></td>
                                <td class="cs" style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <?= htmlspecialchars(trunc($inv['name_product'] ?? '—', 20)) ?>
                                </td>
                                <td class="cn" style="white-space:nowrap">
                                    <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['dashTomanShort'] ?></span>
                                </td>
                                <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
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
                            ?>
                            <tr>
                                <td>
                                    <?php if ($name): ?>
                                        <span class="cs"><?= htmlspecialchars(trunc($name, 14)) ?></span>
                                    <?php elseif ($uname): ?>
                                        <span class="cm" style="color:var(--ac)">@<?= htmlspecialchars(trunc($uname, 12)) ?></span>
                                    <?php else: ?>
                                        <span class="cf"><?= htmlspecialchars($u['id']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="cn" style="white-space:nowrap">
                                    <?= number_format((int) ($u['Balance'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['dashTomanShort2'] ?></span>
                                </td>
                                <td>
                                    <?php if ($isBlocked): ?>
                                        <span class="tag tag-no" style="font-size:.65rem"><?= $textbotlang['panel']['dashLabelBlocked'] ?></span>
                                    <?php else: ?>
                                        <span class="tag <?= user_role_tag($agent) ?>" style="font-size:.65rem">
                                            <?= user_role_label($agent) ?>
                                        </span>
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
    const gridColor = isDark ? '#1e293b' : '#e2e8f0';

    // 1. Sales Line Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        const labels = <?= json_encode($chartLabels) ?>;
        const data = <?= json_encode($chartData) ?>;

        new Chart(salesCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'درآمد (تومان)',
                    data: data,
                    borderColor: '#3b82f6', // Match reference blue line
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: false, // Solid line without fill, more like the reference design
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                        titleColor: isDark ? '#f8fafc' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? '#334155' : '#cbd5e1',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return new Intl.NumberFormat('fa-IR').format(context.parsed.y) + ' تومان';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: textColor, font: { family: 'Vazirmatn, sans-serif', size: 11 } }
                    },
                    y: {
                        grid: { color: gridColor, drawBorder: false, borderDash: [5, 5] },
                        ticks: { 
                            color: textColor,
                            font: { family: 'Vazirmatn, sans-serif', size: 11 },
                            callback: function(value) {
                                if (value >= 1000000) return (value / 1000000) + 'M';
                                if (value >= 1000) return (value / 1000) + 'K';
                                return value;
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
                    borderRadius: 6, // rounded corners for bars
                    borderSkipped: false,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                        titleColor: isDark ? '#f8fafc' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? '#334155' : '#cbd5e1',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return new Intl.NumberFormat('fa-IR').format(context.parsed.y) + ' فروش';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: textColor, font: { family: 'Vazirmatn, sans-serif', size: 11 } }
                    },
                    y: {
                        grid: { color: gridColor, drawBorder: false, borderDash: [5, 5] },
                        ticks: { 
                            color: textColor,
                            font: { family: 'Vazirmatn, sans-serif', size: 11 },
                            stepSize: 1, // Sales are integers
                            callback: function(value) {
                                if (value >= 1000) return (value / 1000) + 'K';
                                return value;
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
/* Responsive tweaks for the new dashboard grid */
@media (max-width: 1024px) {
    .dash-grid-2 {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>