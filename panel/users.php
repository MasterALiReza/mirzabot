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
                    require_once __DIR__ . '/../botapi.php';
                    if (isset($textbotlang['Admin']['manageadmin']['adminAddedSendUser'])) {
                        sendmessage($id_admin, $textbotlang['Admin']['manageadmin']['adminAddedSendUser'], null, 'HTML');
                    } else {
                        sendmessage($id_admin, "شما به عنوان ادمین به ربات اضافه شدید.", null, 'HTML');
                    }
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
                require_once __DIR__ . '/../botapi.php';
                sendmessage($id_admin, "شما از مدیریت ربات حذف شدید.\nبرای دسترسی به منوی کاربری ربات، لطفاً دستور /start را ارسال کنید.", json_encode(['remove_keyboard' => true]));
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
if ($role === 'agents') {
    $where[] = "agent IN ('n', 'n2')";
} elseif ($role !== '') {
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


$pageTitle = 'مدیریت کاربران و ادمین‌ها';
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
    min-height: 44px;
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

/* ========================================================================= */
/* VANGUARD HIGH-END UI ARCHITECTURE (ETHEREAL GLASS)                        */
/* ========================================================================= */

.vanguard-list-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 12px 0;
}

/* Minimal SaaS List Layout */
.vanguard-list-row {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1fr auto;
    gap: 16px;
    align-items: center;
    background: var(--sf);
    border-bottom: 1px solid var(--bd);
    padding: 16px 20px;
    transition: all 300ms ease;
    animation: fadeUp 600ms cubic-bezier(0.32, 0.72, 0, 1) both;
}
.vanguard-list-row:hover {
    background: var(--sf2);
    border-color: var(--bds);
}
.vanguard-list-row:first-child {
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}
.vanguard-list-row:last-child {
    border-bottom: none;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
}

/* Columns */
.v-list-col {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.v-list-meta {
    flex-direction: row;
    align-items: center;
    gap: 14px;
}

/* Avatar */
.vanguard-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--acs);
    border: 1px solid var(--acg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ac);
    flex-shrink: 0;
}

/* Typography */
.v-list-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.v-list-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.v-list-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--text);
}
.v-list-sub {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.8rem;
    color: var(--mute);
}
.v-list-uname {
    direction: ltr;
}
.v-list-id {
    cursor: pointer;
    transition: color 0.2s;
}
.v-list-id:hover {
    color: var(--ac);
}

/* Data Items */
.v-list-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}
.v-list-label {
    color: var(--mute);
    display: flex;
    align-items: center;
}
.v-list-value {
    color: var(--text);
    font-weight: 500;
}

/* Actions */
.v-list-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.v-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--sf2);
    border: 1px solid var(--bd);
    color: var(--mute);
    transition: all 0.2s ease;
    cursor: pointer;
}
.v-action-btn:hover {
    transform: translateY(-2px);
    color: #fff;
}
.v-action-btn.primary:hover { background: var(--ac); border-color: var(--ac); }
.v-action-btn.success:hover { background: var(--ok); border-color: var(--ok); }
.v-action-btn.danger:hover  { background: var(--no); border-color: var(--no); }

/* Responsive */
@media (max-width: 1024px) {
    .vanguard-list-row {
        grid-template-columns: 1.5fr 1fr auto;
    }
    .v-list-contact {
        display: none;
    }
}
@media (max-width: 768px) {
    .vanguard-list-row {
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid var(--bd);
        margin-bottom: 12px;
    }
    .vanguard-list-row:last-child {
        border-bottom: 1px solid var(--bd);
    }
    .v-list-actions {
        justify-content: flex-end;
        border-top: 1px solid var(--bd);
        padding-top: 12px;
        margin-top: 4px;
    }
}
.search-box input {
    padding-inline-end: 14px !important;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); filter: blur(4px); }
    to { opacity: 1; transform: translateY(0); filter: blur(0); }
}

</style>


<div class="tab-navigation fade-up">
    <a href="?tab=users" class="tab-item <?= $activeTab === 'users' ? 'active' : '' ?>">
        <?= icon('users', 18) ?> کاربران ربات
        <span class="badge"><?= number_format($total) ?></span>
    </a>
    <?php if ($isSuperAdmin): ?>
    <a href="?tab=admins" class="tab-item <?= $activeTab === 'admins' ? 'active' : '' ?>">
        <?= icon('shield', 18) ?> ادمین‌های پنل
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

    <div class="vanguard-list-container">
        <?php if (empty($users)): ?>
            <div class="empty" style="padding: 64px 20px;">
                <svg class="ill" viewBox="0 0 200 160" fill="none">
                    <circle cx="100" cy="60" r="40" fill="var(--sf3)" />
                    <circle cx="100" cy="47" r="18" fill="var(--bds)" />
                    <path d="M62 105 Q100 88 138 105" stroke="var(--bds)" stroke-width="8" stroke-linecap="round" fill="none" />
                </svg>
                <p style="font-size: 1.1rem; font-weight: 600; color: var(--mute);"><?= $search ? $textbotlang['panel']['usersNoResultFound'] : $textbotlang['panel']['usersNoUserYet'] ?></p>
            </div>
        <?php else:
            $i = $offset + 1;
            $delay = 0;
            foreach ($users as $u):
                $agent = $u['agent'] ?? 'f';
                $isBlocked = ($u['User_Status'] ?? '') === 'block';
                $name = $u['namecustom'] ?? '';
                if ($name === 'none') $name = '';
                $uname = $u['username'] ?? '';
                if ($uname === 'none') $uname = '';
                $delay += 50; // Staggered entrance
                ?>
                <div class="vanguard-list-row" style="animation-delay: <?= $delay ?>ms;">
                    <!-- 1. User Meta -->
                    <div class="v-list-col v-list-meta">
                        <div class="vanguard-avatar">
                            <?= icon('user', 20) ?>
                        </div>
                        <div class="v-list-info">
                            <div class="v-list-name-row">
                                <?php if ($name): ?>
                                    <span class="v-list-name"><?= htmlspecialchars(trunc($name, 20)) ?></span>
                                <?php elseif ($uname): ?>
                                    <span class="v-list-name" style="direction:ltr; display:inline-block;">@<?= htmlspecialchars(trunc($uname, 20)) ?></span>
                                <?php else: ?>
                                    <span class="v-list-name">کاربر بدون نام</span>
                                <?php endif; ?>
                                
                                <?php if ($isBlocked): ?>
                                    <span class="tag tag-no" style="padding:2px 6px; font-size:0.65rem;">مسدود</span>
                                <?php else: ?>
                                    <span class="tag <?= $agent === 'n2' ? 'tag-warn' : ($agent === 'n' ? 'tag-info' : 'tag-ok') ?>" style="padding:2px 6px; font-size:0.65rem;"><?= user_role_label($agent) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="v-list-sub">
                                <?php if ($uname && $name): ?>
                                    <span class="v-list-uname">@<?= htmlspecialchars($uname) ?></span>
                                <?php endif; ?>
                                <span class="v-list-id" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($u['id']) ?>'); this.style.color='var(--ac)'; setTimeout(()=>this.style.color='var(--mute)', 1000);" title="کپی شناسه">
                                    ID: <?= htmlspecialchars($u['id']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Contact & Date -->
                    <div class="v-list-col v-list-contact">
                        <div class="v-list-item">
                            <span class="v-list-label"><?= icon('phone', 12) ?></span>
                            <span class="v-list-value cm" style="direction: ltr;"><?= (!empty($u['number']) && $u['number'] !== 'none') ? htmlspecialchars($u['number']) : '—' ?></span>
                        </div>
                        <div class="v-list-item">
                            <span class="v-list-label"><?= icon('calendar', 12) ?></span>
                            <span class="v-list-value"><?= safe_date($u['register'] ?? null) ?></span>
                        </div>
                    </div>

                    <!-- 3. Financial & Gamification -->
                    <div class="v-list-col v-list-fin">
                        <div class="v-list-item">
                            <span class="v-list-label" style="color:var(--ok)"><?= icon('wallet', 12) ?></span>
                            <span class="v-list-value cn" style="color:var(--ok); font-weight:600;"><?= number_format((int) ($u['Balance'] ?? 0)) ?> <small style="opacity:0.6; font-size:0.7em;">ت</small></span>
                        </div>
                        <div class="v-list-item">
                            <span class="v-list-label" style="color:var(--warn)"><?= icon('star', 12) ?></span>
                            <span class="v-list-value cn" style="color:var(--warn); font-weight:600;"><?= (int) ($u['score'] ?? 0) ?></span>
                        </div>
                    </div>

                    <!-- 4. Actions -->
                    <div class="v-list-actions">
                        <a href="user.php?id=<?= (int) $u['id'] ?>" class="v-action-btn primary" title="<?= $textbotlang['panel']['usersViewBtn'] ?>">
                            <?= icon('eye', 16) ?>
                        </a>
                        
                        <?php if ($isBlocked): ?>
                            <a href="user_action.php?action=unblock&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                class="v-action-btn success" title="<?= $textbotlang['panel']['usersUnblockBtn'] ?>"
                                data-confirm="<?= sprintf($textbotlang['panel']['usersConfirmUnblockUser'], $name, $u['id']) ?>">
                                <?= icon('check', 16) ?>
                            </a>
                        <?php else: ?>
                            <a href="user_action.php?action=block&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                class="v-action-btn danger" title="<?= $textbotlang['panel']['usersBlockBtn'] ?>"
                                data-confirm="<?= sprintf($textbotlang['panel']['usersConfirmBlockUser'], $name, $u['id']) ?>">
                                <?= icon('block', 16) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

