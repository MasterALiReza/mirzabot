<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$currentUserData = db_fetch($pdo, "SELECT * FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
$isSuperAdmin = ($currentUserData && $currentUserData['rule'] === 'administrator');

// Handle Admin POST Actions
if ($isSuperAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id_admin = trim($_POST['id_admin'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rule     = $_POST['rule'] ?? 'administrator';

        if ($id_admin === '' || $username === '' || $password === '') {
            flash('error', 'تمام فیلدها الزامی هستند.');
        } else {
            $exists = db_fetch($pdo, "SELECT * FROM admin WHERE id_admin = ? OR username = ?", [$id_admin, $username]);
            if ($exists) {
                flash('error', 'شناسه کاربری یا نام کاربری تکراری است.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                try {
                    db_query($pdo, "INSERT INTO admin (id_admin, username, password, rule) VALUES (?, ?, ?, ?)", [
                        $id_admin, $username, $hash, $rule
                    ]);
                    flash('success', 'ادمین با موفقیت اضافه شد.');
                } catch (Exception $e) {
                    flash('error', 'خطا در افزودن ادمین.');
                }
            }
        }
        header('Location: users.php?tab=admins');
        exit;
    }

    if ($action === 'edit') {
        $id_admin = trim($_POST['id_admin'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rule     = $_POST['rule'] ?? 'administrator';

        if ($id_admin === '' || $username === '') {
            flash('error', 'شناسه و نام کاربری الزامی هستند.');
        } else {
            $existing = db_fetch($pdo, "SELECT * FROM admin WHERE id_admin = ?", [$id_admin]);
            if (!$existing) {
                flash('error', 'ادمین پیدا نشد.');
            } else {
                $checkUser = db_fetch($pdo, "SELECT * FROM admin WHERE username = ? AND id_admin != ?", [$username, $id_admin]);
                if ($checkUser) {
                    flash('error', 'نام کاربری تکراری است.');
                } else {
                    $hash = $password ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]) : $existing['password'];
                    try {
                        db_query($pdo, "UPDATE admin SET username = ?, password = ?, rule = ? WHERE id_admin = ?", [
                            $username, $hash, $rule, $id_admin
                        ]);
                        flash('success', 'ادمین با موفقیت ویرایش شد.');
                    } catch (Exception $e) {
                        flash('error', 'خطا در ویرایش ادمین.');
                    }
                }
            }
        }
        header('Location: users.php?tab=admins');
        exit;
    }

    if ($action === 'delete') {
        $id_admin = trim($_POST['id_admin'] ?? '');
        if ($id_admin === $currentUserData['id_admin']) {
            flash('error', 'شما نمی‌توانید حساب کاربری خودتان را حذف کنید.');
        } else {
            try {
                db_query($pdo, "DELETE FROM admin WHERE id_admin = ?", [$id_admin]);
                flash('success', 'ادمین با موفقیت حذف شد.');
            } catch (Exception $e) {
                flash('error', 'خطا در حذف ادمین.');
            }
        }
        header('Location: users.php?tab=admins');
        exit;
    }
}

// Active Tab Handling
$activeTab = $_GET['tab'] ?? 'users';
if ($activeTab === 'admins' && !$isSuperAdmin) {
    $activeTab = 'users';
}

$search = trim($_GET['q'] ?? '');

// ----------------------------------------
// Fetch Users Logic
// ----------------------------------------
$status = $_GET['status'] ?? '';
$role = $_GET['role'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(id LIKE ? OR COALESCE(username,'') LIKE ? OR COALESCE(namecustom,'') LIKE ? OR COALESCE(number,'') LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
if ($status !== '') {
    $where[] = "User_Status = ?";
    $params[] = $status;
}
if ($role !== '') {
    $where[] = "agent = ?";
    $params[] = $role;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $total = db_count($pdo, "SELECT COUNT(*) FROM user $whereSQL", $params);
    $users = db_fetchAll($pdo, "SELECT * FROM user $whereSQL ORDER BY register DESC LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
    $total = 0;
    $users = [];
    error_log('users.php: ' . $e->getMessage());
}

$totalPages = max(1, (int) ceil($total / $perPage));

$blockedCount = 0;
$agentCount = 0;
$agentAdvCount = 0;
try {
    $blockedCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE User_Status='block'");
    $agentCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent='n'");
    $agentAdvCount = db_count($pdo, "SELECT COUNT(*) FROM user WHERE agent='n2'");
} catch (Exception $e) {}


// ----------------------------------------
// Fetch Admins Logic
// ----------------------------------------
$admins = [];
$totalAdmins = 0;
$roleCount = [];
if ($isSuperAdmin) {
    $totalAdmins = (int) db_count($pdo, "SELECT COUNT(*) FROM admin");
    $totalAdminsRole = db_fetchAll($pdo, "SELECT rule, COUNT(*) as cnt FROM admin GROUP BY rule");
    foreach ($totalAdminsRole as $r) $roleCount[$r['rule']] = (int)$r['cnt'];

    $whereSQLAdmin = '';
    $paramsAdmin = [];
    if ($search !== '') {
        $whereSQLAdmin = "WHERE (id_admin LIKE ? OR username LIKE ? OR rule LIKE ?)";
        $paramsAdmin = ["%$search%", "%$search%", "%$search%"];
    }
    $admins = db_fetchAll($pdo, "SELECT * FROM admin $whereSQLAdmin ORDER BY id_admin ASC", $paramsAdmin);
}

// Role config map for admins
$roleConfig = [
    'administrator' => ['label' => 'مدیر کل',  'tag' => 'tag-ok',   'icon' => 'shield',  'color' => '#22c55e'],
    'Seller'        => ['label' => 'فروشنده',   'tag' => 'tag-info', 'icon' => 'tag',     'color' => '#3b82f6'],
    'support'       => ['label' => 'پشتیبان',   'tag' => 'tag-warn', 'icon' => 'life',    'color' => '#f59e0b'],
];
$getRoleConf = fn($rule) => $roleConfig[$rule] ?? ['label' => $rule, 'tag' => 'tag-plain', 'icon' => 'user', 'color' => '#6b7280'];


$pageTitle = 'مدیریت کاربران و همکاران';
$pageLede = 'مدیریت یکپارچه تمامی کاربران ربات و ادمین‌های پنل';
$activeNav = 'users';
include __DIR__ . '/inc/layout_head.php';
?>

<style>
.tab-navigation {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.tab-navigation::-webkit-scrollbar {
    display: none;
}
.tab-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--mute);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    margin-bottom: -1px;
    white-space: nowrap;
    flex-shrink: 0;
}
.tab-item:hover {
    color: var(--text);
}
.tab-item.active {
    color: var(--ac);
    border-bottom-color: var(--ac);
}
.tab-item .badge {
    background: var(--sf2);
    color: var(--text2);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    border: 1px solid var(--bd);
}
.tab-item.active .badge {
    background: var(--ac);
    color: #fff !important;
    border-color: var(--ac);
}
</style>

<div class="tab-navigation fade-up">
    <a href="?tab=users" class="tab-item <?= $activeTab === 'users' ? 'active' : '' ?>">
        <?= icon('users', 18) ?> کاربران ربات
        <span class="badge"><?= number_format($total) ?></span>
    </a>
    <?php if ($isSuperAdmin): ?>
    <a href="?tab=admins" class="tab-item <?= $activeTab === 'admins' ? 'active' : '' ?>">
        <?= icon('shield', 18) ?> مدیران و همکاران پنل
        <span class="badge"><?= number_format($totalAdmins) ?></span>
    </a>
    <?php endif; ?>
</div>

<?php if ($activeTab === 'users'): ?>
<!-- ================= USERS TAB ================= -->
<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title"><?= $textbotlang['panel']['usersHeading'] ?></div>

            <?php if ($blockedCount > 0): ?>
                <a href="?tab=users&status=block" class="tag tag-no" style="cursor:pointer"><?= $blockedCount ?> مسدود</a>
            <?php endif; ?>
            <?php if ($agentCount > 0): ?>
                <a href="?tab=users&role=n" class="tag tag-info" style="cursor:pointer"><?= $agentCount ?> نماینده عادی</a>
            <?php endif; ?>
            <?php if ($agentAdvCount > 0): ?>
                <a href="?tab=users&role=n2" class="tag tag-warn" style="cursor:pointer"><?= $agentAdvCount ?> نماینده پیشرفته</a>
            <?php endif; ?>
        </div>

        <form method="GET" id="usersForm" class="toolbar-end">
            <input type="hidden" name="tab" value="users">
            <select name="status" class="select" style="width:auto"
                onchange="document.getElementById('usersForm').submit()">
                <option value=""><?= $textbotlang['panel']['usersAllStatuses'] ?></option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersStatusActiveFilter'] ?></option>
                <option value="block" <?= $status === 'block' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersStatusBlockedFilter'] ?></option>
            </select>

            <select name="role" class="select" style="width:auto"
                onchange="document.getElementById('usersForm').submit()">
                <option value=""><?= $textbotlang['panel']['usersAllGroups'] ?></option>
                <option value="f" <?= $role === 'f' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersGroupFreeUser'] ?></option>
                <option value="n" <?= $role === 'n' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersGroupNormalAgent'] ?></option>
                <option value="n2" <?= $role === 'n2' ? 'selected' : '' ?>><?= $textbotlang['panel']['usersGroupAdvancedAgent'] ?></option>
            </select>

            <div class="search-box" style="min-width:260px">
                <?= icon('search', 15) ?>
                <input type="text" name="q" placeholder="<?= $textbotlang['panel']['usersSearchUserPlaceholder'] ?>"
                    value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="button" class="search-clear" onclick="window.location='users.php?tab=users'">✕</button>
                <button type="submit" class="search-btn"><?= $textbotlang['panel']['usersSearchBtn'] ?></button>
            </div>

            <?php if ($search || $status || $role): ?>
                <a href="users.php?tab=users" class="btn-link" style="font-size:.78rem;white-space:nowrap"><?= $textbotlang['panel']['usersClearBtn'] ?></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="tbl-wrap dash-orders">
        <table class="tbl-xl">
            <thead>
                <tr>
                    <th><?= $textbotlang['panel']['dashColUser'] ?? 'کاربر' ?></th>
                    <th><?= $textbotlang['panel']['usersColContactInfo'] ?? 'اطلاعات تماس' ?></th>
                    <th><?= $textbotlang['panel']['usersColFinancial'] ?? 'مالی و امتیاز' ?></th>
                    <th><?= $textbotlang['panel']['usersColStatusActions'] ?? 'وضعیت و عملیات' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="10">
                            <div class="empty">
                                <svg class="ill" viewBox="0 0 200 160" fill="none">
                                    <circle cx="100" cy="60" r="40" fill="var(--sf3)" />
                                    <circle cx="100" cy="47" r="18" fill="var(--bds)" />
                                    <path d="M62 105 Q100 88 138 105" stroke="var(--bds)" stroke-width="8"
                                        stroke-linecap="round" fill="none" />
                                </svg>
                                <p><?= $search ? $textbotlang['panel']['usersNoResultFound'] : $textbotlang['panel']['usersNoUserYet'] ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $i = $offset + 1;
                    foreach ($users as $u):
                        $agent = $u['agent'] ?? 'f';
                        $isBlocked = ($u['User_Status'] ?? '') === 'block';
                        $name = $u['namecustom'] ?? '';
                        if ($name === 'none') $name = '';
                        $uname = $u['username'] ?? '';
                        if ($uname === 'none') $uname = '';
                        ?>
                        <tr style="border-bottom: 1px solid var(--bd);">
                            <td data-label="<?= $textbotlang['panel']['dashColUser'] ?? 'کاربر' ?>" class="no-label">
                                <div class="user-profile-cell" style="display:flex; justify-content:space-between; align-items:center; width:100%; flex-wrap:wrap; gap:8px;">
                                    <div class="user-avatar-info" style="display:flex; align-items:center; gap:8px;">
                                        <div class="avatar-icon" style="background: rgba(var(--ac-rgb), 0.1); color: var(--ac); padding: 6px; border-radius: 50%; display:flex; align-items:center; justify-content:center;">
                                            <?= icon('user', 18) ?>
                                        </div>
                                        <span class="profile-name" style="font-weight:600; font-size:0.95rem;">
                                            <?php if ($name): ?>
                                                <?= htmlspecialchars(trunc($name, 18)) ?>
                                            <?php elseif ($uname): ?>
                                                <span style="direction:ltr; display:inline-block;">@<?= htmlspecialchars(trunc($uname, 18)) ?></span>
                                            <?php else: ?>
                                                <?= $textbotlang['panel']['dashColUser'] ?? 'کاربر' ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="profile-id-box" style="display:flex; align-items:center; gap:6px; font-size: 0.8rem; color: var(--mute); background:rgba(var(--glass-base-rgb),0.5); padding:4px 8px; border-radius:8px;">
                                        <?= icon('id-card', 14) ?>
                                        <span class="cf">آیدی :</span>
                                        <span class="cn" style="font-family:monospace; font-size:0.85rem;"><?= htmlspecialchars($u['id']) ?></span>
                                        <?php if ($uname && $name): ?>
                                            <span class="cm" style="color:var(--ac); margin-right:6px; direction:ltr; display:inline-block;">@<?= htmlspecialchars($uname) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['usersColContactInfo'] ?? 'اطلاعات تماس' ?>">
                                <div style="display:flex; flex-direction:column; gap:8px;">
                                    <div style="display:flex; align-items:center; gap:6px; font-size:0.85rem;">
                                        <span style="color:var(--mute)"><?= icon('phone', 14) ?></span>
                                        <span class="cm cf"><?= (!empty($u['number']) && $u['number'] !== 'none') ? htmlspecialchars($u['number']) : '—' ?></span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:6px; font-size:0.8rem;">
                                        <span style="color:var(--mute)"><?= icon('calendar', 14) ?></span>
                                        <span class="cf"><?= safe_date($u['register'] ?? null) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['usersColFinancial'] ?? 'مالی و امتیاز' ?>">
                                <div style="display:flex; flex-direction:column; gap:8px;">
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <span style="color:var(--mute)"><?= icon('wallet', 14) ?></span>
                                        <span class="cn" style="font-weight:600; font-size:1rem; color:var(--ac);">
                                            <?= number_format((int) ($u['Balance'] ?? 0)) ?> <span class="cf" style="font-size:0.75rem"><?= $textbotlang['panel']['dashTomanShort'] ?? 'ت' ?></span>
                                        </span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:6px; font-size:0.85rem;">
                                        <span style="color:var(--warn)"><?= icon('star', 14) ?></span>
                                        <span class="cn"><?= (int) ($u['score'] ?? 0) > 0 ? number_format((int) ($u['score'] ?? 0)) : '<span class="cf">—</span>' ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="<?= $textbotlang['panel']['usersColStatusActions'] ?? 'وضعیت و عملیات' ?>">
                                <div style="display:flex; align-items:center; justify-content:space-between; width:100%; gap:8px; flex-wrap:wrap;">
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <?php if ($isBlocked): ?>
                                            <span class="status-pill danger">مسدود</span>
                                        <?php else: ?>
                                            <span class="status-pill <?= $agent === 'n2' ? 'warning' : ($agent === 'n' ? 'info' : 'success') ?>"><?= user_role_label($agent) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex; gap:4px">
                                        <a href="user.php?id=<?= (int) $u['id'] ?>" class="btn btn-ghost btn-sm btn-icon"
                                            title="<?= $textbotlang['panel']['usersViewBtn'] ?>">
                                            <?= icon('eye', 14) ?>
                                        </a>
                                        <?php if ($isBlocked): ?>
                                            <a href="user_action.php?action=unblock&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                                class="btn btn-ok btn-sm btn-icon" title="<?= $textbotlang['panel']['usersUnblockBtn'] ?>"
                                                data-confirm="<?= sprintf($textbotlang['panel']['usersConfirmUnblockUser'], $name, $u['id']) ?>">
                                                <?= icon('check', 13) ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="user_action.php?action=block&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                                class="btn btn-no btn-sm btn-icon" title="<?= $textbotlang['panel']['usersBlockBtn'] ?>"
                                                data-confirm="<?= sprintf($textbotlang['panel']['usersConfirmBlockUser'], $name, $u['id']) ?>">
                                                <?= icon('block', 13) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="tbl-foot">
        <span><?= number_format($total) ?> <?= $textbotlang['panel']['usersPaginationTotalUsers'] ?> <?= $page ?> <?= $textbotlang['panel']['usersPaginationOf'] ?> <?= $totalPages ?></span>
        <div class="pager">
            <?php
            $qs = fn($p) => '?tab=users&q=' . urlencode($search)
                . '&status=' . urlencode($status)
                . '&role=' . urlencode($role)
                . '&page=' . $p;
            ?>
            <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($activeTab === 'admins' && $isSuperAdmin): ?>
<!-- ================= ADMINS TAB ================= -->
<div class="stats fade-up" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;">
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-emerald"><?= icon('shield', 20) ?></div>
            <div class="dash-card-title">مدیران کل</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill success">Administrator</span></div>
            <div class="dash-card-value"><?= $roleCount['administrator'] ?? 0 ?></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue"><?= icon('tag', 20) ?></div>
            <div class="dash-card-title">فروشندگان</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill info">Seller</span></div>
            <div class="dash-card-value"><?= $roleCount['Seller'] ?? 0 ?></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-amber"><?= icon('life', 20) ?></div>
            <div class="dash-card-title">پشتیبان‌ها</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill warning">Support</span></div>
            <div class="dash-card-value"><?= $roleCount['support'] ?? 0 ?></div>
        </div>
    </div>
</div>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title">لیست ادمین‌ها</div>
            <button class="btn btn-primary btn-sm" onclick="openAdminModal()">
                <?= icon('plus', 14) ?> افزودن همکار
            </button>
        </div>
        <form method="GET" class="toolbar-end">
            <input type="hidden" name="tab" value="admins">
            <div class="search-box" style="min-width:260px">
                <?= icon('search', 15) ?>
                <input type="text" name="q" placeholder="جستجو در شناسه، نام..."
                    value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="button" class="search-clear" onclick="window.location='users.php?tab=admins'">✕</button>
                <button type="submit" class="search-btn">جستجو</button>
            </div>
            <?php if ($search): ?>
                <a href="users.php?tab=admins" class="btn-link" style="font-size:.78rem;white-space:nowrap">پاک کردن فیلتر</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="tbl-wrap dash-orders">
        <table class="tbl-xl" id="adminsTbl">
            <thead>
                <tr>
                    <th><?= $textbotlang['panel']['dashColUser'] ?? 'کاربر' ?></th>
                    <th>دسترسی و وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty" style="padding:48px 20px">
                                <svg class="ill" viewBox="0 0 200 160" fill="none">
                                    <circle cx="100" cy="60" r="40" fill="var(--surface-3)" />
                                    <circle cx="100" cy="47" r="18" fill="var(--border-strong)" />
                                    <path d="M62 105 Q100 88 138 105" stroke="var(--border-strong)" stroke-width="8" stroke-linecap="round" fill="none" />
                                </svg>
                                <p>ادمینی یافت نشد.</p>
                                <button class="btn btn-primary" style="margin-top:12px" onclick="openAdminModal()">
                                    <?= icon('plus', 14) ?> افزودن اولین ادمین
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $i = 1;
                    foreach ($admins as $ad):
                        $rc  = $getRoleConf($ad['rule']);
                        $isMe = ($ad['id_admin'] === $currentUserData['id_admin']);
                ?>
                    <tr style="border-bottom: 1px solid var(--bd);">
                        <td data-label="<?= $textbotlang['panel']['dashColUser'] ?? 'کاربر' ?>" class="no-label">
                            <div class="user-profile-cell" style="display:flex; justify-content:space-between; align-items:center; width:100%; flex-wrap:wrap; gap:8px;">
                                <div class="user-avatar-info" style="display:flex; align-items:center; gap:8px;">
                                    <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,<?= $rc['color'] ?>33,<?= $rc['color'] ?>11);border:1px solid <?= $rc['color'] ?>44;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $rc['color'] ?>">
                                        <?= icon($rc['icon'], 15) ?>
                                    </div>
                                    <div>
                                        <div class="profile-name" style="font-weight:600; font-size:0.95rem;">
                                            <?= htmlspecialchars($ad['username']) ?>
                                        </div>
                                        <?php if ($isMe): ?>
                                            <div style="font-size:.68rem;color:var(--ac);margin-top:2px">● حساب جاری</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="profile-id-box" style="display:flex; align-items:center; gap:6px; font-size: 0.8rem; color: var(--mute); background:rgba(var(--glass-base-rgb),0.5); padding:4px 8px; border-radius:8px;">
                                    <?= icon('id-card', 14) ?>
                                    <span class="cf">شناسه تلگرام :</span>
                                    <span class="cm" style="font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($ad['id_admin']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td data-label="دسترسی و وضعیت">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="status-pill <?= $rc['color'] === '#22c55e' ? 'success' : ($rc['color'] === '#f59e0b' ? 'warning' : 'info') ?>"><?= $rc['label'] ?></span>
                                <?php if ($isMe): ?>
                                    <span class="status-pill success" style="opacity:0.8; padding: 3px 8px;">آنلاین</span>
                                <?php else: ?>
                                    <span class="status-pill neutral" style="opacity:0.8; padding: 3px 8px;">فعال</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="عملیات">
                            <div style="display:flex;gap:4px">
                                <button class="btn btn-ghost btn-sm btn-icon" title="ویرایش"
                                    onclick='openEditModal(<?= htmlspecialchars(json_encode([
                                        "id"       => $ad['id_admin'],
                                        "username" => $ad['username'],
                                        "rule"     => $ad['rule'],
                                    ]), ENT_QUOTES) ?>)'>
                                    <?= icon('edit', 14) ?>
                                </button>
                                <?php if (!$isMe): ?>
                                <button class="btn btn-no btn-sm btn-icon" title="حذف"
                                    onclick="deleteAdmin('<?= htmlspecialchars($ad['id_admin']) ?>', '<?= htmlspecialchars($ad['username']) ?>')">
                                    <?= icon('trash', 14) ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- =================== ADD MODAL =================== -->
<div class="modal-veil" id="addModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-head">
            <h3><?= icon('plus', 16) ?> افزودن ادمین جدید</h3>
            <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="field full">
                        <label>شناسه عددی تلگرام <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="id_admin" id="add-id" class="input"
                            placeholder="مثال: 123456789" required
                            pattern="\d+" title="فقط عدد مجاز است">
                        <small style="color:var(--mute);margin-top:4px;display:block">شناسه عددی کاربر در تلگرام</small>
                    </div>
                    <div class="field">
                        <label>نام کاربری پنل <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="username" id="add-username" class="input"
                            placeholder="مثال: admin1" required>
                    </div>
                    <div class="field">
                        <label>رمز عبور <span style="color:var(--accent)">*</span></label>
                        <input type="password" name="password" id="add-password" class="input"
                            placeholder="حداقل ۶ کاراکتر" required minlength="6">
                    </div>
                    <div class="field full">
                        <label>سطح دسترسی</label>
                        <select name="rule" id="add-rule" class="select" required onchange="updateRoleDesc('add')">
                            <option value="administrator">مدیر کل (Administrator)</option>
                            <option value="Seller">فروشنده (Seller)</option>
                            <option value="support">پشتیبان (Support)</option>
                        </select>
                        <div id="add-role-desc" style="margin-top:8px;padding:10px 12px;border-radius:8px;background:var(--surface-2);border:1px solid var(--border);font-size:.78rem;color:var(--mute)">
                            دسترسی کامل به تمام بخش‌های سیستم
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> افزودن ادمین</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<!-- =================== EDIT MODAL =================== -->
<div class="modal-veil" id="editModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-head">
            <h3><?= icon('edit', 16) ?> ویرایش ادمین</h3>
            <button class="modal-x" onclick="closeModal('editModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_admin" id="edit-id">

                <!-- Admin Info Card -->
                <div id="edit-info-card" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);margin-bottom:16px">
                    <div id="edit-avatar" style="width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;flex-shrink:0;color:#fff;background:linear-gradient(135deg,#3b82f6,#6366f1)">A</div>
                    <div>
                        <div id="edit-display-name" style="font-weight:600;font-size:.9rem"></div>
                        <div id="edit-display-id" style="font-size:.75rem;color:var(--mute);margin-top:2px"></div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field full">
                        <label>شناسه تلگرام</label>
                        <input type="text" id="edit-id-show" class="input" disabled
                            style="opacity:.6;cursor:not-allowed;background:var(--surface-2)">
                    </div>
                    <div class="field">
                        <label>نام کاربری پنل <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="username" id="edit-username" class="input" required>
                    </div>
                    <div class="field">
                        <label>رمز عبور جدید</label>
                        <input type="password" name="password" id="edit-password" class="input"
                            placeholder="خالی = بدون تغییر" minlength="6">
                        <small style="color:var(--mute);margin-top:4px;display:block">برای تغییر رمز پر کنید</small>
                    </div>
                    <div class="field full">
                        <label>سطح دسترسی</label>
                        <select name="rule" id="edit-rule" class="select" onchange="updateRoleDesc('edit')">
                            <option value="administrator">مدیر کل (Administrator)</option>
                            <option value="Seller">فروشنده (Seller)</option>
                            <option value="support">پشتیبان (Support)</option>
                        </select>
                        <div id="edit-role-desc" style="margin-top:8px;padding:10px 12px;border-radius:8px;background:var(--surface-2);border:1px solid var(--border);font-size:.78rem;color:var(--mute)"></div>
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

<!-- Delete hidden form -->
<form method="POST" id="delete-form" style="display:none">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_admin" id="d-id">
</form>

<script>
const roleDescriptions = {
    administrator: 'دسترسی کامل به تمام بخش‌های سیستم، مدیریت کاربران، تنظیمات و پنل‌ها',
    Seller:        'دسترسی به بخش فروش، مدیریت سفارشات و صدور فاکتور — بدون دسترسی به تنظیمات کلی',
    support:       'مشاهده اطلاعات کاربران و ارسال پیام — بدون دسترسی به مالی یا تنظیمات'
};

const avatarColors = {
    administrator: 'linear-gradient(135deg,#22c55e,#16a34a)',
    Seller:        'linear-gradient(135deg,#3b82f6,#6366f1)',
    support:       'linear-gradient(135deg,#f59e0b,#d97706)'
};

function updateRoleDesc(prefix) {
    const rule = document.getElementById(prefix + '-rule').value;
    const desc = document.getElementById(prefix + '-role-desc');
    if (desc) desc.innerText = roleDescriptions[rule] || '';
}

function openAdminModal() {
    document.getElementById('add-id').value       = '';
    document.getElementById('add-username').value  = '';
    document.getElementById('add-password').value  = '';
    document.getElementById('add-rule').value      = 'administrator';
    updateRoleDesc('add');
    openModal('addModal');
}

function openEditModal(data) {
    document.getElementById('edit-id').value       = data.id;
    document.getElementById('edit-id-show').value  = data.id;
    document.getElementById('edit-username').value = data.username;
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-rule').value     = data.rule;

    const initials = (data.username || '?').charAt(0).toUpperCase();
    document.getElementById('edit-avatar').innerText   = initials;
    document.getElementById('edit-avatar').style.background = avatarColors[data.rule] || 'linear-gradient(135deg,#6b7280,#4b5563)';
    document.getElementById('edit-display-name').innerText  = data.username;
    document.getElementById('edit-display-id').innerText    = 'ID: ' + data.id;

    updateRoleDesc('edit');
    openModal('editModal');
}

function deleteAdmin(id, name) {
    if (confirm('آیا از حذف ادمین «' + name + '» اطمینان دارید؟\nاین عمل قابل بازگشت نیست.')) {
        document.getElementById('d-id').value = id;
        document.getElementById('delete-form').submit();
    }
}

// Init role desc on page load if modal exists
if (document.getElementById('add-rule')) {
    updateRoleDesc('add');
}
</script>
<?php endif; ?>

<script src="js/users.js"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
