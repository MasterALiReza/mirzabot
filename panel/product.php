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
  try {
    db_query(
      $pdo,
      "INSERT INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (?,?,?,?,?,?,?,'no_reset',?,?,'{}','0')",
      [$name, $code, (int) ($_POST['price_product'] ?? 0), (int) ($_POST['volume_product'] ?? 0), (int) ($_POST['time_product'] ?? 0), $_POST['namepanel'] ?? '', $_POST['agent_product'] ?? '', $_POST['note_product'] ?? '', $_POST['cetegory_product'] ?? '']
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
    try {
      db_query(
        $pdo,
        "UPDATE product SET name_product=?,price_product=?,Volume_constraint=?,Service_time=?,Location=?,agent=?,note=?,category=? WHERE id=?",
        [$name, (int) ($_POST['price_product'] ?? 0), (int) ($_POST['volume_product'] ?? 0), (int) ($_POST['time_product'] ?? 0), $_POST['namepanel'] ?? '', $_POST['agent_product'] ?? '', $_POST['note_product'] ?? '', $_POST['cetegory_product'] ?? '', $pid]
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

$panels = [];
try {
  $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel");
} catch (Exception $e) {
}
$products = db_fetchAll($pdo, "SELECT * FROM product ORDER BY id");

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
<div class="stats fade-up" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;">کل محصولات</div>
            <div class="icon-glow bg-blue">
                <?= icon('package', 20) ?>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($totalProducts) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <span class="status-pill neutral">محصول ثبت‌شده</span>
        </div>
    </div>
    
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;">دسته‌بندی‌ها</div>
            <div class="icon-glow bg-amber">
                <?= icon('layers', 20) ?>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($categoriesCount) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <span class="status-pill warning">دسته فعال</span>
        </div>
    </div>
    
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <div style="font-size: 0.95rem; color: var(--cf); font-weight: 600;">پنل‌های متصل</div>
            <div class="icon-glow bg-emerald">
                <?= icon('server', 20) ?>
            </div>
        </div>
        <div style="font-size: 2.2rem; font-weight: 700; color: var(--ct); margin-bottom: 12px; line-height: 1;">
            <?= number_format($panelsCount) ?>
        </div>
        <div style="font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 6px;">
            <span class="status-pill success">مرزبان متصل</span>
        </div>
    </div>
    
    <!-- Action Card -->
    <div class="dash-card" style="display: flex; flex-direction: column; justify-content: center; align-items: center; background: rgba(59, 130, 246, 0.05); border: 1px dashed rgba(59, 130, 246, 0.3); cursor: pointer; transition: all 0.2s;" onclick="openModal('addModal')" onmouseover="this.style.background='rgba(59,130,246,0.1)'" onmouseout="this.style.background='rgba(59,130,246,0.05)'">
        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--ac); color: white; display: flex; justify-content: center; align-items: center; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);">
            <?= icon('plus', 24) ?>
        </div>
        <div style="font-size: 1.1rem; font-weight: 600; color: var(--ac);">افزودن محصول جدید</div>
    </div>

</div>

<div class="card fade-up d1">
  <?php if (empty($products)): ?>
    <div class="empty" style="padding:60px 20px">
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
      <div class="toolbar-title"><?= $textbotlang['panel']['productColTime'] ?> <small>(<?= count($products) ?>)</small></div>
      <div class="search-box" style="min-width:220px">
        <?= icon('search', 14) ?>
        <input type="text" placeholder="<?= htmlspecialchars($textbotlang['panel']['productSearchPlaceholder'] ?? 'جستجوی محصول...') ?>" data-filter="prodTbl">
        <button type="button" class="search-clear">✕</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table id="prodTbl" class="tbl-xl">
        <thead>
          <tr>
            <th>#</th>
            <th><?= $textbotlang['panel']['productColName'] ?? 'نام محصول' ?></th>
            <th><?= $textbotlang['panel']['productColPrice'] ?? 'قیمت' ?></th>
            <th><?= $textbotlang['panel']['productColVolume'] ?? 'حجم' ?></th>
            <th><?= $textbotlang['panel']['productColTime'] ?? 'مدت زمان' ?></th>
            <th><?= $textbotlang['panel']['productColLocation'] ?? 'سرور/پنل' ?></th>
            <th><?= $textbotlang['panel']['productColCategory'] ?? 'دسته‌بندی' ?></th>
            <th><?= $textbotlang['panel']['productColId'] ?? 'کد' ?></th>
            <th><?= $textbotlang['panel']['productColActions'] ?? 'عملیات' ?></th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1;
          foreach ($products as $p): ?>
            <tr>
              <td class="cf"><?= $i++ ?></td>
              <td class="cs"><?= htmlspecialchars($p['name_product'] ?? '') ?></td>
              <td class="cn cs"><?= number_format((int) ($p['price_product'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['dashUnitToman'] ?? 'تومان' ?></span></td>
              <td class="cn"><?= htmlspecialchars($p['Volume_constraint'] ?? '—') ?> <span class="cf">GB</span></td>
              <td class="cn"><?= htmlspecialchars($p['Service_time'] ?? '—') ?> <span class="cf"><?= $textbotlang['panel']['productDayUnit'] ?? 'روز' ?></span></td>
              <td class="cf"><?= htmlspecialchars(trunc($p['Location'] ?? '—', 16)) ?></td>
              <td><?php if (!empty($p['category'])): ?><span
                    class="tag tag-info"><?= htmlspecialchars($p['category']) ?></span><?php else: ?><span
                    class="cf">—</span><?php endif; ?></td>
              <td class="cm" style="font-size:.72rem"><?= htmlspecialchars($p['code_product'] ?? '') ?></td>
              <td>
                <div style="display:flex;gap:5px">
                  <button class="btn btn-ghost btn-sm btn-icon" title=$textbotlang['panel']['productEditBtn']
                    onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                    <?= icon('edit', 13) ?>
                  </button>
                  <a href="product.php?delete=<?= (int) $p['id'] ?>&_csrf=<?= csrf_token() ?>"
                    class="btn btn-no btn-sm btn-icon" title=$textbotlang['panel']['productDeleteBtn']
                    data-confirm=sprintf($textbotlang['panel']['productConfirmDeleteProduct'], $p['name_product'])>
                    <?= icon('trash', 13) ?>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal-veil" id="addModal">
  <div class="modal">
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
            <input type="text" name="cetegory_product" class="input" placeholder="مثال: ایرانسل">
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
  <div class="modal">
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
            <input type="text" name="cetegory_product" id="edit_cat" class="input">
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
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ذخیره تغییرات</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<script src="js/product.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>