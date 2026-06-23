<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = trim($_POST['name'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

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

// Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        db_query($pdo, "DELETE FROM panel_category WHERE id = ?", [$id]);
        db_query($pdo, "UPDATE marzban_panel SET panel_category_id = NULL WHERE panel_category_id = ?", [$id]);
        flash('success', "دسته‌بندی با موفقیت حذف شد.");
    } catch (Exception $e) {
        flash('error', "خطا در حذف دسته‌بندی.");
    }
    header('Location: panel_categories.php');
    exit;
}

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
              <td><?= htmlspecialchars($cat['id']) ?></td>
              <td><?= htmlspecialchars($cat['name']) ?></td>
              <td>
                <span class="badge <?= $cat['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                  <?= $cat['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
                </span>
              </td>
              <td>
                <button class="btn btn-sm btn-outline" data-edit-cat="<?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?>">
                  <?= icon('edit') ?> ویرایش
                </button>
                <a href="?action=delete&id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline btn-danger" onclick="return confirm('آیا از حذف این دسته‌بندی اطمینان دارید؟');">
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
<div class="modal-veil" id="categoryModal">
  <div class="modal" style="max-width: 500px;">
    <div class="modal-head">
      <h3 id="catModalTitle"><?= icon('folder', 16) ?> افزودن دسته‌بندی</h3>
      <button type="button" class="modal-x" onclick="closeModal('categoryModal')"><?= icon('x', 14) ?></button>
    </div>
    <form id="catForm" method="POST" action="panel_categories.php">
      <div class="modal-body">
        <input type="hidden" name="action" id="catAction" value="add">
        <input type="hidden" name="id" id="catId" value="">
        
        <div class="form-group" style="margin-bottom: 1rem;">
          <label class="form-label" style="display:block;margin-bottom:6px;">نام دسته‌بندی <span class="text-danger">*</span></label>
          <input type="text" name="name" id="catName" class="input" required>
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
        <button type="button" class="btn btn-ghost" onclick="closeModal('categoryModal')">انصراف</button>
        <button type="submit" class="btn btn-primary">ذخیره دسته‌بندی</button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
    // Attach Add button
    var addBtn = document.getElementById('btnAddCategory');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            var modal = document.getElementById('categoryModal');
            if (!modal) return;
            document.getElementById('catModalTitle').innerText = 'افزودن دسته\u200C\u0628\u0646\u062F\u06CC';
            document.getElementById('catAction').value = 'add';
            document.getElementById('catId').value = '';
            document.getElementById('catName').value = '';
            document.getElementById('catStatus').value = 'active';
            modal.classList.add('open');
        });
    }

    // Attach Edit buttons
    document.querySelectorAll('[data-edit-cat]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var cat = JSON.parse(this.getAttribute('data-edit-cat'));
            var modal = document.getElementById('categoryModal');
            if (!modal) return;
            document.getElementById('catModalTitle').innerText = '\u0648\u06CC\u0631\u0627\u06CC\u0634 \u062F\u0633\u062A\u0647\u200C\u0628\u0646\u062F\u06CC';
            document.getElementById('catAction').value = 'edit';
            document.getElementById('catId').value = cat.id;
            document.getElementById('catName').value = cat.name;
            document.getElementById('catStatus').value = cat.status;
            modal.classList.add('open');
        });
    });

    // Close modal on backdrop click
    var modal = document.getElementById('categoryModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.classList.remove('open');
        });
    }
}());
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
