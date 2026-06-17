<?php
// Buffer all output
ob_start();
session_start();
require '../inc/config.php';
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
    echo '<div style="padding:20px;text-align:center;color:var(--red);">درخواست نامعتبر است.</div>';
    exit;
}

try {
    $invoice = db_fetch($pdo, "SELECT * FROM invoice WHERE id_invoice = ? AND id_user = ?", [$id_invoice, $id_user]);
    if (!$invoice) {
        http_response_code(404);
        echo '<div style="padding:20px;text-align:center;color:var(--red);">سفارش مورد نظر یافت نشد.</div>';
        exit;
    }

    $ManagePanel = new ManagePanel();
    $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);

    if (isset($DataUserOut['status']) && $DataUserOut['status'] == "Unsuccessful") {
        $err_msg = !empty($DataUserOut['msg']) ? htmlspecialchars(is_string($DataUserOut['msg']) ? $DataUserOut['msg'] : json_encode($DataUserOut['msg'])) : "نامشخص";
        echo '<div style="padding:20px;text-align:center;color:var(--red);">
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

    $Percent = 100;
    if ($limitValue > 0) {
        $Percent = (($limitValue - $usedTrafficValue) * 100) / $limitValue;
        if ($Percent < 0) $Percent = 0;
        $Percent = round($Percent, 2);
    }

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
        padding: 12px 16px;
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
        font-size: 0.8em;
        color: var(--mute);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .card-value {
        font-size: 1.05em;
        font-weight: 600;
        word-break: break-all;
    }
    .card-desc {
        font-size: 0.75em;
        opacity: 0.6;
        margin-top: 4px;
    }
    .config-box {
        background: rgba(0,0,0,0.15);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 8px;
        padding: 8px 12px;
        font-family: monospace;
        font-size: 0.85em;
        word-break: break-all;
        max-height: 80px;
        overflow-y: auto;
        color: var(--mute);
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
        padding: 10px 14px;
        font-size: 0.85em;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .btn-sm-action:hover {
        opacity: 0.9;
    }
    .progress-bar-container {
        background: rgba(255,255,255,0.05);
        height: 6px;
        border-radius: 3px;
        overflow: hidden;
        margin-top: 6px;
    }
    .progress-bar-fill {
        height: 100%;
        background: var(--ac);
        border-radius: 3px;
    }
</style>

<div class="service-details">
    
    <div class="bento-grid">
        <!-- Status & Location Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">📌 وضعیت و مشخصات عمومی</div>
                <div class="card-value" style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                    <span class="tag <?= $statusClass ?>"><?= $statusText ?></span>
                    <span style="font-size: 0.85em; opacity: 0.8;"><?= htmlspecialchars($invoice['Service_location']) ?></span>
                </div>
            </div>
            <div class="card-desc">سفارش: <?= htmlspecialchars($invoice['id_invoice']) ?></div>
        </div>

        <!-- Purchase Info Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">💰 جزئیات خرید فاکتور</div>
                <div class="card-value" style="font-size: 0.95em;">
                    <?= htmlspecialchars($invoice['name_product']) ?>
                </div>
            </div>
            <div class="card-desc">مبلغ پرداختی: <?= number_format((int)$invoice['price_product']) ?> تومان</div>
        </div>

        <!-- Volume Usage Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">📊 حجم مصرفی و باقی‌مانده</div>
                <div class="card-value" style="color: var(--text);"><?= $RemainingVolume ?></div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: <?= $Percent ?>%; background: <?= $Percent < 20 ? 'var(--red)' : ($Percent < 50 ? 'var(--orange)' : 'var(--green)') ?>;"></div>
                </div>
            </div>
            <div class="card-desc">مصرف شده: <?= $usedTrafficGb ?> از <?= $LastTraffic ?> (<?= (100 - $Percent) ?>٪)</div>
        </div>

        <!-- Expiration Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">⏰ اعتبار زمانی سرویس</div>
                <div class="card-value"><?= $expirationDate ?></div>
            </div>
            <div class="card-desc">زمان باقی‌مانده: <?= $daysRemaining ?> (تاریخ خرید: <?= $purchaseDate ?>)</div>
        </div>

        <!-- Connection Details Card -->
        <div class="bento-card bento-full">
            <div class="card-title">🔌 جزئیات اتصال و به‌روزرسانی</div>
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
                        <?= htmlspecialchars(trunc($DataUserOut['sub_last_user_agent'] ?? 'یافت نشد', 20)) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscription & Configs Card -->
        <div class="bento-card bento-full">
            <div class="card-title">🔗 اشتراک و کانفیگ‌ها</div>
            
            <?php if ($subUrl): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; background: rgba(255,255,255,0.02); padding: 8px 12px; border-radius: 8px; margin-bottom: 8px;">
                <span style="font-size:0.85em; color:var(--ac); cursor:pointer;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>alert('کپی شد!'))">
                    لینک اشتراک: برای کپی کردن کلیک کنید
                </span>
                <button class="btn btn-ghost btn-sm" style="padding: 2px 6px;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>alert('کپی شد!'))">
                    📋 کپی
                </button>
            </div>
            <?php endif; ?>

            <?php if (!empty($configLinks)): ?>
            <div style="margin-top: 8px;">
                <span style="font-size: 0.8em; color: var(--mute);">کانفیگ‌های فعال سرویس:</span>
                <?php foreach ($configLinks as $index => $link): if(empty(trim($link))) continue; ?>
                    <div style="margin-top: 6px; display:flex; flex-direction:column; gap:4px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size: 0.8em; color: var(--mute);">
                            <span>کانفیگ #<?= ($index + 1) ?></span>
                            <span style="color:var(--ac); cursor:pointer; font-size: 0.95em;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars(trim($link)) ?>').then(()=>alert('کپی شد!'))">📋 کپی کانفیگ</span>
                        </div>
                        <div class="config-box"><?= htmlspecialchars(trim($link)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
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
            🔄 بروزرسانی اطلاعات لحظه‌ای
        </button>

        <!-- Toggle Status -->
        <?php if ($panelStatus == "active"): ?>
        <a href="user_action.php?action=toggle_status&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:var(--red); color:#fff; border:none; text-decoration:none;" 
           data-confirm="آیا از غیرفعال کردن (خاموش کردن) این کانفیگ مطمئن هستید؟" hx-boost="false">
           ❌ خاموش کردن اکانت
        </a>
        <?php else: ?>
        <a href="user_action.php?action=toggle_status&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:var(--green); color:#fff; border:none; text-decoration:none;" 
           data-confirm="آیا از فعال کردن (روشن کردن) این کانفیگ مطمئن هستید؟" hx-boost="false">
           🟢 روشن کردن اکانت
        </a>
        <?php endif; ?>

        <!-- Extend Service -->
        <a href="user_action.php?action=extendservice&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:var(--blue); color:#fff; border:none; text-decoration:none;" 
           data-confirm="آیا مایلید این سرویس را با حجم <?= $invoice['Volume'] ?> گیگابایت و زمان <?= $invoice['Service_time'] ?> روز تمدید رایگان کنید؟" hx-boost="false">
           💊 تمدید سرویس
        </a>

        <!-- Remove Service (No Refund) -->
        <a href="user_action.php?action=removeservice&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:rgba(239, 68, 68, 0.15); color:var(--red); border: 1px solid var(--red); text-decoration:none;" 
           data-confirm="آیا از حذف کامل این سرویس مطمئن هستید؟ این کار غیرقابل بازگشت است و وجهی بازگردانده نمی‌شود." hx-boost="false">
           🗑 حذف کامل سرویس
        </a>
        
        <!-- Remove Service (Refund) -->
        <a href="user_action.php?action=removeserviceandrefund&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn-sm-action" style="background:rgba(245, 158, 11, 0.15); color:var(--orange); border: 1px solid var(--orange); text-decoration:none;" 
           data-confirm="آیا مطمئن هستید؟ کاربر حذف شده و مبلغ <?= number_format((int)($invoice['price_product'] ?? 0)) ?> تومان به کیف پول او برگشت داده می‌شود." hx-boost="false">
           🔄 حذف سفارش و بازگشت وجه
        </a>
    </div>

</div>

<?php
} catch (Exception $e) {
    http_response_code(500);
    echo '<div style="padding:20px;text-align:center;color:var(--red); direction: rtl;">خطای سرور: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
