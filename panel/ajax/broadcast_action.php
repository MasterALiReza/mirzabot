<?php
try {
    require_once __DIR__ . '/../inc/config.php';
    require_auth();
    csrf_check_post();

    global $pdo;

    $info_path = __DIR__ . '/../../cronbot/info';
    $users_path = __DIR__ . '/../../cronbot/users.json';
    $cancel_path = __DIR__ . '/../../cronbot/cancel_broadcast';
    $history_id = null;
    $created_users_file = false;
    $created_info_file = false;

    function broadcast_feedback(string $type, string $message): void
    {
        echo '<div class="alert alert-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">' . $message . '</div>';
    }

    function ensure_broadcast_history_schema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS broadcast_history (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id VARCHAR(200) NULL,
            message_type VARCHAR(50) NOT NULL,
            content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            target_audience VARCHAR(100) NOT NULL,
            status VARCHAR(50) NOT NULL,
            created_at VARCHAR(50) NOT NULL,
            pin_message TINYINT(1) DEFAULT 0,
            button_type VARCHAR(50) DEFAULT NULL,
            button_text VARCHAR(100) DEFAULT NULL,
            button_data TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");

        try {
            $pdo->exec("ALTER TABLE broadcast_history MODIFY COLUMN button_data TEXT");
        } catch (PDOException $e) {}

        $columns = $pdo->query("SHOW COLUMNS FROM broadcast_history")->fetchAll(PDO::FETCH_COLUMN);
        $addColumn = function (string $name, string $definition) use ($pdo, $columns): void {
            if (!in_array($name, $columns, true)) {
                $pdo->exec("ALTER TABLE broadcast_history ADD COLUMN {$definition}");
            }
        };

        $addColumn('admin_id', 'admin_id VARCHAR(200) NULL AFTER id');
        $addColumn('pin_message', 'pin_message TINYINT(1) DEFAULT 0');
        $addColumn('button_type', 'button_type VARCHAR(50) DEFAULT NULL');
        $addColumn('button_text', 'button_text VARCHAR(100) DEFAULT NULL');
        $addColumn('button_data', 'button_data VARCHAR(255) DEFAULT NULL');
    }

    if (is_file($cancel_path) && !is_file($info_path) && !is_file($users_path)) {
        $cancel_age = time() - (int) @filemtime($cancel_path);
        if ($cancel_age < 30) {
            broadcast_feedback('warn', 'لغو عملیات در حال نهایی‌سازی است. چند ثانیه دیگر دوباره تلاش کنید.');
            exit;
        }
        @unlink($cancel_path);
    }

    if (is_file($info_path) || is_file($users_path)) {
        broadcast_feedback('warn', 'یک عملیات ارسال در حال اجراست. ابتدا باید آن را لغو کنید یا تا پایان آن منتظر بمانید.');
        exit;
    }

    $type = $_POST['type'] ?? 'sendmessage';
    $btnmessage = $_POST['btnmessage'] ?? 'none';
    $target_users = $_POST['target_users'] ?? 'all';
    $target_agent = $_POST['target_agent'] ?? 'all';
    $message = trim($_POST['message'] ?? '');
    $channel_link = trim($_POST['channel_link'] ?? '');
    $pingmessage = isset($_POST['pingmessage']) ? 'yes' : 'no';

    $allowed_types = ['sendmessage', 'forwardlink', 'unpinmessage'];
    $allowed_buttons = ['none', 'custom_url', 'custom_product', 'buy', 'start', 'usertestbtn', 'helpbtn', 'affiliatesbtn', 'addbalance'];
    $allowed_targets = ['all', 'customer', 'nonecustomer'];
    $allowed_agents = ['all', 'f', 'n', 'n2'];

    if (!in_array($type, $allowed_types, true)) {
        broadcast_feedback('warn', 'نوع عملیات نامعتبر است.');
        exit;
    }
    if (!in_array($btnmessage, $allowed_buttons, true)) {
        broadcast_feedback('warn', 'نوع دکمه انتخاب شده نامعتبر است.');
        exit;
    }
    if (!in_array($target_users, $allowed_targets, true) || !in_array($target_agent, $allowed_agents, true)) {
        broadcast_feedback('warn', 'جامعه هدف انتخاب شده معتبر نیست.');
        exit;
    }

    if ($type === 'unpinmessage') {
        $btnmessage = 'none';
        $pingmessage = 'no';
        $message = 'Unpin all pinned messages';
    }

    if ($type === 'sendmessage' && $message === '') {
        broadcast_feedback('warn', 'لطفا متن پیام را وارد کنید.');
        exit;
    }

    if ($type === 'forwardlink') {
        if ($channel_link === '') {
            broadcast_feedback('warn', 'لطفا لینک کانال را وارد کنید.');
            exit;
        }
        if (!preg_match('~^(?:https?://)?t\.me/(?:c/\d+|[a-zA-Z0-9_]+)/\d+(?:\?.*)?$~', $channel_link)) {
            broadcast_feedback('warn', 'فرمت لینک پست کانال معتبر نیست. نمونه: https://t.me/MyChannel/123');
            exit;
        }
        $message = $channel_link;
        
        // Test if the bot can actually copy this message using admin's chat id
        $admin_info = db_fetch($pdo, "SELECT id_admin FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
        $test_chat_id = $admin_info['id_admin'] ?? null;
        if ($test_chat_id) {
            $from_chat_id = '';
            $message_id = '';
            if (preg_match('/t\.me\/c\/(\d+)\/(\d+)/', $link ?? $channel_link, $matches)) {
                $from_chat_id = '-100' . $matches[1];
                $message_id = $matches[2];
            } elseif (preg_match('/t\.me\/([a-zA-Z0-9_]+)\/(\d+)/', $link ?? $channel_link, $matches)) {
                $from_chat_id = '@' . $matches[1];
                $message_id = $matches[2];
            }
            if ($from_chat_id && $message_id) {
                require_once __DIR__ . '/../../botapi.php';
                $test_res = telegram('copyMessage', [
                    'chat_id' => $test_chat_id,
                    'from_chat_id' => $from_chat_id,
                    'message_id' => $message_id
                ]);
                if (isset($test_res['ok']) && !$test_res['ok']) {
                    $err = $test_res['description'] ?? 'Unknown error';
                    broadcast_feedback('warn', "خطا در دسترسی به پیام کانال: ربات قادر به کپی پیام نیست. مطمئن شوید ربات در کانال عضو است. خطای تلگرام: $err");
                    exit;
                }
            }
        }
    }

    $custom_btn_text_prod = trim($_POST['custom_btn_text_prod'] ?? '');
    $custom_btn_callback = trim($_POST['custom_btn_callback'] ?? '');
    $button_type = $btnmessage;
    $button_text = null;
    $button_data = null;

    if ($btnmessage === 'custom_url') {
        $texts = $_POST['custom_btn_text_url'] ?? [];
        $links = $_POST['custom_btn_link'] ?? [];
        $colors = $_POST['custom_btn_color'] ?? [];
        
        $buttonsArray = [];
        if (is_array($texts) && is_array($links)) {
            for ($i = 0; $i < count($texts); $i++) {
                $t = trim($texts[$i] ?? '');
                $l = trim($links[$i] ?? '');
                $c = trim($colors[$i] ?? 'default');
                if ($t !== '' && $l !== '') {
                    $buttonsArray[] = ['text' => $t, 'url' => $l, 'color' => $c];
                }
            }
        }
        
        if (empty($buttonsArray)) {
            broadcast_feedback('warn', 'لطفا متن و لینک دکمه را وارد کنید.');
            exit;
        }
        
        $button_type = 'custom_url_dynamic';
        $button_text = 'Dynamic Buttons';
        $button_data = json_encode($buttonsArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } elseif ($btnmessage === 'custom_product') {
        if ($custom_btn_text_prod === '' || $custom_btn_callback === '') {
            broadcast_feedback('warn', 'برای دکمه محصول/دسته، متن دکمه و مقصد را انتخاب کنید.');
            exit;
        }
        $button_text = $custom_btn_text_prod;
        $button_data = $custom_btn_callback;
    }

    $admin = db_fetch($pdo, "SELECT id_admin FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
    $id_admin = $admin['id_admin'] ?? 1;

    $where = [];
    $params = [];

    if ($target_agent !== 'all') {
        $where[] = "agent = ?";
        $params[] = $target_agent;
    }

    if ($target_users === 'customer') {
        $where[] = "id IN (SELECT id_user FROM invoice)";
    } elseif ($target_users === 'nonecustomer') {
        $where[] = "id NOT IN (SELECT id_user FROM invoice)";
    }

    $sql = "SELECT id FROM user";
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $users = db_fetchAll($pdo, $sql, $params);
    if (!$users && $type !== 'unpinmessage') {
        broadcast_feedback('warn', 'هیچ کاربری با این شرایط یافت نشد.');
        exit;
    }

    $admin_ids = [];
    $admin_rows = db_fetchAll($pdo, "SELECT id_admin FROM admin");
    foreach ($admin_rows as $row) {
        if (!empty($row['id_admin'])) {
            $admin_ids[] = (string) $row['id_admin'];
        }
    }
    global $adminnumber;
    if (isset($adminnumber) && $adminnumber !== '') {
        $admin_ids[] = (string) $adminnumber;
    }

    $all_ids = [];
    foreach ($users as $u) {
        if (!empty($u['id'])) {
            $all_ids[] = (string) $u['id'];
        }
    }
    $all_ids = array_values(array_unique(array_filter(array_merge($all_ids, $admin_ids))));

    if (!$all_ids) {
        broadcast_feedback('warn', 'هیچ کاربری برای اجرای عملیات یافت نشد.');
        exit;
    }

    $formatted_users = array_map(static fn(string $id): array => ['id' => $id], $all_ids);

    $info = [
        'id_admin' => $id_admin,
        'id_message' => 0,
        'type' => $type,
        'message' => $message,
        'pingmessage' => $pingmessage,
        'btnmessage' => $button_type,
    ];
    if ($button_type === 'custom_url_dynamic') {
        $info['custom_btn_dynamic'] = $button_data;
    } elseif ($button_type === 'custom_product') {
        $info['custom_btn_text_prod'] = $button_text;
        $info['custom_btn_callback'] = $button_data;
    }

    ensure_broadcast_history_schema($pdo);

    $msg_type_db = ($type === 'forwardlink') ? 'forwardlink' : (($type === 'sendmessage') ? 'text' : 'unpin');

    $hist_stmt = $pdo->prepare("INSERT INTO broadcast_history (admin_id, message_type, content, target_audience, status, created_at, pin_message, button_type, button_text, button_data) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");
    $hist_stmt->execute([
        $id_admin,
        $msg_type_db,
        $message,
        $target_users,
        time(),
        $pingmessage === 'yes' ? 1 : 0,
        $button_type,
        $button_text,
        $button_data,
    ]);
    $history_id = $pdo->lastInsertId();

    $users_json = json_encode($formatted_users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $info_json = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($users_json === false || $info_json === false) {
        throw new RuntimeException('Unable to encode broadcast payload.');
    }

    if (file_put_contents($users_path, $users_json, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write cronbot/users.json.');
    }
    $created_users_file = true;
    if (file_put_contents($info_path, $info_json, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write cronbot/info.');
    }
    $created_info_file = true;

    broadcast_feedback('success', 'عملیات با موفقیت تنظیم شد و در پس‌زمینه ارسال خواهد شد. ' . count($formatted_users) . ' کاربر هدف‌گذاری شدند.');
    echo '<script>setTimeout(() => window.location.reload(), 2500);</script>';
} catch (\Throwable $e) {
    if (isset($history_id) && $history_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM broadcast_history WHERE id = ?");
            $stmt->execute([$history_id]);
        } catch (\Throwable $ignored) {
        }
    }
    if (!empty($created_info_file)) {
        @unlink(__DIR__ . '/../../cronbot/info');
    }
    if (!empty($created_users_file)) {
        @unlink(__DIR__ . '/../../cronbot/users.json');
    }

    echo '<div class="alert alert-danger" style="background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; color: #e74c3c; padding: 20px; border-radius: 12px; margin-bottom: 20px;">';
    echo '<strong>خطا در اجرای عملیات:</strong><br>';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '<br>';
    echo '<small>در فایل: ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ' خط ' . (int) $e->getLine() . '</small>';
    echo '</div>';
    exit;
}
