<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$pageTitle = $textbotlang['panel']['servicesTitle'];
$pageLede = $textbotlang['panel']['servicesSubtitle'];
$activeNav = 'service_other';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($search !== '') {
  $where[] = "(id_user LIKE ? OR COALESCE(username,'') LIKE ? OR COALESCE(type,'') LIKE ?)";
  $params = ["%$search%", "%$search%", "%$search%"];
}
if ($status !== '') {
  $where[] = "status = ?";
  $params[] = $status;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
  $total = db_count($pdo, "SELECT COUNT(*) FROM service_other $whereSQL", $params);
  $services = db_fetchAll($pdo, "SELECT * FROM service_other $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
  $total = 0;
  $services = [];
  error_log('service.php error: ' . $e->getMessage());
}
$totalPages = max(1, (int) ceil($total / $perPage));

// Calculate Global Stats
try {
  $globalTotal = db_count($pdo, "SELECT COUNT(*) FROM service_other");
  $globalPending = db_count($pdo, "SELECT COUNT(*) FROM service_other WHERE status = 'pending'");
  $globalDone = db_count($pdo, "SELECT COUNT(*) FROM service_other WHERE status = 'done'");
  $globalReject = db_count($pdo, "SELECT COUNT(*) FROM service_other WHERE status = 'reject'");
} catch (Exception $e) {
  $globalTotal = 0; $globalPending = 0; $globalDone = 0; $globalReject = 0;
}

$typeMap = [
  'change_location' => $textbotlang['panel']['serviceChangeLocationLabel'],
  'extra_user' => $textbotlang['panel']['serviceExtraVolumeLabel'],
  'extra_time_user' => $textbotlang['panel']['serviceExtraTimeLabel'],
  'extends_not_user' => $textbotlang['panel']['serviceRenewLabel'],
  'extend_user' => $textbotlang['panel']['serviceRenewLabel2'],
  'transfertouser' => $textbotlang['panel']['serviceTransferOrderLabel']
];

$pageTitle = $textbotlang['panel']['servicesHeading'];
$pageLede = $textbotlang['panel']['servicesSubtitle2'];
$activeNav = 'service';
include __DIR__ . '/inc/layout_head.php';
?>

<!-- Top Statistics Cards -->
<div class="stats fade-up" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;">کل درخواست‌ها</div>
            <div class="icon-glow bg-blue">
                <?= icon('layers', 20) ?>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($globalTotal) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <span class="status-pill neutral">تراکنش دستی ثبت شده</span>
        </div>
    </div>
    
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;">در انتظار انجام</div>
            <div class="icon-glow bg-amber">
                <?= icon('clock', 20) ?>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($globalPending) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <span class="status-pill warning">نیازمند بررسی</span>
        </div>
    </div>
    
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;">انجام شده</div>
            <div class="icon-glow bg-emerald">
                <?= icon('check-circle', 20) ?>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($globalDone) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <span class="status-pill success">موفق</span>
        </div>
    </div>
    
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;">رد شده</div>
            <div class="icon-glow bg-red">
                <?= icon('x-circle', 20) ?>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($globalReject) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <span class="status-pill danger">لغو شده</span>
        </div>
    </div>

</div>

<div class="card fade-up">
  <div class="toolbar">
    <div class="toolbar-title"><?= $textbotlang['panel']['servicesPageHeading'] ?> <small>(<?= number_format($total) ?>)</small></div>
    <form method="GET" id="srvForm" class="toolbar-end">
      <select name="status" class="select" style="width:auto" onchange="document.getElementById('srvForm').submit()">
        <option value="">همه وضعیت‌ها</option>
        <option value="done" <?= $status === 'done' ? 'selected' : '' ?>>انجام شده</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>در انتظار</option>
        <option value="reject" <?= $status === 'reject' ? 'selected' : '' ?>>رد شده</option>
      </select>
      <div class="search-box" style="min-width:240px">
        <?= icon('search', 14) ?>
        <input type="text" name="q" placeholder="<?= htmlspecialchars($textbotlang['panel']['serviceSearchServicePlaceholder'] ?? 'جستجو') ?>" value="<?= htmlspecialchars($search) ?>"
          autocomplete="off">
        <button type="button" class="search-clear">✕</button>
        <button type="submit" class="search-btn">جستجو</button>
      </div>
      <?php if ($search || $status): ?>
        <a href="service.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['serviceColPanel'] ?></a>
      <?php endif; ?>
    </form>
  </div>

  <div class="tbl-wrap">
    <table class="tbl-lg">
      <thead>
        <tr>
          <th>#</th>
          <th><?= $textbotlang['panel']['serviceDetailUser'] ?? 'کاربر' ?></th>
          <th><?= $textbotlang['panel']['userColUsername'] ?? 'یوزرنیم' ?></th>
          <th><?= $textbotlang['panel']['serviceColType'] ?? 'نوع' ?></th>
          <th><?= $textbotlang['panel']['serviceColAmount'] ?? 'مقدار' ?></th>
          <th><?= $textbotlang['panel']['serviceColPrice'] ?? 'قیمت' ?></th>
          <th><?= $textbotlang['panel']['serviceColDate'] ?? 'تاریخ' ?></th>
          <th><?= $textbotlang['panel']['serviceColStatus'] ?? 'وضعیت' ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($services)): ?>
          <tr>
            <td colspan="8">
              <div class="empty">
                <svg class="ill" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="30" y="30" width="120" height="80" rx="10" fill="var(--sf3)" />
                  <rect x="50" y="50" width="40" height="40" rx="6" fill="var(--bds)" />
                  <rect x="100" y="55" width="35" height="8" rx="4" fill="var(--bd)" />
                  <rect x="100" y="70" width="25" height="8" rx="4" fill="var(--bd)" />
                  <rect x="100" y="85" width="30" height="8" rx="4" fill="var(--bd)" />
                  <path d="M60 65 l10 10 l20-20" stroke="var(--ac)" stroke-width="3" stroke-linecap="round" fill="none" />
                </svg>
                <p><?= $search ? $textbotlang['panel']['serviceNoServiceFound'] : $textbotlang['panel']['serviceNoManualServiceYet'] ?></p>
              </div>
            </td>
          </tr>
        <?php else:
          $i = $offset + 1;
          foreach ($services as $s):
            $stMap = [
              'done' => ['tag-ok', $textbotlang['panel']['serviceStatusDone']],
              'pending' => ['tag-warn', $textbotlang['panel']['serviceStatusWaiting']],
              'reject' => ['tag-no', $textbotlang['panel']['serviceStatusRejected']],
            ];
            [$cls, $lbl] = $stMap[$s['status'] ?? ''] ?? ['tag-plain', $s['status'] ?? '—'];
            $typeLabel = $typeMap[$s['type'] ?? ''] ?? ($s['type'] ?? '—');
            
            $rawVal = $s['value'] ?? '';
            $valStr = $rawVal;
            if (str_starts_with(trim($rawVal), '{')) {
                $decoded = json_decode($rawVal, true);
                if (is_array($decoded)) {
                    $parts = [];
                    if (isset($decoded['volumebuy'])) $parts[] = $decoded['volumebuy'] . ' گیگ';
                    if (isset($decoded['time'])) $parts[] = $decoded['time'] . ' روز';
                    if (isset($decoded['server_id'])) $parts[] = 'سرور ' . $decoded['server_id'];
                    if (isset($decoded['plan_id'])) $parts[] = 'پلن ' . $decoded['plan_id'];
                    if (isset($decoded['server_name'])) $parts[] = $decoded['server_name'];
                    if (empty($parts)) {
                        foreach ($decoded as $k => $v) {
                            if (is_scalar($v)) $parts[] = "$k: $v";
                        }
                    }
                    $valStr = implode(' - ', $parts);
                }
            }
            if ($valStr === '' || $valStr === '[]' || $valStr === '{}') $valStr = '—';
            ?>
            <tr>
              <td class="cf"><?= $i++ ?></td>
              <td class="cm"><?= htmlspecialchars($s['id_user'] ?? '—') ?></td>
              <td>
                <?= !empty($s['username']) ? '<span class="cm" style="color:var(--ac)">@' . htmlspecialchars(trunc($s['username'], 18)) . '</span>' : '<span class="cf">—</span>' ?>
              </td>
              <td style="font-size:.82rem;color:var(--text2)"><?= htmlspecialchars($typeLabel) ?></td>
              <td class="cn" style="font-size:.82rem; direction:ltr; text-align:right"><?= htmlspecialchars(trunc($valStr, 40)) ?></td>
              <td class="cn cs"><?= number_format((int) ($s['price'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['dashUnitToman'] ?? 'تومان' ?></span></td>
              <td class="cf"><?= safe_date($s['time'] ?? null, 'Y/m/d') ?></td>
              <td><span class="tag <?= $cls ?>"><?= $lbl ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="tbl-foot">
    <span><?= number_format($total) ?> <?= $textbotlang['panel']['serviceDetailPanel'] ?> <?= $page ?> <?= $textbotlang['panel']['serviceCloseBtn'] ?> <?= $totalPages ?></span>
    <div class="pager">
      <?php $qs = fn($p) => '?q=' . urlencode($search) . '&status=' . urlencode($status) . '&page=' . $p; ?>
      <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
      <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>