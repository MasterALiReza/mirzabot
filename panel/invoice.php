<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$search = trim($_GET['q'] ?? '');

$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($search !== '') {
  $where[] = "(id_user LIKE ? OR COALESCE(name_product,'') LIKE ? OR COALESCE(username,'') LIKE ?)";
  $params = ["%$search%", "%$search%", "%$search%"];
}
if ($status !== '') {

  $where[] = "Status = ?";
  $params[] = $status;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
  $total = db_count($pdo, "SELECT COUNT(*) FROM invoice $whereSQL", $params);
  $invoices = db_fetchAll($pdo, "SELECT * FROM invoice $whereSQL ORDER BY time_sell DESC LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
  $total = 0;
  $invoices = [];
  flash('error', $textbotlang['panel']['invoiceDbError'] . $e->getMessage());
}
$totalPages = max(1, (int) ceil($total / $perPage));

// Calculate Global Stats
try {
  $globalTotal = db_count($pdo, "SELECT COUNT(*) FROM invoice");
  $globalRevenue = db_fetchAll($pdo, "SELECT SUM(price_product) as total FROM invoice")[0]['total'] ?? 0;
  $globalActive = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE Status = 'active'");
  $globalUnpaid = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE Status = 'unpaid'");
} catch (Exception $e) {
  $globalTotal = 0; $globalRevenue = 0; $globalActive = 0; $globalUnpaid = 0;
}

$statusMap = [
  'active' => ['tag-ok', $textbotlang['panel']['invoiceStatusActive']],
  'end_of_time' => ['tag-warn', $textbotlang['panel']['invoiceNotifTimeExpire']],
  'end_of_volume' => ['tag-no', $textbotlang['panel']['invoiceNotifVolumeExpire']],
  'sendedwarn' => ['tag-warn', $textbotlang['panel']['invoiceNotifAllSent']],
  'send_on_hold' => ['tag-plain', $textbotlang['panel']['invoiceNotifNotConnectedSent']],
  'unpaid' => ['tag-plain', $textbotlang['panel']['invoiceStatusUnpaid']],
  'Unsuccessful' => ['tag-plain', $textbotlang['panel']['invoiceDataFetchError']],
];

$pageTitle = $textbotlang['panel']['invoiceOrdersTitle'];
$pageLede = $textbotlang['panel']['invoiceOrdersSubtitle'];
$activeNav = 'invoice';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="stats fade-up" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px;">

    <!-- Stat 1: Total Orders -->
    <div class="dash-card" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
            <div class="icon-glow bg-blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
            </div>
            <div style="font-size: 1.05rem; color: var(--cf); font-weight: 600;">کل سفارشات</div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; margin-top: auto;">
            <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); line-height: 1;">
                <?= number_format($globalTotal) ?>
            </div>
            <div style="font-size: 0.85rem; font-weight: 500;">
                <span class="status-pill neutral" style="padding: 4px 10px;">سفارش ثبت شده</span>
            </div>
        </div>
    </div>
    
    <!-- Stat 2: Total Revenue -->
    <div class="dash-card" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
            <div class="icon-glow bg-emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <div style="font-size: 1.05rem; color: var(--cf); font-weight: 600;">مجموع درآمد</div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; margin-top: auto;">
            <div style="display: flex; align-items: baseline; gap: 6px;">
                <span style="font-size: 2.2rem; font-weight: 700; color: var(--ct); line-height: 1; direction: ltr;">
                    <?= $globalRevenue >= 1_000_000 ? number_format($globalRevenue / 1_000_000, 1) : number_format($globalRevenue) ?>
                </span>
                <span style="font-size: 1rem; font-weight: 600; color: var(--cf);">
                    <?= $globalRevenue >= 1_000_000 ? 'میلیون تومان' : 'تومان' ?>
                </span>
            </div>
            <div style="font-size: 0.85rem; font-weight: 500;">
                <span class="status-pill success" style="padding: 4px 10px;">فروش کل</span>
            </div>
        </div>
    </div>
    
    <!-- Stat 3: Active Services -->
    <div class="dash-card" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
            <div class="icon-glow bg-purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div style="font-size: 1.05rem; color: var(--cf); font-weight: 600;">سرویس‌های فعال</div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; margin-top: auto;">
            <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); line-height: 1;">
                <?= number_format($globalActive) ?>
            </div>
            <div style="font-size: 0.85rem; font-weight: 500;">
                <span class="status-pill neutral" style="padding: 4px 10px;">در حال استفاده</span>
            </div>
        </div>
    </div>
    
    <!-- Stat 4: Unpaid Orders -->
    <div class="dash-card" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 140px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
            <div class="icon-glow bg-amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <div style="font-size: 1.05rem; color: var(--cf); font-weight: 600;">پرداخت نشده</div>
        </div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; margin-top: auto;">
            <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); line-height: 1;">
                <?= number_format($globalUnpaid) ?>
            </div>
            <div style="font-size: 0.85rem; font-weight: 500;">
                <span class="status-pill warning" style="padding: 4px 10px;">رها شده</span>
            </div>
        </div>
    </div>

</div>

<div class="card fade-up">
  <div class="toolbar">
    <div class="toolbar-title"><?= $textbotlang['panel']['invoiceOrdersHeading'] ?> <small>(<?= number_format($total) ?>)</small></div>
    <form method="GET" id="invoiceForm" class="toolbar-end">
      <select name="status" class="select" style="width:auto"
        onchange="document.getElementById('invoiceForm').submit()">
        <option value=""><?= $textbotlang['panel']['invoiceAllStatuses'] ?></option>
        <?php foreach ($statusMap as $k => [$_, $lbl]): ?>
          <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <div class="search-box" style="min-width:240px">
        <?= icon('search', 14) ?>
        <input type="text" name="q" placeholder="<?= htmlspecialchars($textbotlang['panel']['invoiceSearchOrderPlaceholder'] ?? 'جستجو') ?>" value="<?= htmlspecialchars($search) ?>"
          autocomplete="off">
        <button type="button" class="search-clear">✕</button>
        <button type="submit" class="search-btn"><?= $textbotlang['panel']['invoiceSearchBtn'] ?></button>
      </div>
      <?php if ($search || $status): ?>
        <a href="invoice.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['invoiceClearBtn'] ?></a>
      <?php endif; ?>
    </form>
  </div>

  <div class="tbl-wrap">
    <table class="tbl-md">
      <thead>
        <tr>
          <th>#</th>
          <th><?= $textbotlang['panel']['invoiceColUser'] ?></th>
          <th><?= $textbotlang['panel']['invoiceColProduct'] ?></th>
          <th><?= $textbotlang['panel']['invoiceColPrice'] ?></th>
          <th><?= $textbotlang['panel']['invoiceColDate'] ?></th>
          <th><?= $textbotlang['panel']['invoiceColStatus'] ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($invoices)): ?>
          <tr>
            <td colspan="6">
              <div class="empty">
                <svg class="ill" viewBox="0 0 160 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="30" y="15" width="100" height="90" rx="8" fill="var(--sf3)" />
                  <rect x="45" y="35" width="70" height="8" rx="4" fill="var(--bds)" />
                  <rect x="45" y="52" width="50" height="6" rx="3" fill="var(--bd)" />
                  <rect x="45" y="66" width="60" height="6" rx="3" fill="var(--bd)" />
                  <rect x="45" y="80" width="35" height="6" rx="3" fill="var(--bd)" />
                </svg>
                <p><?= $search ? $textbotlang['panel']['invoiceNoOrderFound'] : $textbotlang['panel']['invoiceNoOrderYet'] ?></p>
              </div>
            </td>
          </tr>
        <?php else:
          $i = $offset + 1;
          foreach ($invoices as $inv):
            $st = $inv['Status'] ?? '';
            [$cls, $lbl] = $statusMap[$st] ?? ['tag-plain', $st ?: '—'];
            ?>
            <tr>
              <td data-label="#" class="cf"><?= $i++ ?></td>
              <td data-label="<?= $textbotlang['panel']['invoiceColUser'] ?>" class="cm"><?= htmlspecialchars($inv['id_user'] ?? '—') ?></td>
              <td data-label="<?= $textbotlang['panel']['invoiceColProduct'] ?>" class="cs"><?= htmlspecialchars(trunc($inv['name_product'] ?? '—', 28)) ?></td>
              <td data-label="<?= $textbotlang['panel']['invoiceColPrice'] ?>" class="cn cs"><?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['invoiceColTrackingCode'] ?></span></td>
              <td data-label="<?= $textbotlang['panel']['invoiceColDate'] ?>" class="cf"><?= safe_date($inv['time_sell'] ?? null, 'Y/m/d') ?></td>
              <td data-label="<?= $textbotlang['panel']['invoiceColStatus'] ?>"><span class="tag <?= $cls ?>"><?= $lbl ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="tbl-foot">
    <span><?= number_format($total) ?> <?= $textbotlang['panel']['invoiceColService'] ?> <?= $page ?> <?= $textbotlang['panel']['invoiceColPanel'] ?> <?= $totalPages ?></span>
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