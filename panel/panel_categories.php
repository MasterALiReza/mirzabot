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
$pageLede = 'مدیریت دسته‌بندی‌های پنل‌ها برای ساخت گروهی محصولات';
$activeNav = 'panel_categories';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="card fade-up">
  <div class="toolbar">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div class="toolbar-title">لیست دسته‌بندی‌ها <small>(<?= count($categories) ?>)</small></div>
    </div>
    <div class="toolbar-end">
      <button class="btn btn-primary btn-sm" id="btnAddCategory">
        <?= icon('plus', 14) ?> افزودن دسته‌بندی
      </button>
    </div>
  </div>

  <div class="tbl-wrap">
    <table class="table">
      <thead>
        <tr>
          <th style="width: 80px;">آیدی</th>
          <th>نام دسته‌بندی</th>
          <th style="width: 100px;">وضعیت</th>
          <th style="width: 180px;">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($categories) > 0): ?>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td data-label="آیدی" class="cell-mono"><?= htmlspecialchars((string)$cat['id']) ?></td>
              <td data-label="نام" style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($cat['name']) ?></td>
              <td data-label="وضعیت" class="no-label">
                <span class="status-pill <?= $cat['status'] === 'active' ? 'success' : 'danger' ?>">
                  <?= $cat['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
                </span>
              </td>
              <td data-label="عملیات">
                <div style="display: flex; gap: 8px;">
                  <button class="btn btn-sm btn-ghost" data-edit-cat="<?= htmlspecialchars(json_encode($cat, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                    <?= icon('edit', 14) ?> ویرایش
                  </button>
                  <a href="?action=delete&id=<?= (int)$cat['id'] ?>&_csrf=<?= csrf_token() ?>"
                     class="btn btn-sm btn-no"
                     data-confirm="آیا از حذف دسته‌بندی «<?= htmlspecialchars($cat['name']) ?>» اطمینان دارید؟ پنل‌های مرتبط بدون دسته‌بندی می‌شوند.">
                    <?= icon('trash', 14) ?> حذف
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="no-label">
              <div class="empty">
                <span style="opacity:0.3; display:inline-block; margin-bottom:1rem;"><?= icon('inbox', 48) ?></span><br>
                هیچ دسته‌بندی پنلی یافت نشد.
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Add/Edit -->
<div class="modal-veil" id="categoryModal">
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

        <div class="field" style="margin-bottom: 1.2rem;">
          <label>نام دسته‌بندی <span class="text-danger">*</span></label>
          <input type="text" name="name" id="catName" class="input" required maxlength="100" placeholder="مثلاً: سرورهای اروپا">
        </div>

        <div class="field">
          <label>وضعیت</label>
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

