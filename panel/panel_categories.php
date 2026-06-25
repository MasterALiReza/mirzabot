<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Auto-create table if missing (for users who didn't run table.php updater)
try {
    $pdo->query("SELECT 1 FROM panel_category LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS panel_category (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        status VARCHAR(50) DEFAULT 'active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
}

// ─── POST: Add / Edit ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check_post();

    $action = $_POST['action'];
    $name   = trim($_POST['name'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    // Validate name
    if ($name === '') {
        flash('error', 'نام دسته‌بندی نمی‌تواند خالی باشد.');
        header('Location: panel_categories.php');
        exit;
    }
    if (mb_strlen($name, 'UTF-8') > 100) {
        flash('error', 'نام دسته‌بندی حداکثر ۱۰۰ کاراکتر مجاز است.');
        header('Location: panel_categories.php');
        exit;
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    if ($action === 'add') {
        try {
            db_query($pdo, "INSERT INTO panel_category (name, status) VALUES (?, ?)", [$name, $status]);
            flash('success', 'دسته‌بندی پنل با موفقیت اضافه شد.');
        } catch (Exception $e) {
            flash('error', 'خطا در افزودن دسته‌بندی: ' . $e->getMessage());
        }
        header('Location: panel_categories.php');
        exit;

    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if ($id <= 0) {
            flash('error', 'شناسه دسته‌بندی معتبر نیست.');
            header('Location: panel_categories.php');
            exit;
        }
        try {
            db_query($pdo, "UPDATE panel_category SET name = ?, status = ? WHERE id = ?", [$name, $status, $id]);
            flash('success', 'دسته‌بندی پنل با موفقیت ویرایش شد.');
        } catch (Exception $e) {
            flash('error', 'خطا در ویرایش دسته‌بندی: ' . $e->getMessage());
        }
        header('Location: panel_categories.php');
        exit;
    }
}

// ─── GET: Delete ─────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    csrf_check_get();

    $id = (int)$_GET['id'];
    if ($id <= 0) {
        flash('error', 'شناسه دسته‌بندی معتبر نیست.');
        header('Location: panel_categories.php');
        exit;
    }
    try {
        db_query($pdo, "DELETE FROM panel_category WHERE id = ?", [$id]);
        db_query($pdo, "UPDATE marzban_panel SET panel_category_id = NULL WHERE panel_category_id = ?", [$id]);
        flash('success', 'دسته‌بندی با موفقیت حذف شد.');
    } catch (Exception $e) {
        flash('error', 'خطا در حذف دسته‌بندی.');
    }
    header('Location: panel_categories.php');
    exit;
}

// ─── Fetch list ──────────────────────────────────────────────────────────────
try {
    $categories = db_fetchAll($pdo, "SELECT * FROM panel_category ORDER BY id DESC");
} catch (Exception $e) {
    $categories = [];
}

$pageTitle = 'دسته‌بندی پنل‌ها';
$activeNav = 'panel_categories';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="header-actions">
  <div class="header-title">
    <h2><?= icon('folder') ?> دسته‌بندی پنل‌ها</h2>
    <p>مدیریت دسته‌بندی‌های پنل‌ها برای ساخت گروهی محصولات</p>
  </div>
  <button class="btn btn-primary" id="btnAddCategory">
    <?= icon('plus') ?> افزودن دسته‌بندی
  </button>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">لیست دسته‌بندی‌ها</h3>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>شناسه</th>
          <th>نام دسته‌بندی</th>
          <th>وضعیت</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($categories) > 0): ?>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td><?= htmlspecialchars((string)$cat['id']) ?></td>
              <td><?= htmlspecialchars($cat['name']) ?></td>
              <td>
                <span class="badge <?= $cat['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                  <?= $cat['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
                </span>
              </td>
              <td>
                <button class="btn btn-sm btn-outline" data-edit-cat="<?= htmlspecialchars(json_encode($cat, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                  <?= icon('edit') ?> ویرایش
                </button>
                <a href="?action=delete&id=<?= (int)$cat['id'] ?>&_csrf=<?= csrf_token() ?>"
                   class="btn btn-sm btn-outline btn-danger"
                   data-confirm="آیا از حذف دسته‌بندی «<?= htmlspecialchars($cat['name']) ?>» اطمینان دارید؟ پنل‌های مرتبط بدون دسته‌بندی می‌شوند.">
                  <?= icon('trash') ?> حذف
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="text-center text-muted" style="padding: 3rem;">
              <?= icon('inbox', ['width' => '48', 'height' => '48', 'style' => 'opacity:0.3; margin-bottom:1rem;']) ?><br>
              هیچ دسته‌بندی پنلی یافت نشد.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Add/Edit -->
<div class="modal-veil" id="categoryModal" onclick="if(event.target===this) closeCategoryModal()">
  <div class="modal" style="max-width: 500px;">
    <div class="modal-head">
      <h3 id="catModalTitle"><?= icon('folder', 16) ?> افزودن دسته‌بندی</h3>
      <button type="button" class="modal-x" id="btnCloseCatModal" onclick="closeCategoryModal()"><?= icon('x', 14) ?></button>
    </div>
    <form id="catForm" method="POST" action="panel_categories.php">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <div class="modal-body">
        <input type="hidden" name="action" id="catAction" value="add">
        <input type="hidden" name="id" id="catId" value="">

        <div class="form-group" style="margin-bottom: 1rem;">
          <label class="form-label" style="display:block;margin-bottom:6px;">نام دسته‌بندی <span class="text-danger">*</span></label>
          <input type="text" name="name" id="catName" class="input" required maxlength="100" placeholder="مثلاً: سرورهای اروپا">
        </div>

        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;">وضعیت</label>
          <select name="status" id="catStatus" class="input">
            <option value="active">فعال</option>
            <option value="inactive">غیرفعال</option>
          </select>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" id="btnCancelCatModal" onclick="closeCategoryModal()">انصراف</button>
        <button type="submit" class="btn btn-primary">ذخیره دسته‌بندی</button>
      </div>
    </form>
  </div>
</div>



<?php include __DIR__ . '/inc/layout_foot.php'; ?>

