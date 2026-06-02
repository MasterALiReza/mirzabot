<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../panels.php'; // For ManagePanel
require_auth();

// TEST CONNECTION AJAX
if (isset($_GET['action']) && $_GET['action'] === 'test_connection' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    try {
        $panel = db_fetch($pdo, "SELECT * FROM marzban_panel WHERE id = ?", [$id]);
        if (!$panel) {
            echo json_encode(['success' => false, 'message' => 'پنل یافت نشد.']);
            exit;
        }
        
        $mp = new ManagePanel();
        $res = $mp->DataUser($panel['name_panel'], 'mirza_test_connection_fake_user');
        
        $msg = $res['msg'] ?? '';
        $msgLower = strtolower($msg);
        
        if ($res['status'] === 'Unsuccessful' && (
            strpos($msgLower, 'not found') !== false || 
            strpos($msgLower, 'object invalid') !== false ||
            strpos($msgLower, 'unsuccessful') !== false ||
            empty($msg)
        )) {
            // These errors typically mean it reached the panel but the user didn't exist
            // Or the response was generic 'Unsuccessful' but not a connection timeout
            echo json_encode(['success' => true, 'message' => 'اتصال با موفقیت برقرار شد. پنل در دسترس است.']);
        } elseif ($res['status'] === 'Unsuccessful') {
            echo json_encode(['success' => false, 'message' => 'خطا در اتصال: ' . $msg]);
        } else {
            // Connected successfully
            echo json_encode(['success' => true, 'message' => 'اتصال با موفقیت برقرار شد.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطای سیستمی: ' . $e->getMessage()]);
    }
    exit;
}

// HANDLE ADD / EDIT POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name_panel = trim($_POST['name_panel'] ?? '');
    $url_panel = trim($_POST['url_panel'] ?? '');
    $username_panel = trim($_POST['username_panel'] ?? '');
    $password_panel = trim($_POST['password_panel'] ?? '');
    $type = trim($_POST['type'] ?? 'marzban');
    $status = trim($_POST['status'] ?? 'active');

    if ($action === 'add') {
        try {
            // Generate code_panel
            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(code_panel, 3) AS UNSIGNED)) as max_num FROM marzban_panel WHERE code_panel LIKE '7e%'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $max_num = $row ? (int)$row['max_num'] : 0;
            $code_panel = '7e' . ($max_num + 1);

            db_query($pdo, "INSERT INTO marzban_panel 
                (name_panel, url_panel, username_panel, password_panel, type, status, code_panel, MethodUsername, inboundstatus, inbound_deactive, agent, inboundid, conecton, Methodextend, namecustom, limit_panel, TestAccount, sublink, config, version_panel, on_hold_test, subvip, changeloc, status_extend, priceChangeloc) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                [
                    $name_panel, $url_panel, $username_panel, $password_panel, $type, $status, $code_panel,
                    '1', // MethodUsername default
                    'offinbounddisable', // inboundstatus default
                    '0', // inbound_deactive
                    'all', // agent
                    '1', // inboundid
                    'offconecton', // conecton
                    '1', // Methodextend default
                    'vpn', // namecustom
                    'unlimted', // limit_panel
                    'ONTestAccount', // TestAccount
                    'onsublink', // sublink
                    'offconfig', // config
                    '0', // version_panel
                    '1', // on_hold_test
                    'offsubvip', // subvip
                    'offchangeloc', // changeloc
                    'on_extend', // status_extend
                    '0' // priceChangeloc
                ]
            );
            flash('success', 'پنل جدید با موفقیت اضافه شد.');
        } catch (Exception $e) {
            flash('error', 'خطا در افزودن پنل: ' . $e->getMessage());
        }
        header('Location: panels_manage.php');
        exit;
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        try {
            db_query($pdo, "UPDATE marzban_panel SET name_panel = ?, url_panel = ?, username_panel = ?, password_panel = ?, type = ?, status = ? WHERE id = ?",
                [$name_panel, $url_panel, $username_panel, $password_panel, $type, $status, $id]
            );
            flash('success', 'پنل با موفقیت ویرایش شد.');
        } catch (Exception $e) {
            flash('error', 'خطا در ویرایش پنل: ' . $e->getMessage());
        }
        header('Location: panels_manage.php');
        exit;
    }
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        db_query($pdo, "DELETE FROM marzban_panel WHERE id = ?", [$id]);
        flash('success', "پنل با موفقیت حذف شد.");
    } catch (Exception $e) {
        flash('error', "خطا در حذف پنل.");
    }
    header('Location: panels_manage.php');
    exit;
}

try {
    $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel ORDER BY id ASC");
} catch (Exception $e) {
    $panels = [];
    error_log('panels_manage.php: ' . $e->getMessage());
}

$pageTitle = $textbotlang['panel']['panelsManageTitle'];
$pageLede = $textbotlang['panel']['panelsManageSubtitle'];
$activeNav = 'panels_manage';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title"><?= $textbotlang['panel']['panelsHeading'] ?> <small>(<?= count($panels) ?>)</small></div>
        </div>
        <div class="toolbar-end">
            <button class="btn btn-primary btn-sm" onclick="openPanelModal('add')">
                <?= icon('plus', 14) ?> <?= $textbotlang['panel']['panelsAddBtn'] ?>
            </button>
        </div>
    </div>

    <div class="tbl-wrap">
        <table class="tbl-xl">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th><?= $textbotlang['panel']['panelsColName'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColUrl'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColType'] ?></th>
                    <th><?= $textbotlang['panel']['panelsColStatus'] ?></th>
                    <th style="width:140px"><?= $textbotlang['panel']['panelsColActions'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($panels)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty">
                                <svg class="ill" viewBox="0 0 200 160" fill="none">
                                    <circle cx="100" cy="60" r="40" fill="var(--sf3)" />
                                    <path d="M62 105 Q100 88 138 105" stroke="var(--bds)" stroke-width="8" stroke-linecap="round" fill="none" />
                                </svg>
                                <p><?= $textbotlang['panel']['panelsNoData'] ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $i = 1;
                    foreach ($panels as $p):
                        $isActive = ($p['status'] ?? '') === 'active';
                        $type = $p['type'] ?? 'marzban';
                        $panelData = json_encode([
                            'id' => $p['id'],
                            'name_panel' => $p['name_panel'],
                            'url_panel' => $p['url_panel'],
                            'username_panel' => $p['username_panel'],
                            'password_panel' => $p['password_panel'],
                            'type' => $type,
                            'status' => $p['status']
                        ]);
                        ?>
                        <tr>
                            <td data-label="#" class="cf"><?= $i++ ?></td>
                            <td data-label="<?= $textbotlang['panel']['panelsColName'] ?>" class="cs" style="font-weight:600"><?= htmlspecialchars($p['name_panel'] ?? '—') ?></td>
                            <td data-label="<?= $textbotlang['panel']['panelsColUrl'] ?>">
                                <a href="<?= htmlspecialchars($p['url_panel'] ?? '#') ?>" target="_blank" style="color:var(--ac);text-decoration:none;display:inline-block;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:ltr;vertical-align:middle;">
                                    <?= htmlspecialchars($p['url_panel'] ?? '—') ?>
                                </a>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['panelsColType'] ?>">
                                <span class="tag tag-plain" style="text-transform:uppercase"><?= htmlspecialchars($type) ?></span>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['panelsColStatus'] ?>">
                                <?php if ($isActive): ?>
                                    <span class="tag tag-ok"><?= $textbotlang['panel']['panelsStatusActive'] ?></span>
                                <?php else: ?>
                                    <span class="tag tag-no"><?= $textbotlang['panel']['panelsStatusInactive'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['panelsColActions'] ?>">
                                <div style="display:flex;gap:4px">
                                    <button class="btn btn-ghost btn-sm btn-icon test-conn-btn" data-id="<?= $p['id'] ?>" title="<?= $textbotlang['panel']['panelsActionTest'] ?>">
                                        <?= icon('dashboard', 14) ?>
                                    </button>
                                    <button class="btn btn-ghost btn-sm btn-icon" data-panel="<?= htmlspecialchars($panelData, ENT_QUOTES, 'UTF-8') ?>" onclick="openPanelModal('edit', this)" title="ویرایش">
                                        <?= icon('edit', 14) ?>
                                    </button>
                                    <a href="?action=delete&id=<?= (int)$p['id'] ?>" class="btn btn-no btn-sm btn-icon" title="<?= $textbotlang['panel']['panelsActionDelete'] ?>" onclick="return confirm('<?= sprintf($textbotlang['panel']['panelsConfirmDelete'], htmlspecialchars($p['name_panel'])) ?>')">
                                        <?= icon('block', 14) ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Panel Modal -->
<div id="panelModalVeil" class="modal-veil">
    <div class="modal" style="max-width:500px">
        <div class="modal-head">
            <h3 id="panelModalTitle">افزودن پنل جدید</h3>
            <button type="button" class="modal-x" onclick="closePanelModal()">
                <?= icon('x', 16) ?>
            </button>
        </div>
        <form method="post" action="panels_manage.php">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:15px">
                <input type="hidden" name="action" id="panelAction" value="add">
            <input type="hidden" name="id" id="panelId" value="">
            
            <div class="field-group">
                <label>نوع پنل</label>
                <select name="type" id="panelType" class="input" required>
                    <option value="marzban">Marzban (مرزبان)</option>
                    <option value="marzneshin">Marzneshin (مرزنشین)</option>
                    <option value="MHSanaei-3.2">MHSanaei (سنایی)</option>
                    <option value="x-ui_single">X-UI (ایکس یو آی)</option>
                    <option value="alireza_single">Alireza (علیرضا)</option>
                    <option value="hiddify">Hiddify (هیدیفای)</option>
                    <option value="s_ui">S-UI (اس یو آی)</option>
                    <option value="WGDashboard">WGDashboard (وایرگارد)</option>
                    <option value="ibsng">IBSng</option>
                    <option value="mikrotik">Mikrotik</option>
                    <option value="Manualsale">Manual (دستی)</option>
                </select>
            </div>

            <div class="field-group">
                <label>نام پنل</label>
                <input type="text" name="name_panel" id="panelName" class="input" required placeholder="مثلا: سرور آلمان 1">
            </div>

            <div class="field-group">
                <label>آدرس پنل (URL)</label>
                <input type="url" name="url_panel" id="panelUrl" class="input" required placeholder="https://panel.example.com:2053" style="direction:ltr;text-align:left;">
            </div>

            <div class="field-group">
                <label>نام کاربری پنل</label>
                <input type="text" name="username_panel" id="panelUsername" class="input" placeholder="admin" style="direction:ltr;text-align:left;">
            </div>

            <div class="field-group">
                <label>رمز عبور پنل</label>
                <input type="password" name="password_panel" id="panelPassword" class="input" placeholder="••••••••" style="direction:ltr;text-align:left;">
            </div>

            <div class="field-group">
                <label>وضعیت اتصال</label>
                <select name="status" id="panelStatus" class="input">
                    <option value="active">فعال</option>
                    <option value="deactive">غیرفعال</option>
                </select>
            </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" onclick="closePanelModal()">انصراف</button>
                <button type="submit" class="btn btn-primary" style="margin-right:auto">ذخیره پنل</button>
            </div>
        </form>
    </div>
</div>

<!-- Test Connection Modal -->
<div id="testConnModalVeil" class="modal-veil">
    <div class="modal" style="max-width:400px;text-align:center">
        <div class="modal-body" style="padding:30px 20px">
            <div id="testConnLoader">
                <div class="spinner" style="margin:0 auto 15px"></div>
                <h4 style="margin:0">در حال تست اتصال به پنل...</h4>
                <p style="color:var(--ts);font-size:13px;margin:5px 0 0">لطفا چند لحظه صبر کنید</p>
            </div>
            <div id="testConnResult" style="display:none">
                <div id="testConnIcon" style="font-size:40px;margin-bottom:10px"></div>
                <h4 id="testConnTitle" style="margin:0 0 10px"></h4>
                <p id="testConnMessage" style="margin:0;font-size:14px;color:var(--ts);"></p>
                <button class="btn btn-ghost btn-sm" style="margin-top:20px" onclick="closeTestConnModal()">بستن</button>
            </div>
        </div>
    </div>
</div>

<style>
.spinner {
    width: 30px;
    height: 30px;
    border: 3px solid var(--sf3);
    border-top-color: var(--ac);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<script>
function openPanelModal(action, btn = null) {
    const modalVeil = document.getElementById('panelModalVeil');
    const title = document.getElementById('panelModalTitle');
    const actionInput = document.getElementById('panelAction');
    
    let data = null;
    if (btn && btn.getAttribute('data-panel')) {
        data = JSON.parse(btn.getAttribute('data-panel'));
    }
    
    if (action === 'add') {
        title.innerText = 'افزودن پنل جدید';
        actionInput.value = 'add';
        document.getElementById('panelId').value = '';
        document.getElementById('panelName').value = '';
        document.getElementById('panelUrl').value = '';
        document.getElementById('panelUsername').value = '';
        document.getElementById('panelPassword').value = '';
        document.getElementById('panelType').value = 'marzban';
        document.getElementById('panelStatus').value = 'active';
    } else if (action === 'edit' && data) {
        title.innerText = 'ویرایش پنل: ' + data.name_panel;
        actionInput.value = 'edit';
        document.getElementById('panelId').value = data.id;
        document.getElementById('panelName').value = data.name_panel;
        document.getElementById('panelUrl').value = data.url_panel;
        document.getElementById('panelUsername').value = data.username_panel;
        document.getElementById('panelPassword').value = data.password_panel;
        document.getElementById('panelType').value = data.type;
        document.getElementById('panelStatus').value = data.status;
    }
    
    modalVeil.classList.add('open');
}

function closePanelModal() {
    document.getElementById('panelModalVeil').classList.remove('open');
}

// Test Connection Logic
const testBtns = document.querySelectorAll('.test-conn-btn');
const testModalVeil = document.getElementById('testConnModalVeil');
const loader = document.getElementById('testConnLoader');
const resultView = document.getElementById('testConnResult');

testBtns.forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        
        // Show modal & loader
        loader.style.display = 'block';
        resultView.style.display = 'none';
        testModalVeil.classList.add('open');
        
        try {
            const res = await fetch(`panels_manage.php?action=test_connection&id=${id}`);
            const data = await res.json();
            
            loader.style.display = 'none';
            resultView.style.display = 'block';
            
            const icon = document.getElementById('testConnIcon');
            const title = document.getElementById('testConnTitle');
            const msg = document.getElementById('testConnMessage');
            
            if (data.success) {
                icon.innerHTML = '✅';
                title.innerText = 'اتصال موفق!';
                title.style.color = '#10b981';
            } else {
                icon.innerHTML = '❌';
                title.innerText = 'خطا در اتصال';
                title.style.color = '#ef4444';
            }
            msg.innerText = data.message;
            
        } catch (err) {
            loader.style.display = 'none';
            resultView.style.display = 'block';
            document.getElementById('testConnIcon').innerHTML = '⚠️';
            document.getElementById('testConnTitle').innerText = 'خطای شبکه';
            document.getElementById('testConnTitle').style.color = '#f59e0b';
            document.getElementById('testConnMessage').innerText = 'ارتباط با سرور برقرار نشد.';
        }
    });
});

function closeTestConnModal() {
    testModalVeil.classList.remove('open');
}
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
