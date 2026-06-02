<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id_order = $_GET['id'] ?? '';
    csrf_check_get();

    if ($action === 'confirm') {
        $Payment_report = db_fetch($pdo, "SELECT * FROM Payment_report WHERE id_order = ?", [$id_order]);
        if ($Payment_report && $Payment_report['payment_Status'] === 'waiting') {
            require_once __DIR__ . '/../../panels.php';
            global $ManagePanel, $textbotlang, $from_id, $message_id, $Confirm_pay;
            $ManagePanel = new ManagePanel();
            $from_id = null;
            $message_id = null;
            $Confirm_pay = null;
            DirectPayment($id_order);
            flash('success', "تراکنش با موفقیت تایید و اعمال شد.");
            header('Location: payment.php');
            exit;
        }
    } elseif ($action === 'reject') {
        $Payment_report = db_fetch($pdo, "SELECT * FROM Payment_report WHERE id_order = ?", [$id_order]);
        if ($Payment_report && $Payment_report['payment_Status'] === 'waiting') {
            db_query($pdo, "UPDATE Payment_report SET payment_Status = 'reject', dec_not_confirmed = 'remove_all' WHERE id_order = ?", [$id_order]);
            flash('success', "تراکنش با موفقیت رد شد.");
            header('Location: payment.php');
            exit;
        }
    } elseif ($action === 'delete') {
        db_query($pdo, "DELETE FROM Payment_report WHERE id_order = ?", [$id_order]);
        flash('success', "تراکنش با موفقیت حذف شد.");
        header('Location: payment.php');
        exit;
    }
}

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($search !== '') {
  $where[] = "(`id_user` LIKE ? OR `id_order` LIKE ?)";
  $params = ["%$search%", "%$search%"];
}
if ($status !== '') {
  $where[] = "payment_Status = ?";
  $params[] = $status;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderSQL = "ORDER BY time DESC";

try {
  $total = db_count($pdo, "SELECT COUNT(*) FROM Payment_report $whereSQL", $params);
  $payments = db_fetchAll($pdo, "SELECT * FROM Payment_report $whereSQL $orderSQL LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
  $total = 0;
  $payments = [];
  flash('error', $textbotlang['panel']['paymentDbErrorTransactions'] . $e->getMessage());
}
$totalPages = max(1, (int) ceil($total / $perPage));

$totalSuccess = 0;
$todayCount = 0;
try {
  $totalSuccess = (int) db_query($pdo, "SELECT COALESCE(SUM(price),0) FROM Payment_report WHERE payment_Status ='paid'")->fetchColumn();
  $todayCount = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE time > ?", [strtotime('today')]);
} catch (Exception $e) {
}

$statusMap = [
  'paid' => ['tag-ok', $textbotlang['panel']['paymentStatusPaid']],
  'Unpaid' => ['tag-no', $textbotlang['panel']['paymentStatusUnpaid']],
  'expire' => ['tag-plain', $textbotlang['panel']['paymentStatusExpired']],
  'reject' => ['tag-no', $textbotlang['panel']['paymentStatusRejected']],
  'waiting' => ['tag-warn', $textbotlang['panel']['paymentStatusWaiting']],
];
$methodMap = [
  'cart to cart' => $textbotlang['panel']['paymentMethodCardToCard'],
  'low balance by admin' => $textbotlang['panel']['paymentMethodAdminDeduct'],
  'add balance by admin' => $textbotlang['panel']['paymentMethodAdminAdd'],
  'Currency Rial 1' => $textbotlang['panel']['paymentMethodRialGateway1'],
  'Currency Rial tow' => $textbotlang['panel']['paymentMethodRialGateway2'],
  'Currency Rial 3' => $textbotlang['panel']['paymentMethodRialGateway3'],
  'aqayepardakht' => $textbotlang['panel']['paymentMethodAqayePardakht'],
  'zarinpal' => $textbotlang['panel']['paymentMethodZarinpal'],
  'plisio' => 'Plisio',
  'arze digital offline' => $textbotlang['panel']['paymentMethodCryptoOffline'],
  'Star Telegram' => $textbotlang['panel']['paymentMethodTelegramStar'],
  'nowpayment' => 'NowPayment',
];

$pageTitle = $textbotlang['panel']['paymentTransactionsTitle'];
$pageLede = $textbotlang['panel']['paymentTransactionsSubtitle'];
$activeNav = 'payment';
include __DIR__ . '/inc/layout_head.php';
?>

<?php if ($msg = get_flash('error')): ?>
  <div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = get_flash('success')): ?>
  <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="stats" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat success">
    <div class="stat-label">جمع تراکنش‌های موفق</div>
    <div class="stat-num"><?= number_format($totalSuccess) ?> <small style="font-size: 0.6em; opacity: 0.8;">تومان</small></div>
    <div class="stat-meta">از ابتدای فعالیت</div>
  </div>
  <div class="stat">
    <div class="stat-label">تعداد کل</div>
    <div class="stat-num"><?= number_format($total) ?></div>
    <div class="stat-meta">رکورد تراکنش</div>
  </div>
  <div class="stat warn">
    <div class="stat-label">امروز</div>
    <div class="stat-num"><?= number_format($todayCount) ?></div>
    <div class="stat-meta">تراکنش جدید امروز</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-title">گزارش تراکنش‌ها <small>(<?= number_format($total) ?>)</small></div>
    <form method="GET" class="toolbar-end">
      <select name="status" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">همه وضعیت‌ها</option>
        <?php foreach ($statusMap as $k => [$_, $lbl]): ?>
          <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <div class="search-box" style="min-width:230px">
        <?= icon('search', 14) ?>
        <input type="text" name="q" placeholder="جستجوی شناسه سفارش یا کاربر..." value="<?= htmlspecialchars($search) ?>">
        <button type="button" class="search-clear">✕</button>
        <button type="submit" class="search-btn">جستجو</button>
      </div>
      <?php if ($search || $status): ?>
        <a href="payment.php" class="btn-link" style="font-size:.78rem;margin-right:10px">پاک‌سازی فیلتر</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="tbl-wrap">
    <table class="tbl-lg">
      <thead>
        <tr>
          <th>#</th>
          <th>کاربر</th>
          <th>شناسه تراکنش</th>
          <th>مبلغ</th>
          <th>روش پرداخت</th>
          <th>تاریخ</th>
          <th>وضعیت</th>
          <th style="text-align:left">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
          <tr>
            <td colspan="8">
              <div class="empty">
                <div class="empty-mark">—</div>
                <p>هیچ تراکنشی یافت نشد</p>
              </div>
            </td>
          </tr>
        <?php else:
          $i = $offset + 1;
          foreach ($payments as $p):
            $st = $p['payment_Status'] ?? '';
            [$cls, $lbl] = $statusMap[$st] ?? ['tag-plain', $st ?: '—'];
            $methodRaw = $p['Payment_Method'] ?? '';
            $method = $methodMap[$methodRaw] ?? ($methodRaw ?: '—');
            ?>
            <tr>
              <td data-label="#" style="color:var(--text-dim)"><?= $i++ ?></td>
              <td data-label="کاربر" class="cell-mono"><?= htmlspecialchars($p['id_user'] ?? '—') ?></td>
              <td data-label="شناسه تراکنش" class="cell-mono" style="color:var(--accent)">
                <?= htmlspecialchars(trunc((string) ($p['id_order'] ?? '—'), 18)) ?>
              </td>
              <td data-label="مبلغ" class="cell-strong cell-num"><?= number_format((int) ($p['price'] ?? 0)) ?> <span style="color:var(--text-dim);font-weight:400;font-size:.72rem">تومان</span></td>
              <td data-label="روش پرداخت" style="font-size:.8rem"><?= htmlspecialchars($method) ?></td>
              <td data-label="تاریخ" style="font-size:.78rem;color:var(--text-dim);white-space:nowrap">
                <?= safe_date($p['time'] ?? null, 'Y/m/d H:i') ?>
              </td>
              <td data-label="وضعیت"><span class="tag <?= $cls ?>"><?= $lbl ?></span></td>
              <td data-label="عملیات" style="text-align:left; white-space:nowrap;">
                <div class="actions">
                    <?php if ($st === 'waiting'): ?>
                      <a href="payment.php?action=confirm&id=<?= urlencode($p['id_order']) ?>&_csrf=<?= csrf_token() ?>" class="btn-icon" style="color:var(--success)" title="تایید" onclick="return confirm('آیا از تایید این تراکنش مطمئن هستید؟')">
                          <?= icon('check', 16) ?>
                      </a>
                      <a href="payment.php?action=reject&id=<?= urlencode($p['id_order']) ?>&_csrf=<?= csrf_token() ?>" class="btn-icon" style="color:var(--warn)" title="رد کردن" onclick="return confirm('آیا از رد این تراکنش مطمئن هستید؟')">
                          <?= icon('x', 16) ?>
                      </a>
                    <?php endif; ?>
                    <a href="payment.php?action=delete&id=<?= urlencode($p['id_order']) ?>&_csrf=<?= csrf_token() ?>" class="btn-icon" style="color:var(--danger)" title="حذف" onclick="return confirm('آیا از حذف این تراکنش مطمئن هستید؟')">
                        <?= icon('trash', 16) ?>
                    </a>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="tbl-foot">
    <span><?= number_format($total) ?> رکورد، صفحه <?= $page ?> از <?= $totalPages ?></span>
    <div class="pager">
      <?php $qs = fn($p) => '?q=' . urlencode($search) . '&status=' . urlencode($status) . '&page=' . $p; ?>
      <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
      <?php for ($p2 = max(1, $page - 2); $p2 <= min($totalPages, $page + 2); $p2++): ?>
        <a class="<?= $p2 === $page ? 'active' : '' ?>" href="<?= $qs($p2) ?>"><?= $p2 ?></a>
      <?php endfor; ?>
      <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>