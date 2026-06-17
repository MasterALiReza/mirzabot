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
                <p style="font-size:0.85em; opacity:0.8;">جزئیات: ' . $err_msg . '</p>
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

    // Dates
    require_once __DIR__ . '/../../jdf.php';
    $expirationDate = !empty($DataUserOut['expire']) ? jdate('Y/m/d H:i', $DataUserOut['expire']) : 'نامحدود';
    
    // Traffic
    function format_bytes_fa($bytes) {
        if ($bytes <= 0) return '۰ مگ';
        $mb = $bytes / pow(1024, 2);
        if ($mb < 1024) {
            return round($mb, 1) . ' مگ';
        } else {
            $gb = $bytes / pow(1024, 3);
            return round($gb, 2) . ' گیگ';
        }
    }

    $limitValue = (float)($DataUserOut['data_limit'] ?? 0);
    $usedTrafficValue = (float)($DataUserOut['used_traffic'] ?? 0);
    $output = $limitValue - $usedTrafficValue;

    $LastTraffic = $limitValue > 0 ? format_bytes_fa($limitValue) : 'نامحدود';
    $RemainingVolume = $limitValue > 0 ? format_bytes_fa($output) : 'نامحدود';
    $usedTrafficGb = $usedTrafficValue > 0 ? format_bytes_fa($usedTrafficValue) : 'مصرف نشده';

    $Percent = 100;
    if ($limitValue > 0) {
        $Percent = (($limitValue - $usedTrafficValue) * 100) / $limitValue;
        if ($Percent < 0) $Percent = 0;
        $Percent = round($Percent, 2);
    }

    $subUrl = $DataUserOut['subscription_url'] ?? '';

?>
<div style="display:flex; flex-direction:column; gap:16px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card-alt); padding:12px; border-radius:12px;">
        <span style="color:var(--mute); font-size:0.9em;">وضعیت سرویس</span>
        <span class="tag <?= $statusClass ?>"><?= $statusText ?></span>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div style="background:var(--bg-card-alt); padding:12px; border-radius:12px;">
            <div style="color:var(--mute); font-size:0.85em; margin-bottom:4px;">حجم باقی‌مانده</div>
            <div style="font-weight:600; color:var(--text); font-size:1.1em;"><?= $RemainingVolume ?></div>
            <div style="font-size:0.75em; opacity:0.6; margin-top:2px;">از <?= $LastTraffic ?></div>
        </div>
        <div style="background:var(--bg-card-alt); padding:12px; border-radius:12px;">
            <div style="color:var(--mute); font-size:0.85em; margin-bottom:4px;">حجم مصرفی</div>
            <div style="font-weight:600; color:var(--text); font-size:1.1em;"><?= $usedTrafficGb ?></div>
            <div style="font-size:0.75em; opacity:0.6; margin-top:2px;"><?= (100 - $Percent) ?>٪ مصرف شده</div>
        </div>
    </div>

    <div style="background:var(--bg-card-alt); padding:12px; border-radius:12px;">
        <div style="color:var(--mute); font-size:0.85em; margin-bottom:4px;">تاریخ انقضا</div>
        <div style="font-weight:600; color:var(--text); font-size:1.1em;"><?= $expirationDate ?></div>
    </div>

    <?php if ($subUrl): ?>
    <div style="background:var(--bg-card-alt); padding:12px; border-radius:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="color:var(--mute); font-size:0.85em; margin-bottom:4px;">لینک اشتراک</div>
            <div style="font-size:0.85em; color:var(--ac); cursor:pointer;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>alert('کپی شد!'))">
                برای کپی کردن کلیک کنید
            </div>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>alert('کپی شد!'))">
            📋
        </button>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div style="margin-top:16px; display:flex; flex-direction:column; gap:8px;">
        <?php $csrf = csrf_token(); ?>
        
        <a href="user_action.php?action=removeservice&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn" style="background:var(--red); color:#fff; border:none; justify-content:center; width:100%;" 
           data-confirm="آیا از حذف این سرویس مطمئن هستید؟ این کار غیرقابل بازگشت است و وجهی بازگردانده نمی‌شود." hx-boost="false">
           🗑 حذف اکانت (بدون بازگشت وجه)
        </a>
        
        <a href="user_action.php?action=removeserviceandrefund&id=<?= $id_user ?>&id_invoice=<?= urlencode($id_invoice) ?>&_csrf=<?= $csrf ?>&back=user.php" 
           class="btn" style="background:var(--orange); color:#fff; border:none; justify-content:center; width:100%;" 
           data-confirm="آیا مطمئن هستید؟ کاربر حذف شده و مبلغ <?= number_format((int)($invoice['price_product'] ?? 0)) ?> تومان به کیف پول او برگشت داده می‌شود." hx-boost="false">
           🔄 حذف اکانت و بازگشت وجه
        </a>
    </div>

</div>

<?php
} catch (Exception $e) {
    http_response_code(500);
    echo '<div style="padding:20px;text-align:center;color:var(--red);">خطای سرور: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
