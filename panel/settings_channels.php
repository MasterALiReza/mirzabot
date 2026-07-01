<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    
    if ($action === 'add') {
        $remark = trim($_POST['remark'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $linkjoin = trim($_POST['linkjoin'] ?? '');
        
        if (empty($remark) || empty($link) || empty($linkjoin)) {
            $error = 'تمامی فیلدها الزامی هستند.';
        } elseif (!filter_var($linkjoin, FILTER_VALIDATE_URL)) {
            $error = 'لینک جوین وارد شده نامعتبر است.';
        } else {
            try {
                db_query($pdo, "INSERT INTO channels (link, remark, linkjoin) VALUES (?, ?, ?)", [$link, $remark, $linkjoin]);
                $success = 'کانال با موفقیت اضافه شد.';
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
                    try {
                        ensureTableUtf8mb4('channels');
                        db_query($pdo, "INSERT INTO channels (link, remark, linkjoin) VALUES (?, ?, ?)", [$link, $remark, $linkjoin]);
                        $success = 'کانال با موفقیت اضافه شد.';
                    } catch (Exception $e2) {
                        $error = 'خطا در افزودن کانال: ' . $e2->getMessage();
                    }
                } else {
                    $error = 'خطا در افزودن کانال: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'edit') {
        $old_link = trim($_POST['old_link'] ?? '');
        $remark = trim($_POST['remark'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $linkjoin = trim($_POST['linkjoin'] ?? '');
        
        if (empty($old_link) || empty($remark) || empty($link) || empty($linkjoin)) {
            $error = 'تمامی فیلدها الزامی هستند.';
        } elseif (!filter_var($linkjoin, FILTER_VALIDATE_URL)) {
            $error = 'لینک جوین وارد شده نامعتبر است.';
        } else {
            try {
                db_query($pdo, "UPDATE channels SET link = ?, remark = ?, linkjoin = ? WHERE link = ?", [$link, $remark, $linkjoin, $old_link]);
                $success = 'کانال با موفقیت ویرایش شد.';
            } catch (Exception $e) {
                $error = 'خطا در ویرایش کانال: ' . $e->getMessage();
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'delete') {
        csrf_check_get();
        $channel_link = trim($_GET['channel_link'] ?? '');
        if (!empty($channel_link)) {
            try {
                db_query($pdo, "DELETE FROM channels WHERE link = ?", [$channel_link]);
                $success = 'کانال با موفقیت حذف شد.';
            } catch (Exception $e) {
                $error = 'خطا در حذف کانال: ' . $e->getMessage();
            }
        }
    }
}

$channels = [];
try {
    $channels = db_fetchAll($pdo, "SELECT * FROM channels");
} catch (Exception $e) {
    // If table doesn't exist
}

$pageTitle = 'تنظیمات کانال‌های اجباری';
$activeNav = 'settings_channels';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div class="dash-header fade-up">
    <div>
        <h1 class="dash-title"><?= icon('shield', 28) ?> تنظیمات کانال‌های اجباری</h1>
        <p class="dash-subtitle">مدیریت کانال‌هایی که کاربران برای استفاده از ربات باید در آن‌ها عضو شوند</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger fade-up" style="margin-bottom: 20px;">
        <?= icon('alert-triangle', 20) ?> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success fade-up" style="margin-bottom: 20px;">
        <?= icon('check-circle', 20) ?> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="profile-grid fade-up" style="margin-top: 20px;">
    <!-- Add Channel Card -->
    <div>
        <div class="card">
            <div class="card-head">
                <div class="card-title"><?= icon('plus', 16) ?> افزودن کانال جدید</div>
            </div>
            <div class="card-body">
                <form method="POST" action="settings_channels.php" style="display: flex; flex-direction: column; gap: 15px;">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="field">
                        <label>نام کانال (برای نمایش)</label>
                        <input type="text" name="remark" class="input" placeholder="مثلاً: کانال اصلی" required>
                    </div>
                    
                    <div class="field">
                        <label>آیدی کانال (جهت بررسی)</label>
                        <input type="text" name="link" class="input" placeholder="مثلاً: @MyChannel یا -100XXXX" required dir="ltr">
                    </div>
                    
                    <div class="field">
                        <label>لینک جوین (برای دکمه)</label>
                        <input type="url" name="linkjoin" class="input" placeholder="https://t.me/joinchat/..." required dir="ltr">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="justify-content: center; width: 100%;">
                        <?= icon('plus', 16) ?> افزودن کانال
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Channels List Card -->
    <div>
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= icon('layers', 16) ?> لیست کانال‌های ثبت شده</div>
                    <div class="card-subtitle">مدیریت کانال‌های فعال و کنترل عضویت اجباری کاربران.</div>
                </div>
                <div class="tag tag-info"><?= count($channels) ?> کانال فعال</div>
            </div>
            
            <div class="tbl-wrap dash-channels">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>نام کانال</th>
                            <th>آیدی کانال</th>
                            <th>لینک جوین</th>
                            <th style="width: 150px; text-align: left;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($channels)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 48px; color: var(--mute);">
                                    <div class="empty" style="padding: 0;">
                                        <span style="opacity: 0.3; display: inline-block; margin-bottom: 1rem;"><?= icon('inbox', 48) ?></span><br>
                                        هیچ کانال اجباری ثبت نشده است.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($channels as $index => $ch): ?>
                                <tr>
                                    <td data-label="#" class="no-label"><?= $index + 1 ?></td>
                                    <td data-label="نام کانال" style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($ch['remark']) ?></td>
                                    <td data-label="آیدی کانال">
                                        <span class="tag tag-plain" style="font-family: monospace; font-size: 0.85rem;" dir="ltr"><?= htmlspecialchars($ch['link']) ?></span>
                                    </td>
                                    <td data-label="لینک جوین">
                                        <a href="<?= htmlspecialchars($ch['linkjoin']) ?>" target="_blank" class="btn-link" style="display: inline-flex; align-items: center; gap: 4px;" dir="ltr">
                                            <?= icon('link', 14) ?> <?= htmlspecialchars(strlen($ch['linkjoin']) > 30 ? substr($ch['linkjoin'], 0, 30) . '...' : $ch['linkjoin']) ?>
                                        </a>
                                    </td>
                                    <td data-label="عملیات" style="text-align: left;">
                                        <div style="display: inline-flex; gap: 8px;">
                                            <button type="button" class="btn btn-sm btn-ghost btn-icon" title="ویرایش" 
                                                    onclick='openEditModal(<?= json_encode($ch, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                                                <?= icon('edit', 14) ?>
                                            </button>
                                            <a href="settings_channels.php?action=delete&channel_link=<?= urlencode($ch['link']) ?>&_csrf=<?= csrf_token() ?>" 
                                               class="btn btn-sm btn-no btn-icon" title="حذف" 
                                               data-confirm="آیا از حذف کانال «<?= htmlspecialchars($ch['remark']) ?>» اطمینان دارید؟">
                                                <?= icon('trash', 14) ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-veil" id="editChannelModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-head">
            <h3><?= icon('edit', 16) ?> ویرایش کانال</h3>
            <button type="button" class="modal-x" onclick="closeEditModal()"><?= icon('x', 14) ?></button>
        </div>
        <form method="POST" action="settings_channels.php">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="old_link" id="editOldLink" value="">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 15px;">
                <div class="field">
                    <label>نام کانال (برای نمایش)</label>
                    <input type="text" name="remark" id="editRemark" class="input" required>
                </div>
                
                <div class="field">
                    <label>آیدی کانال (جهت بررسی)</label>
                    <input type="text" name="link" id="editLink" class="input" required dir="ltr">
                </div>
                
                <div class="field">
                    <label>لینک جوین (برای دکمه)</label>
                    <input type="url" name="linkjoin" id="editLinkjoin" class="input" required dir="ltr">
                </div>
            </div>
            
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> ذخیره تغییرات</button>
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">انصراف</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(ch) {
    document.getElementById('editOldLink').value = ch.link;
    document.getElementById('editRemark').value = ch.remark;
    document.getElementById('editLink').value = ch.link;
    document.getElementById('editLinkjoin').value = ch.linkjoin;
    document.getElementById('editChannelModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editChannelModal').classList.remove('open');
}
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
