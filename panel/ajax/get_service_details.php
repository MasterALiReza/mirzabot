<?php
// Buffer all output
ob_start();
session_start();
require '../inc/config.php';
require_once '../inc/icons.php';
ob_end_clean();

require_auth();
csrf_check_get();

$old_cwd = getcwd();
chdir(__DIR__ . '/../../');
require_once 'function.php';
require_once 'panels.php';
chdir($old_cwd);

$id_invoice = $_GET['id_invoice'] ?? '';
$id_user = (int)($_GET['id_user'] ?? 0);

if (empty($id_invoice) || !$id_user) {
    http_response_code(400);
    echo '<div style="padding:20px;text-align:center;color:var(--no);">درخواست نامعتبر است.</div>';
    exit;
}

try {
    $invoice = db_fetch($pdo, "SELECT * FROM invoice WHERE id_invoice = ? AND id_user = ?", [$id_invoice, $id_user]);
    if (!$invoice) {
        http_response_code(404);
        echo '<div style="padding:20px;text-align:center;color:var(--no);">سفارش مورد نظر یافت نشد.</div>';
        exit;
    }

    $ManagePanel = new ManagePanel();
    $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);

    if (isset($DataUserOut['status']) && $DataUserOut['status'] == "Unsuccessful") {
        $err_msg = !empty($DataUserOut['msg']) ? htmlspecialchars(is_string($DataUserOut['msg']) ? $DataUserOut['msg'] : json_encode($DataUserOut['msg'])) : "نامشخص";
        echo '<div style="padding:20px;text-align:center;color:var(--no);">
                <p style="margin-bottom:10px;">خطا در ارتباط با سرور یا کاربر در پنل وجود ندارد.</p>
                <p style="font-size:0.85em; opacity:0.8; word-break: break-all;">جزئیات: ' . $err_msg . '</p>
              </div>';
    }

    // Prepare status translation
    $statusMap = [
        'active' => ['tag-ok', $textbotlang['users']['status']['active'] ?? 'فعال'],
        'limited' => ['tag-warn', $textbotlang['users']['status']['limited'] ?? 'محدود شده'],
        'disabled' => ['tag-no', $textbotlang['users']['status']['disabled'] ?? 'غیرفعال'],
        'expired' => ['tag-no', $textbotlang['users']['status']['expired'] ?? 'منقضی شده'],
        'on_hold' => ['tag-plain', $textbotlang['users']['status']['on_hold'] ?? 'در انتظار'],
        'Unknown' => ['tag-plain', $textbotlang['users']['status']['unknown'] ?? 'نامشخص'],
        'deactivev' => ['tag-no', $textbotlang['users']['status']['disabled'] ?? 'غیرفعال'],
    ];

    $panelStatus = $DataUserOut['status'] ?? 'Unknown';
    $statusText = $statusMap[$panelStatus][1] ?? $statusMap['Unknown'][1];
    $statusClass = $statusMap[$panelStatus][0] ?? $statusMap['Unknown'][0];

    // Dates & JDF
    require_once __DIR__ . '/../../jdf.php';
    
    // Initial Purchase Date
    $purchaseDate = !empty($invoice['time_sell']) ? jdate('Y/m/d H:i', $invoice['time_sell']) : 'نامشخص';
    
    // Expiration date
    $expirationDate = !empty($DataUserOut['expire']) ? jdate('Y/m/d', $DataUserOut['expire']) : 'نامحدود';
    $timeDiff = !empty($DataUserOut['expire']) ? $DataUserOut['expire'] - time() : 0;
    $daysRemaining = $timeDiff > 0 ? floor($timeDiff / 86400) . ' روز' : 'منقضی / نامحدود';

    // Last Online
    $lastonline = 'نامشخص';
    if (isset($DataUserOut['online_at'])) {
        if ($DataUserOut['online_at'] == "online") {
            $lastonline = 'متصل 🟢';
        } elseif ($DataUserOut['online_at'] == "offline") {
            $lastonline = 'آفلاین 🔴';
        } elseif ($DataUserOut['online_at'] !== null) {
            $lastonline = jdate('Y/m/d H:i', strtotime($DataUserOut['online_at']));
        }
    }

    // Sub link updates
    $lastupdate = 'بروزرسانی نشده';
    if (!empty($DataUserOut['sub_updated_at'])) {
        $dateTime = new DateTime($DataUserOut['sub_updated_at'], new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }

    // Traffic Formatting
    function format_bytes_fa($bytes) {
        if ($bytes <= 0) return '۰ مگابایت';
        $mb = $bytes / pow(1024, 2);
        if ($mb < 1024) {
            return round($mb, 1) . ' مگابایت';
        } else {
            $gb = $bytes / pow(1024, 3);
            return round($gb, 2) . ' گیگابایت';
        }
    }

    $limitValue = (float)($DataUserOut['data_limit'] ?? 0);
    $usedTrafficValue = (float)($DataUserOut['used_traffic'] ?? 0);
    $remainingBytes = $limitValue - $usedTrafficValue;

    $LastTraffic = $limitValue > 0 ? format_bytes_fa($limitValue) : 'نامحدود';
    $RemainingVolume = $limitValue > 0 ? format_bytes_fa($remainingBytes) : 'نامحدود';
    $usedTrafficGb = $usedTrafficValue > 0 ? format_bytes_fa($usedTrafficValue) : 'مصرف نشده';

    // Percentage Calculation
    $remainingPercent = 100;
    if ($limitValue > 0) {
        $remainingPercent = (($limitValue - $usedTrafficValue) * 100) / $limitValue;
        if ($remainingPercent < 0) $remainingPercent = 0;
        $remainingPercent = round($remainingPercent, 2);
    }
    
    // Consumed percentage for progress bar
    $usedPercent = 100 - $remainingPercent;
    if ($usedPercent < 0) $usedPercent = 0;
    if ($usedPercent > 100) $usedPercent = 100;

    $subUrl = $DataUserOut['subscription_url'] ?? '';
    $configLinks = $DataUserOut['links'] ?? [];

?>
<style>
    .service-details {
        direction: rtl;
        text-align: right;
        font-family: inherit;
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: var(--text);
    }
    .bento-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    @media (max-width: 480px) {
        .bento-grid {
            grid-template-columns: 1fr;
        }
    }
    .bento-card {
        background: var(--bg-card-alt);
        border: 1px solid rgba(255,255,255,0.04);
        border-radius: 12px;
        padding: 14px 18px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.2s ease;
    }
    .bento-card:hover {
        border-color: rgba(255,255,255,0.08);
        transform: translateY(-2px);
    }
    .bento-full {
        grid-column: 1 / -1;
    }
    .card-title {
        font-size: 0.82em;
        color: var(--mute);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
    }
    .card-title svg {
        color: var(--ac);
        opacity: 0.85;
    }
    .card-value {
        font-size: 1.1em;
        font-weight: 600;
        word-break: break-all;
    }
    .card-desc {
        font-size: 0.78em;
        opacity: 0.6;
        margin-top: 6px;
    }
    .config-box {
        background: rgba(0,0,0,0.22);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 8px;
        padding: 10px 14px;
        font-family: monospace;
        font-size: 0.85em;
        word-break: break-all;
        max-height: 90px;
        overflow-y: auto;
        color: #a0aec0;
        margin-top: 6px;
        direction: ltr;
        text-align: left;
    }
    .btn-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-top: 8px;
    }
    .btn-grid-full {
        grid-column: span 2;
    }
    .btn-sm-action {
        padding: 11px 16px;
        font-size: 0.85em;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: opacity 0.2s, background-color 0.2s;
        border: none;
    }
    .btn-sm-action:hover {
        opacity: 0.95;
    }
    .progress-bar-container {
        background: rgba(255,255,255,0.06);
        height: 6px;
        border-radius: 3px;
        overflow: hidden;
        margin-top: 8px;
    }
    .progress-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }
    /* Collapsible Details Styles */
    details.configs-details {
        margin-top: 10px;
        width: 100%;
    }
    details.configs-details summary {
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 11px 15px;
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 10px;
        font-size: 0.9em;
        font-weight: 500;
        outline: none;
        user-select: none;
        list-style: none;
        transition: background-color 0.2s;
    }
    details.configs-details summary::-webkit-details-marker {
        display: none;
    }
    details.configs-details summary:hover {
        background: rgba(255,255,255,0.04);
    }
    details.configs-details .chevron {
        transition: transform 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    /* default RTL state: arrow-left (pointing left) */
    details.configs-details[open] .chevron {
        transform: rotate(-90deg); /* point down */
    }
</style>

<div class="service-details">
    
    <div class="bento-grid">
        <!-- Status & Location Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">
                    <?= icon('user', 15) ?>
                    <span>وضعیت و مشخصات عمومی</span>
                </div>
                <div class="card-value" style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                    <span class="tag <?= $statusClass ?>"><?= $statusText ?></span>
                    <span style="font-size: 0.85em; opacity: 0.9; font-weight: 500;"><?= htmlspecialchars($invoice['Service_location']) ?></span>
                </div>
            </div>
            <div class="card-desc">سفارش: <?= htmlspecialchars($invoice['id_invoice']) ?></div>
        </div>

        <!-- Purchase Info Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">
                    <?= icon('invoice', 15) ?>
                    <span>جزئیات خرید فاکتور</span>
                </div>
                <div class="card-value" style="font-size: 0.95em;">
                    <?= htmlspecialchars($invoice['name_product']) ?>
                </div>
            </div>
            <div class="card-desc">مبلغ پرداختی: <?= number_format((int)$invoice['price_product']) ?> تومان</div>
        </div>

        <!-- Volume Usage Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">
                    <?= icon('chart', 15) ?>
                    <span>حجم مصرفی و باقی‌مانده</span>
                </div>
                <div class="card-value" style="color: var(--text);"><?= $RemainingVolume ?></div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: <?= $usedPercent ?>%; background: <?= $usedPercent > 80 ? 'var(--no)' : ($usedPercent > 50 ? 'var(--warn)' : 'var(--ok)') ?>;"></div>
                </div>
            </div>
            <div class="card-desc">مصرف شده: <?= $usedTrafficGb ?> از <?= $LastTraffic ?> (<?= round($usedPercent, 1) ?>٪)</div>
        </div>

        <!-- Expiration Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">
                    <?= icon('clock', 15) ?>
                    <span>اعتبار زمانی سرویس</span>
                </div>
                <div class="card-value"><?= $expirationDate ?></div>
            </div>
            <div class="card-desc">زمان باقی‌مانده: <?= $daysRemaining ?> (تاریخ خرید: <?= $purchaseDate ?>)</div>
        </div>

        <!-- Connection Details Card -->
        <div class="bento-card bento-full">
            <div class="card-title">
                <?= icon('activity', 15) ?>
                <span>جزئیات اتصال و به‌روزرسانی</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 0.85em; margin-top: 4px;">
                <div>
                    <span style="color: var(--mute);">آخرین اتصال:</span>
                    <div style="font-weight: 500; margin-top: 2px;"><?= $lastonline ?></div>
                </div>
                <div>
                    <span style="color: var(--mute);">بروزرسانی لینک:</span>
                    <div style="font-weight: 500; margin-top: 2px;"><?= $lastupdate ?></div>
                </div>
                <div>
                    <span style="color: var(--mute);">سیستم‌عامل/کلاینت:</span>
                    <div style="font-weight: 500; margin-top: 2px; word-break: break-all;" title="<?= htmlspecialchars($DataUserOut['sub_last_user_agent'] ?? '') ?>">
                        <?= htmlspecialchars(trunc($DataUserOut['sub_last_user_agent'] ?? 'یافت نشد', 22)) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscription & Configs Card -->
        <div class="bento-card bento-full">
            <div class="card-title">
                <?= icon('link', 15) ?>
                <span>اشتراک و کانفیگ‌ها</span>
            </div>
            
            <?php if ($subUrl): ?>
            <div style="display:flex; flex-direction:column; gap:4px; background: rgba(255,255,255,0.02); padding: 10px 14px; border-radius: 8px; margin-bottom: 6px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.85em; color:var(--ac); cursor:pointer;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>alert('کپی شد!'))">
                        لینک اشتراک: برای کپی کردن کلیک کنید
                    </span>
                    <button class="btn btn-ghost btn-sm" style="padding: 2px 6px; display:flex; align-items:center; gap:4px;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>alert('کپی شد!'))">
                        <?= icon('copy', 13) ?> کپی
                    </button>
                </div>
                <!-- Subscription QR Code -->
                <details style="margin-top: 4px;">
                    <summary style="font-size: 0.8em; color: var(--ac); cursor: pointer; list-style: none; outline: none; display: inline-flex; align-items: center; gap: 4px;">
                        📷 نمایش QR کد لینک اشتراک
                    </summary>
                    <div style="text-align: center; padding: 10px; background: #fff; border-radius: 8px; margin-top: 6px; width: fit-content; margin-left: auto; margin-right: auto;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode(trim($subUrl)) ?>" alt="QR Code" style="display: block; width: 150px; height: 150px;" />
                    </div>
                </details>
            </div>
            <?php endif; ?>

            <?php if (!empty($configLinks)): ?>
            <details class="configs-details">
                <summary>
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <?= icon('sliders', 14) ?>
                        کانفیگ‌های فعال سرویس (<?= count($configLinks) ?> عدد)
                    </span>
                    <span class="chevron"><?= icon('arrow-left', 14) ?></span>
                </summary>
                <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 14px; padding: 4px;">
                    <?php foreach ($configLinks as $index => $link): if(empty(trim($link))) continue; 
                        $linkClean = trim($link);
                        $isWireguard = (stripos($linkClean, '[Interface]') !== false || stripos($linkClean, 'PrivateKey') !== false);
                    ?>
                        <div style="display:flex; flex-direction:column; gap:4px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.04);">
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size: 0.85em; color: var(--mute);">
                                <span style="font-weight: 500;">کانفیگ #<?= ($index + 1) ?> <?= $isWireguard ? '(وایرگارد)' : '' ?></span>
                                <div style="display:flex; gap:10px;">
                                    <?php if ($isWireguard): ?>
                                    <a href="data:text/plain;charset=utf-8,<?= rawurlencode($linkClean) ?>" download="<?= htmlspecialchars($invoice['username']) ?>_wg_<?= ($index + 1) ?>.conf" style="color:var(--ac); cursor:pointer; font-size: 0.92em; display:inline-flex; align-items:center; gap:4px;">
                                        💾 دانلود فایل conf.
                                    </a>
                                    <?php endif; ?>
                                    <span style="color:var(--ac); cursor:pointer; font-size: 0.92em; display:inline-flex; align-items:center; gap:4px;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($linkClean) ?>').then(()=>alert('کپی شد!'))">
                                        <?= icon('copy', 12) ?> کپی کانفیگ
                                    </span>
                                </div>
                            </div>
                            <div class="config-box"><?= htmlspecialchars($linkClean) ?></div>
                            <!-- Config QR Code -->
                            <details style="margin-top: 6px;">
                                <summary style="font-size: 0.8em; color: var(--ac); cursor: pointer; list-style: none; outline: none; display: inline-flex; align-items: center; gap: 4px;">
                                    📷 نمایش QR کد کانفیگ
                                </summary>
                                <div style="text-align: center; padding: 10px; background: #fff; border-radius: 8px; margin-top: 6px; width: fit-content; margin-left: auto; margin-right: auto;">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($linkClean) ?>" alt="QR Code" style="display: block; width: 150px; height: 150px;" />
                                </div>
                            </details>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php else: ?>
            <div style="font-size: 0.8em; color: var(--mute); text-align: center; margin-top: 8px;">هیچ کانفیگی یافت نشد.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- AJAX & Action Buttons -->
    <div class="btn-grid">
        <?php $csrf = csrf_token(); ?>

        <!-- Refresh (AJAX) -->
        <button type="button" class="btn-sm-action btn-grid-full" style="background: rgba(255,255,255,0.06); color: var(--text); border: 1px solid rgba(255,255,255,0.08);" onclick="manageService('<?= $id_invoice ?>')">
            <?= icon('refresh-cw', 13) ?> بروزرسانی اطلاعات لحظه‌ای
        </button>

        <!-- Toggle Status -->
        <?php if ($panelStatus == "active"): ?>
        <a href="user_action.php?action=toggle_status&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:var(--no); color:#fff; text-decoration:none;" 
           data-confirm="آیا از غیرفعال کردن (خاموش کردن) این کانفیگ مطمئن هستید؟" hx-boost="false">
           <?= icon('block', 13) ?> خاموش کردن اکانت
        </a>
        <?php else: ?>
        <a href="user_action.php?action=toggle_status&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:var(--ok); color:#fff; text-decoration:none;" 
           data-confirm="آیا از فعال کردن (روشن کردن) این کانفیگ مطمئن هستید؟" hx-boost="false">
           <?= icon('check', 13) ?> روشن کردن اکانت
        </a>
        <?php endif; ?>

        <!-- Extend Service -->
        <a href="user_action.php?action=extendservice&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:var(--ac); color:#fff; text-decoration:none;" 
           data-confirm="آیا مایلید این سرویس را با حجم <?= $invoice['Volume'] ?> گیگابایت و زمان <?= $invoice['Service_time'] ?> روز تمدید رایگان کنید؟" hx-boost="false">
           <?= icon('plus', 13) ?> تمدید سرویس
        </a>

        <!-- Remove Service (No Refund) -->
        <a href="user_action.php?action=removeservice&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:rgba(244, 63, 94, 0.15); color:var(--no); border: 1px solid var(--no); text-decoration:none;" 
           data-confirm="آیا از حذف کامل این سرویس مطمئن هستید؟ این کار غیرقابل بازگشت است و وجهی بازگردانده نمی‌شود." hx-boost="false">
           <?= icon('trash', 13) ?> حذف کامل سرویس
        </a>
        
        <!-- Remove Service (Refund) -->
        <a href="user_action.php?action=removeserviceandrefund&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:rgba(245, 158, 11, 0.15); color:var(--warn); border: 1px solid var(--warn); text-decoration:none;" 
           data-confirm="آیا مطمئن هستید؟ کاربر حذف شده و مبلغ <?= number_format((int)($invoice['price_product'] ?? 0)) ?> تومان به کیف پول او برگشت داده می‌شود." hx-boost="false">
           <?= icon('refresh-cw', 13) ?> حذف سفارش و بازگشت وجه
        </a>
    </div>

</div>

<?php
} catch (Exception $e) {
    http_response_code(500);
    echo '<div style="padding:20px;text-align:center;color:var(--no); direction: rtl;">خطای سرور: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
