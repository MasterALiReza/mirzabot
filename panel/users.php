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

/* Double-Bezel Shell */
.vanguard-shell {
    background: var(--sf);
    border: 1px solid var(--bd);
    border-radius: 2rem;
    padding: 6px;
    transition: all 700ms cubic-bezier(0.32, 0.72, 0, 1);
    animation: fadeUp 800ms cubic-bezier(0.32, 0.72, 0, 1) both;
}

.vanguard-shell:hover {
    border-color: var(--bds);
    transform: translateY(-2px);
    box-shadow: var(--shlg);
}

/* Inner Core */
.vanguard-core {
    background: var(--bg);
    border-radius: calc(2rem - 6px);
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    padding: 24px 32px;
    display: grid;
    grid-template-columns: 2fr 3fr 1.5fr;
    gap: 24px;
    align-items: center;
    position: relative;
    overflow: hidden;
}

@media (max-width: 1024px) {
    .vanguard-core {
        grid-template-columns: 1fr 1fr;
        padding: 20px;
    }
}

@media (max-width: 768px) {
    .vanguard-shell {
        border-radius: 1.5rem;
        padding: 4px;
    }
    .vanguard-core {
        grid-template-columns: 1fr;
        border-radius: calc(1.5rem - 4px);
        padding: 16px;
        gap: 16px;
    }
}

/* Typography & Layouts */
.vanguard-user-meta {
    display: flex;
    align-items: center;
    gap: 16px;
}

.vanguard-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--acs);
    border: 1px solid var(--acg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ac);
    flex-shrink: 0;
}

.vanguard-name-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.vanguard-name {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text);
    letter-spacing: -0.01em;
}

.vanguard-username {
    color: var(--mute);
    font-size: 0.85rem;
    direction: ltr;
    display: inline-block;
    font-weight: 500;
}

.vanguard-id-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--sf2);
    border: 1px solid var(--bd);
    padding: 4px 10px;
    border-radius: 100px;
    font-size: 0.75rem;
    color: var(--mute);
    cursor: pointer;
    transition: all 0.3s ease;
}
.vanguard-id-pill:hover {
    background: var(--acs);
    color: var(--ac);
    border-color: var(--acg);
}

/* Grid for Details */
.vanguard-details-bento {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

@media (max-width: 1200px) {
    .vanguard-details-bento {
        grid-template-columns: repeat(2, 1fr);
    }
}

.vanguard-bento-box {
    background: var(--sf2);
    border: 1px solid var(--bd);
    border-radius: 1rem;
    padding: 18px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-align: center;
    transition: all 400ms cubic-bezier(0.32, 0.72, 0, 1);
}
.vanguard-bento-box:hover {
    background: var(--sf3);
    border-color: var(--bds);
    transform: translateY(-2px);
}

.vanguard-bento-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--mute);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.vanguard-bento-value {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text);
}

/* Button-in-Button Actions */
.vanguard-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
}
@media (max-width: 1024px) {
    .vanguard-actions {
        flex-direction: row;
        align-items: center;
        grid-column: span 2;
        justify-content: flex-end;
    }
}
@media (max-width: 768px) {
    .vanguard-actions {
        grid-column: span 1;
        justify-content: flex-start;
        flex-wrap: wrap;
    }
}

.btn-vanguard {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: var(--sf2);
    border: 1px solid var(--bd);
    color: var(--text);
    border-radius: 100px;
    padding: 6px 6px 6px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 500ms cubic-bezier(0.32, 0.72, 0, 1);
    cursor: pointer;
}
.btn-vanguard:active {
    transform: scale(0.97);
}

.btn-vanguard .v-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--bd);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 400ms cubic-bezier(0.32, 0.72, 0, 1);
}

.btn-vanguard.v-primary:hover {
    background: var(--ac);
    border-color: var(--ac);
    color: var(--btn-ac-text, #fff);
}
.btn-vanguard.v-primary:hover .v-icon-wrap {
    background: rgba(0,0,0,0.15);
    transform: translateX(-4px) scale(1.05); /* LTR translate because RTL layout translates differently, let's test */
}
.btn-vanguard.v-danger:hover {
    background: var(--no);
    border-color: var(--no);
    color: var(--btn-no-text, #fff);
}
.btn-vanguard.v-danger:hover .v-icon-wrap {
    background: rgba(0,0,0,0.15);
    transform: translateX(-4px) scale(1.05);
}
.btn-vanguard.v-success:hover {
    background: var(--ok);
    border-color: var(--ok);
    color: var(--btn-ok-text, #fff);
}
.btn-vanguard.v-success:hover .v-icon-wrap {
    background: rgba(0,0,0,0.15);
    transform: translateX(-4px) scale(1.05);
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
                <div class="vanguard-shell" style="animation-delay: <?= $delay ?>ms;">
                    <div class="vanguard-core">
                        
                        <!-- 1. User Meta -->
                        <div class="vanguard-user-meta">
                            <div class="vanguard-avatar">
                                <?= icon('user', 24) ?>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                <div class="vanguard-name-row">
                                    <div class="vanguard-name">
                                        <?php if ($name): ?>
                                            <?= htmlspecialchars(trunc($name, 20)) ?>
                                        <?php elseif ($uname): ?>
                                            <span style="direction:ltr; display:inline-block;">@<?= htmlspecialchars(trunc($uname, 20)) ?></span>
                                        <?php else: ?>
                                            کاربر بدون نام
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isBlocked): ?>
                                        <span class="tag tag-no">مسدود</span>
                                    <?php else: ?>
                                        <span class="tag <?= $agent === 'n2' ? 'tag-warn' : ($agent === 'n' ? 'tag-info' : 'tag-ok') ?>"><?= user_role_label($agent) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($uname && $name): ?>
                                    <div class="vanguard-username">@<?= htmlspecialchars($uname) ?></div>
                                <?php endif; ?>
                                <div style="margin-top:4px;">
                                    <span class="vanguard-id-pill" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($u['id']) ?>'); this.style.color='var(--ac)'; setTimeout(()=>this.style.color='var(--mute)', 1000);" title="کپی شناسه">
                                        <?= icon('copy', 12) ?> <?= htmlspecialchars($u['id']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Details Bento Grid -->
                        <div class="vanguard-details-bento">
                            <div class="vanguard-bento-box">
                                <span class="vanguard-bento-label"><?= icon('phone', 12) ?> تماس</span>
                                <span class="vanguard-bento-value cm" style="font-size:0.95rem;"><?= (!empty($u['number']) && $u['number'] !== 'none') ? htmlspecialchars($u['number']) : '—' ?></span>
                            </div>
                            <div class="vanguard-bento-box">
                                <span class="vanguard-bento-label"><?= icon('calendar', 12) ?> عضویت</span>
                                <span class="vanguard-bento-value" style="font-size:0.9rem;"><?= safe_date($u['register'] ?? null) ?></span>
                            </div>
                            <div class="vanguard-bento-box" style="background: var(--oks); border-color: rgba(34,197,94,0.2);">
                                <span class="vanguard-bento-label" style="color: var(--ok);"><?= icon('wallet', 12) ?> کیف پول</span>
                                <span class="vanguard-bento-value cn" style="color: var(--ok); font-size: 1.15rem;"><?= number_format((int) ($u['Balance'] ?? 0)) ?> <span style="font-size:0.7rem;opacity:0.7">ت</span></span>
                            </div>
                            <div class="vanguard-bento-box" style="background: var(--warns); border-color: rgba(251,183,64,0.2);">
                                <span class="vanguard-bento-label" style="color: var(--warn);"><?= icon('star', 12) ?> امتیاز</span>
                                <span class="vanguard-bento-value cn" style="color: var(--warn); font-size: 1.15rem;"><?= (int) ($u['score'] ?? 0) ?></span>
                            </div>
                        </div>

                        <!-- 3. Actions -->
                        <div class="vanguard-actions">
                            <a href="user.php?id=<?= (int) $u['id'] ?>" class="btn-vanguard v-primary" title="<?= $textbotlang['panel']['usersViewBtn'] ?>">
                                <span>ویرایش کاربر</span>
                                <div class="v-icon-wrap"><?= icon('eye', 14) ?></div>
                            </a>
                            
                            <?php if ($isBlocked): ?>
                                <a href="user_action.php?action=unblock&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                    class="btn-vanguard v-success" title="<?= $textbotlang['panel']['usersUnblockBtn'] ?>"
                                    data-confirm="<?= sprintf($textbotlang['panel']['usersConfirmUnblockUser'], $name, $u['id']) ?>">
                                    <span>آزادسازی</span>
                                    <div class="v-icon-wrap"><?= icon('check', 14) ?></div>
                                </a>
                            <?php else: ?>
                                <a href="user_action.php?action=block&id=<?= (int) $u['id'] ?>&_csrf=<?= csrf_token() ?>&back=users.php"
                                    class="btn-vanguard v-danger" title="<?= $textbotlang['panel']['usersBlockBtn'] ?>"
                                    data-confirm="<?= sprintf($textbotlang['panel']['usersConfirmBlockUser'], $name, $u['id']) ?>">
                                    <span>مسدود کردن</span>
                                    <div class="v-icon-wrap"><?= icon('block', 14) ?></div>
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; endif; ?>
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
                <?= icon('plus', 14) ?> افزودن ادمین
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

    <div class="tbl-wrap dash-unified">
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
                                    <span class="cf">شناسه کاربر :</span>
                                    <span class="cm" style="font-weight:600; font-size:0.85rem; direction:ltr; display:inline-block;"><?= htmlspecialchars($ad['id_admin']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td data-label="دسترسی و وضعیت">
                            <div class="dash-unified-content">
                                <span class="mobile-label">دسترسی و وضعیت:</span>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="status-pill <?= $rc['color'] === '#22c55e' ? 'success' : ($rc['color'] === '#f59e0b' ? 'warning' : 'info') ?>"><?= $rc['label'] ?></span>
                                    <?php if ($isMe): ?>
                                        <span class="status-pill success" style="opacity:0.8; padding: 3px 8px;">آنلاین</span>
                                    <?php else: ?>
                                        <span class="status-pill neutral" style="opacity:0.8; padding: 3px 8px;">فعال</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td data-label="عملیات">
                            <div class="dash-unified-content">
                                <span class="mobile-label">عملیات:</span>
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
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="field full">
                        <label>شناسه عددی کاربر (Telegram ID) <span style="color:var(--accent)">*</span></label>
                        <div class="input-with-icon">
                            <?= icon('hash', 18) ?>
                            <input type="number" name="id_admin" id="add-id" class="input" placeholder="مثال: 12345678" required>
                        </div>
                        <small style="color:var(--mute);margin-top:4px;display:block">شناسه عددی یکتای کاربر در ربات (جهت یکپارچگی ادمین پنل با ربات)</small>
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
        <form method="POST" action="">
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
                        <label>شناسه کاربر</label>
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
<form method="POST" action="" id="delete-form" style="display:none">
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
