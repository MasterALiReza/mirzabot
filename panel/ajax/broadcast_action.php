<?php
try {
    require_once __DIR__ . '/../inc/config.php';
    require_auth();
    csrf_check_post();

    global $pdo;

    $info_path = __DIR__ . '/../../cronbot/info';
    $users_path = __DIR__ . '/../../cronbot/users.json';

    if (is_file($info_path) || is_file($users_path)) {
        echo '<div class="alert alert-warn">یک عملیات ارسال در حال اجراست. ابتدا باید آن را لغو کنید یا تا پایان آن منتظر بمانید.</div>';
        exit;
    }

    $type = $_POST['type'] ?? 'sendmessage';
    $btnmessage = $_POST['btnmessage'] ?? 'none';
    $target_users = $_POST['target_users'] ?? 'all';
    $target_agent = $_POST['target_agent'] ?? 'all';
    $message = trim($_POST['message'] ?? '');
    $channel_link = trim($_POST['channel_link'] ?? '');
    $pingmessage = isset($_POST['pingmessage']) ? 'yes' : 'no';

    if ($type === 'sendmessage' && empty($message)) {
        echo '<div class="alert alert-warn">لطفا متن پیام را وارد کنید.</div>';
        exit;
    }

    if ($type === 'forwardlink' && empty($channel_link)) {
        echo '<div class="alert alert-warn">لطفا لینک کانال را وارد کنید.</div>';
        exit;
    }

    if ($type === 'forwardlink') {
        $message = $channel_link;
    }

    // Fetch admin id
    $admin = db_fetch($pdo, "SELECT id_admin FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
    $id_admin = $admin['id_admin'] ?? 1;

    // Build query
    $where = [];
    $params = [];

    if ($target_agent !== 'all') {
        $where[] = "agent = ?";
        $params[] = $target_agent;
    }

    // target_users filtering (like in admin.php)
    if ($target_users === 'customer') {
        $where[] = "id IN (SELECT id_user FROM invoice)";
    } elseif ($target_users === 'nonecustomer') {
        $where[] = "id NOT IN (SELECT id_user FROM invoice)";
    }

    $sql = "SELECT id FROM user";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $users = db_fetchAll($pdo, $sql, $params);
    if (empty($users) && $type !== 'unpinmessage') {
        echo '<div class="alert alert-warn">هیچ کاربری با این شرایط یافت نشد.</div>';
        exit;
    }

    // Fetch admin ids & owner to merge into the broadcast target list
    $admin_ids = [];
    $admin_rows = db_fetchAll($pdo, "SELECT id_admin FROM admin");
    foreach ($admin_rows as $row) {
        if (!empty($row['id_admin'])) {
            $admin_ids[] = (string)$row['id_admin'];
        }
    }
    global $adminnumber;
    if (isset($adminnumber) && $adminnumber !== '') {
        $admin_ids[] = (string)$adminnumber;
    }

    $all_ids = [];
    foreach ($users as $u) {
        if (!empty($u['id'])) {
            $all_ids[] = (string)$u['id'];
        }
    }

    // Merge and deduplicate
    $all_ids = array_merge($all_ids, $admin_ids);
    $all_ids = array_values(array_unique(array_filter($all_ids)));

    // Format exactly as the cron expects: an array of objects/arrays with 'id' property.
    $formatted_users = [];
    foreach ($all_ids as $id) {
        $formatted_users[] = ['id' => $id];
    }

    $custom_btn_text_url = trim($_POST['custom_btn_text_url'] ?? '');
    $custom_btn_link = trim($_POST['custom_btn_link'] ?? '');
    $custom_btn_text_prod = trim($_POST['custom_btn_text_prod'] ?? '');
    $custom_btn_callback = trim($_POST['custom_btn_callback'] ?? '');

    $info = [
        'id_admin' => $id_admin,
        'id_message' => 0, // Panel doesn't have a specific telegram message to edit for progress
        'type' => $type,
        'message' => $message,
        'pingmessage' => $pingmessage,
        'btnmessage' => $btnmessage,
        'custom_btn_text_url' => $custom_btn_text_url,
        'custom_btn_link' => $custom_btn_link,
        'custom_btn_text_prod' => $custom_btn_text_prod,
        'custom_btn_callback' => $custom_btn_callback
    ];

    file_put_contents($users_path, json_encode($formatted_users));
    file_put_contents($info_path, json_encode($info));

    // Ensure columns exist for new button types
    try { $pdo->exec("ALTER TABLE broadcast_history ADD COLUMN pin_message TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE broadcast_history ADD COLUMN button_type VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE broadcast_history ADD COLUMN button_text VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE broadcast_history ADD COLUMN button_data VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}

    // Log into broadcast_history
    $msg_type_db = ($type === 'forwardlink') ? 'forwardlink' : (($type === 'sendmessage') ? 'text' : 'unpin');
    if ($msg_type_db !== 'unpin') {
        $btn_txt = null;
        $btn_dat = null;
        if ($btnmessage === 'custom_url') {
            $btn_txt = $custom_btn_text_url;
            $btn_dat = $custom_btn_link;
        } elseif ($btnmessage === 'custom_product') {
            $btn_txt = $custom_btn_text_prod;
            $btn_dat = $custom_btn_callback;
        }
        
        $hist_stmt = $pdo->prepare("INSERT INTO broadcast_history (admin_id, message_type, content, target_audience, status, created_at, pin_message, button_type, button_text, button_data) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");
        $hist_stmt->execute([
            $id_admin,
            $msg_type_db,
            $message,
            $target_users,
            time(),
            $pingmessage === 'yes' ? 1 : 0,
            $btnmessage,
            $btn_txt,
            $btn_dat
        ]);
    }

    echo '<div class="alert alert-success">عملیات با موفقیت تنظیم شد و در پس‌زمینه ارسال خواهد شد. ' . count($formatted_users) . ' کاربر هدف‌گذاری شدند.</div>';
    echo '<script>setTimeout(() => window.location.reload(), 2500);</script>';

} catch (\Throwable $e) {
    echo '<div class="alert alert-danger" style="background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; color: #e74c3c; padding: 20px; border-radius: 12px; margin-bottom: 20px;">';
    echo '<strong>خطا در اجرای عملیات:</strong><br>';
    echo htmlspecialchars($e->getMessage()) . '<br>';
    echo '<small>در فایل: ' . htmlspecialchars($e->getFile()) . ' خط ' . $e->getLine() . '</small>';
    echo '</div>';
    exit;
}
