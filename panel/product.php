<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  csrf_check_post();
  $name = trim($_POST['name_product'] ?? '');
  if ($name === '') {
    flash('error', $textbotlang['panel']['productNameRequired']);
    header('Location: product.php');
    exit;
  }
  if (db_count($pdo, "SELECT COUNT(*) FROM product WHERE name_product = ?", [$name])) {
    flash('error', $textbotlang['panel']['productNameExists']);
    header('Location: product.php');
    exit;
  }
  $code = bin2hex(random_bytes(2));
  $cat_input = trim($_POST['cetegory_product'] ?? '');
  if (!empty($cat_input)) {
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = ?");
      $stmt->execute([$cat_input]);
      if ($stmt->fetchColumn() == 0) {
          $stmt_add = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
          $stmt_add->execute([$cat_input]);
      }
  }

  try {
    db_query(
      $pdo,
      "INSERT INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status,inbounds,proxies) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
      [$name, $code, (int) ($_POST['price_product'] ?? 0), (int) ($_POST['volume_product'] ?? 0), (int) ($_POST['time_product'] ?? 0), $_POST['namepanel'] ?? '', $_POST['agent_product'] ?? '', $_POST['data_limit_reset'] ?? 'no_reset', $_POST['note_product'] ?? '', $cat_input, $_POST['hide_panel'] ?? '{}', $_POST['one_buy_status'] ?? '0', $_POST['inbounds'] ?? null, $_POST['proxies'] ?? null]
    );
    flash('success', $textbotlang['panel']['productAddedPrefix'] . $name . $textbotlang['panel']['productAddedSuffix']);
  } catch (Exception $e) {
    flash('error', $textbotlang['panel']['productDbError'] . $e->getMessage());
  }
  header('Location: product.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
  csrf_check_post();
  $pid = (int) ($_POST['edit_id'] ?? 0);
  $name = trim($_POST['name_product'] ?? '');
  if ($pid && $name !== '') {
    $cat_input = trim($_POST['cetegory_product'] ?? '');
    if (!empty($cat_input)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = ?");
        $stmt->execute([$cat_input]);
        if ($stmt->fetchColumn() == 0) {
            $stmt_add = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
            $stmt_add->execute([$cat_input]);
        }
    }

    try {
      db_query(
        $pdo,
        "UPDATE product SET name_product=?,price_product=?,Volume_constraint=?,Service_time=?,Location=?,agent=?,note=?,category=?,data_limit_reset=?,one_buy_status=?,inbounds=?,proxies=?,hide_panel=? WHERE id=?",
        [$name, (int) ($_POST['price_product'] ?? 0), (int) ($_POST['volume_product'] ?? 0), (int) ($_POST['time_product'] ?? 0), $_POST['namepanel'] ?? '', $_POST['agent_product'] ?? '', $_POST['note_product'] ?? '', $cat_input, $_POST['data_limit_reset'] ?? 'no_reset', $_POST['one_buy_status'] ?? '0', $_POST['inbounds'] ?? null, $_POST['proxies'] ?? null, $_POST['hide_panel'] ?? '{}', $pid]
      );
      flash('success', $textbotlang['panel']['productEdited']);
    } catch (Exception $e) {
      flash('error', $textbotlang['panel']['productErrorPrefix'] . $e->getMessage());
    }
  }
  header('Location: product.php');
  exit;
}

if (isset($_GET['delete'])) {
  csrf_check_get();
  db_query($pdo, "DELETE FROM product WHERE id = ?", [(int) $_GET['delete']]);
  flash('success', $textbotlang['panel']['productDeleted']);
  header('Location: product.php');
  exit;
}

// Category Management Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_cat') {
  csrf_check_post();
  $cat_name = trim($_POST['cat_name'] ?? '');
  if ($cat_name !== '') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = ?");
    $stmt->execute([$cat_name]);
    if ($stmt->fetchColumn() == 0) {
      $stmt = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
      $stmt->execute([$cat_name]);
      flash('success', "دسته‌بندی با موفقیت اضافه شد.");
    } else {
      flash('error', "این دسته‌بندی از قبل وجود دارد.");
    }
  }
  header('Location: product.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_cat') {
  csrf_check_post();
  $cat_id = (int)($_POST['cat_id'] ?? 0);
  $cat_name = trim($_POST['cat_name'] ?? '');
  if ($cat_id && $cat_name !== '') {
    $stmt = $pdo->prepare("SELECT remark FROM category WHERE id = ?");
    $stmt->execute([$cat_id]);
    $oldCat = $stmt->fetchColumn();

    if ($oldCat) {
      $stmt = $pdo->prepare("UPDATE category SET remark = ? WHERE id = ?");
      $stmt->execute([$cat_name, $cat_id]);

      $stmt2 = $pdo->prepare("UPDATE product SET category = ? WHERE category = ?");
      $stmt2->execute([$cat_name, $oldCat]);
      
      flash('success', 'نام دسته‌بندی با موفقیت تغییر کرد.');
    }
  }
  header('Location: product.php');
  exit;
}

if (isset($_GET['delete_cat'])) {
  csrf_check_get();
  $cat_id = (int)$_GET['delete_cat'];
  $stmt = $pdo->prepare("SELECT remark FROM category WHERE id = ?");
  $stmt->execute([$cat_id]);
  $oldCat = $stmt->fetchColumn();

  if ($oldCat) {
    $stmt = $pdo->prepare("DELETE FROM category WHERE id = ?");
    $stmt->execute([$cat_id]);
    
    $stmt2 = $pdo->prepare("UPDATE product SET category = NULL WHERE category = ?");
    $stmt2->execute([$oldCat]);
    flash('success', 'دسته‌بندی با موفقیت حذف شد.');
  }
  header('Location: product.php');
  exit;
}

$panels = [];
try {
  $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel");
} catch (Exception $e) {
}
$products = db_fetchAll($pdo, "SELECT * FROM product ORDER BY id");

// Auto-sync missing categories from product table to category table
try {
  $existingCategories = array_unique(array_filter(array_column($products, 'category')));
  foreach ($existingCategories as $cat) {
    $cat = trim($cat);
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = ?");
    $checkStmt->execute([$cat]);
    if ($checkStmt->fetchColumn() == 0) {
      $insertStmt = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
      $insertStmt->execute([$cat]);
    }
  }
} catch (Exception $e) {}

$categories_db = db_fetchAll($pdo, "SELECT * FROM category ORDER BY id DESC");

$pageTitle = $textbotlang['panel']['productsTitle'];
$pageLede = $textbotlang['panel']['productsSubtitle'];
$activeNav = 'product';
include __DIR__ . '/inc/layout_head.php';
?>

<?php
$totalProducts = count($products);
$categoriesCount = count(array_unique(array_filter(array_column($products, 'category'))));
$panelsCount = count(array_unique(array_filter(array_column($products, 'Location'))));
?>
<!-- Top Statistics Cards -->
<div class="stats fade-up" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;">
    
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue">
                <?= icon('package', 20) ?>
            </div>
            <div class="dash-card-title">کل محصولات</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill neutral">محصول ثبت‌شده</span>
            </div>
            <div class="dash-card-value">
                <?= number_format($totalProducts) ?>
            </div>
        </div>
    </div>
    
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-amber">
                <?= icon('layers', 20) ?>
            </div>
            <div class="dash-card-title">دسته‌بندی‌ها</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill warning">دسته فعال</span>
            </div>
            <div class="dash-card-value">
                <?= number_format($categoriesCount) ?>
            </div>
        </div>
    </div>
    
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-emerald">
                <?= icon('server', 20) ?>
            </div>
            <div class="dash-card-title">پنل‌های متصل</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill success">پنل متصل</span>
            </div>
            <div class="dash-card-value">
                <?= number_format($panelsCount) ?>
            </div>
        </div>
    </div>

</div>

<div class="card fade-up d1">
  <?php if (empty($products)): ?>
    <div class="empty" style="padding:48px 20px">
      <svg class="ill" viewBox="0 0 200 160" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="40" y="30" width="120" height="100" rx="12" fill="var(--surface-3)" />
        <rect x="56" y="50" width="88" height="12" rx="6" fill="var(--border-strong)" />
        <rect x="56" y="72" width="60" height="8" rx="4" fill="var(--border)" />
        <rect x="56" y="90" width="72" height="8" rx="4" fill="var(--border)" />
        <rect x="56" y="108" width="44" height="8" rx="4" fill="var(--border)" />
        <circle cx="155" cy="125" r="22" fill="var(--accent-s)" stroke="var(--accent)" stroke-width="2" />
        <path d="M147 125h16M155 117v16" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" />
      </svg>
      <p><?= $textbotlang['panel']['productColName'] ?></p>
      <button class="btn btn-primary" style="margin-top:14px" onclick="openModal('addModal')"><?= icon('plus', 14) ?>
        <?= $textbotlang['panel']['productColVolume'] ?></button>
    </div>
  <?php else: ?>
    <div class="toolbar">
      <div class="toolbar-title">فهرست محصولات <small>(<?= count($products) ?>)</small></div>
      <div class="toolbar-end">
        <div class="toolbar-filters">
          <select class="select" id="filter-category">
              <option value="all">همه دسته‌بندی‌ها</option>
              <?php foreach (array_unique(array_filter(array_column($products, 'category'))) as $cat): ?>
                  <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
              <?php endforeach; ?>
          </select>
          <select class="select" id="filter-panel">
              <option value="all">همه سرورها/پنل‌ها</option>
              <?php foreach (array_unique(array_filter(array_column($products, 'Location'))) as $loc): ?>
                  <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
              <?php endforeach; ?>
          </select>
        </div>
        <div class="search-box">
          <?= icon('search', 14) ?>
          <input type="text" placeholder="جستجوی محصول..." id="filter-search">
          <button type="button" class="search-clear">✕</button>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="openModal('catManageModal')" style="margin-left:8px;">
          <?= icon('package', 14) ?> مدیریت دسته‌بندی‌ها
        </button>
        <button class="btn btn-primary btn-sm btn-add-product" onclick="openModal('addModal')">
          <?= icon('plus', 14) ?> افزودن محصول جدید
        </button>
      </div>
    </div>
    <div class="product-grid" id="prodTbl">
      <div class="filter-empty-state" id="filter-empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              <line x1="11" y1="8" x2="11" y2="14"></line>
              <line x1="8" y1="11" x2="14" y2="11"></line>
          </svg>
          <p>هیچ محصولی با این فیلترها یافت نشد.</p>
      </div>
      <?php foreach ($products as $p): ?>
        <div class="product-card filterable-item" data-category="<?= htmlspecialchars($p['category'] ?? '') ?>" data-panel="<?= htmlspecialchars($p['Location'] ?? '') ?>">
            <div class="pc-head">
                <div>
                    <h3 class="pc-title"><?= htmlspecialchars($p['name_product'] ?? '') ?></h3>
                    <div class="pc-price">
                        <?= number_format((int) ($p['price_product'] ?? 0)) ?> <small>تومان</small>
                    </div>
                </div>
                <?php if (!empty($p['category'])): ?>
                    <span class="tag tag-info"><?= htmlspecialchars($p['category']) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="pc-features">
                <div class="pc-feature">
                    <?= icon('database', 15) ?>
                    <span>حجم: <b><?= htmlspecialchars($p['Volume_constraint'] ?? '—') ?></b> GB</span>
                </div>
                <div class="pc-feature">
                    <?= icon('clock', 15) ?>
                    <span>زمان: <b><?= htmlspecialchars($p['Service_time'] ?? '—') ?></b> روز</span>
                </div>
                <div class="pc-feature">
                    <?= icon('server', 15) ?>
                    <span>پنل: <b><?= htmlspecialchars(trunc($p['Location'] ?? '—', 16)) ?></b></span>
                </div>
            </div>
            
            <div class="pc-foot">
                <span class="pc-code">#<?= htmlspecialchars($p['code_product'] ?? '') ?></span>
                <div class="pc-actions">
                  <button class="btn btn-ghost btn-sm btn-icon" title="ویرایش"
                    onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                    <?= icon('edit', 14) ?>
                  </button>
                  <a href="product.php?delete=<?= (int) $p['id'] ?>&_csrf=<?= csrf_token() ?>"
                    class="btn btn-no btn-sm btn-icon" title="حذف"
                    data-confirm="آیا از حذف محصول «<?= htmlspecialchars($p['name_product'] ?? '') ?>» مطمئن هستید؟">
                    <?= icon('trash', 14) ?>
                  </a>
                </div>
            </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<datalist id="category_list">
  <?php foreach ($categories_db as $c_db): ?>
    <option value="<?= htmlspecialchars($c_db['remark'] ?? '') ?>"></option>
  <?php endforeach; ?>
</datalist>

<div class="modal-veil" id="addModal">
  <div class="modal" style="max-width:540px">
    <div class="modal-head">
      <h3>افزودن محصول جدید</h3>
      <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
          <div class="field full">
            <label>نام محصول</label>
            <input type="text" name="name_product" class="input" placeholder="مثال: سرور آلمان ۳۰ روزه" required>
          </div>
          <div class="field">
            <label>قیمت (تومان)</label>
            <input type="number" name="price_product" class="input" placeholder="50000" min="0">
          </div>
          <div class="field">
            <label>حجم (گیگابایت)</label>
            <input type="number" name="volume_product" class="input" placeholder="50" min="0">
          </div>
          <div class="field">
            <label>مدت زمان (روز)</label>
            <input type="number" name="time_product" class="input" placeholder="30" min="0">
          </div>
          <div class="field">
            <label>دسته‌بندی</label>
            <input type="text" name="cetegory_product" class="input" list="category_list" placeholder="مثال: ایرانسل" autocomplete="off">
          </div>
          <div class="field">
            <label>پنل/سرور</label>
            <select name="namepanel" class="select">
              <option value="">-- انتخاب پنل --</option>
              <?php foreach ($panels as $pl): ?>
                <option value="<?= htmlspecialchars($pl['name_panel'] ?? $pl['id']) ?>">
                  <?= htmlspecialchars($pl['name_panel'] ?? $pl['id']) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>سطح دسترسی محصول</label>
            <select name="agent_product" class="select">
              <option value="f">کاربر عادی</option>
              <option value="n">نماینده</option>
              <option value="n2">نماینده پیشرفته</option>
            </select>
          </div>
          <div class="field full">
            <label>توضیحات محصول</label>
            <input type="text" name="note_product" class="input" placeholder="توضیحات (اختیاری)">
          </div>
          <div class="field">
            <label>وضعیت ریست حجم</label>
            <select name="data_limit_reset" class="select">
              <option value="no_reset">بدون ریست</option>
              <option value="day">روزانه</option>
              <option value="week">هفتگی</option>
              <option value="month">ماهانه</option>
              <option value="year">سالانه</option>
            </select>
          </div>
          <div class="field">
            <label>محدودیت خرید</label>
            <select name="one_buy_status" class="select">
              <option value="0">آزاد (چندبار خرید)</option>
              <option value="1">فقط یکبار خرید</option>
            </select>
          </div>
          <div class="field full">
            <label>اینباندهای اختصاصی (JSON)</label>
            <input type="text" name="inbounds" class="input" placeholder='مثال: {"vless":["inbound1"]}'>
          </div>
          <div class="field full">
            <label>پراکسی‌های اختصاصی (JSON)</label>
            <input type="text" name="proxies" class="input" placeholder='مثال: {"vless":{}}'>
          </div>
          <div class="field full">
            <label>مخفی از پنل‌ها (JSON)</label>
            <input type="text" name="hide_panel" class="input" placeholder='مثال: ["panel1", "panel2"]' value='{}'>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> ذخیره محصول</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-veil" id="editModal">
  <div class="modal" style="max-width:540px">
    <div class="modal-head">
      <h3>ویرایش محصول</h3>
      <button class="modal-x" onclick="closeModal('editModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="form-grid">
          <div class="field full">
            <label>نام محصول</label>
            <input type="text" name="name_product" id="edit_name" class="input" required>
          </div>
          <div class="field">
            <label>قیمت (تومان)</label>
            <input type="number" name="price_product" id="edit_price" class="input" min="0">
          </div>
          <div class="field">
            <label>حجم (گیگابایت)</label>
            <input type="number" name="volume_product" id="edit_volume" class="input" min="0">
          </div>
          <div class="field">
            <label>مدت زمان (روز)</label>
            <input type="number" name="time_product" id="edit_time" class="input" min="0">
          </div>
          <div class="field">
            <label>دسته‌بندی</label>
            <input type="text" name="cetegory_product" id="edit_cat" class="input" list="category_list" autocomplete="off">
          </div>
          <div class="field">
            <label>پنل/سرور</label>
            <select name="namepanel" id="edit_panel" class="select">
              <option value="">-- انتخاب پنل --</option>
              <?php foreach ($panels as $pl): ?>
                <option value="<?= htmlspecialchars($pl['name_panel'] ?? $pl['id']) ?>">
                  <?= htmlspecialchars($pl['name_panel'] ?? $pl['id']) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>سطح دسترسی محصول</label>
            <select name="agent_product" id="edit_agent" class="select">
              <option value="f">کاربر عادی</option>
              <option value="n">نماینده</option>
              <option value="n2">نماینده پیشرفته</option>
            </select>
          </div>
          <div class="field full">
            <label>توضیحات محصول</label>
            <input type="text" name="note_product" id="edit_note" class="input">
          </div>
          <div class="field">
            <label>وضعیت ریست حجم</label>
            <select name="data_limit_reset" id="edit_data_limit_reset" class="select">
              <option value="no_reset">بدون ریست</option>
              <option value="day">روزانه</option>
              <option value="week">هفتگی</option>
              <option value="month">ماهانه</option>
              <option value="year">سالانه</option>
            </select>
          </div>
          <div class="field">
            <label>محدودیت خرید</label>
            <select name="one_buy_status" id="edit_one_buy_status" class="select">
              <option value="0">آزاد (چندبار خرید)</option>
              <option value="1">فقط یکبار خرید</option>
            </select>
          </div>
          <div class="field full">
            <label>اینباندهای اختصاصی (JSON)</label>
            <input type="text" name="inbounds" id="edit_inbounds" class="input" placeholder='مثال: {"vless":["inbound1"]}'>
          </div>
          <div class="field full">
            <label>پراکسی‌های اختصاصی (JSON)</label>
            <input type="text" name="proxies" id="edit_proxies" class="input" placeholder='مثال: {"vless":{}}'>
          </div>
          <div class="field full">
            <label>مخفی از پنل‌ها (JSON)</label>
            <input type="text" name="hide_panel" id="edit_hide_panel" class="input" placeholder='مثال: ["panel1", "panel2"]'>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ذخیره تغییرات</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Manage Categories -->
<div class="modal-veil" id="catManageModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title"><?= icon('package') ?> مدیریت دسته‌بندی‌ها</div>
      <button class="modal-close" onclick="closeModal('catManageModal')">✕</button>
    </div>
    <div class="modal-body" style="padding:0">
      <?php if (empty($categories_db)): ?>
        <div class="empty" style="padding:32px 20px;">
          <p>هیچ دسته‌بندی ثبت نشده است</p>
        </div>
      <?php else: ?>
        <table class="table" style="margin:0; border-radius:0;">
          <thead>
            <tr>
              <th style="width:60px">شناسه</th>
              <th>نام دسته‌بندی</th>
              <th style="text-align:left">عملیات</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories_db as $c): ?>
            <tr>
              <td class="td-id">#<?= $c['id'] ?></td>
              <td><?= htmlspecialchars($c['remark']) ?></td>
              <td style="text-align:left; gap:4px; display:flex; justify-content:flex-end;">
                <button class="btn-icon" title="ویرایش" onclick="openEditCatModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['remark'])) ?>')"><?= icon('edit', 14) ?></button>
                <button class="btn-icon danger" title="حذف" onclick="confirmDeleteCat(<?= $c['id'] ?>)"><?= icon('trash', 14) ?></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-primary" onclick="openAddCatModal()"><?= icon('plus', 13) ?> افزودن دسته‌بندی</button>
    </div>
  </div>
</div>

<!-- Modal Add/Edit Category -->
<div class="modal-veil" id="catActionModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title" id="catModalTitle">دسته‌بندی</div>
      <button class="modal-close" onclick="closeModal('catActionModal')">✕</button>
    </div>
    <form action="" method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" id="catModalAction" value="add_cat">
        <input type="hidden" name="cat_id" id="catModalId" value="">
        <div class="field">
          <label>نام دسته‌بندی</label>
          <input type="text" name="cat_name" id="catModalName" class="input" required>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ذخیره</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('catActionModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddCatModal() {
  closeModal('catManageModal');
  document.getElementById('catModalTitle').innerText = 'افزودن دسته‌بندی';
  document.getElementById('catModalAction').value = 'add_cat';
  document.getElementById('catModalId').value = '';
  document.getElementById('catModalName').value = '';
  openModal('catActionModal');
}
function openEditCatModal(id, name) {
  closeModal('catManageModal');
  document.getElementById('catModalTitle').innerText = 'ویرایش دسته‌بندی';
  document.getElementById('catModalAction').value = 'edit_cat';
  document.getElementById('catModalId').value = id;
  document.getElementById('catModalName').value = name;
  openModal('catActionModal');
}
function confirmDeleteCat(id) {
  if (confirm('آیا از حذف این دسته‌بندی اطمینان دارید؟ تمامی محصولاتی که این دسته‌بندی را دارند، بدون دسته‌بندی خواهند شد.')) {
    window.location.href = 'product.php?delete_cat=' + id + '&csrf=<?= csrf_token() ?>';
  }
}
</script>

<script src="js/product.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
