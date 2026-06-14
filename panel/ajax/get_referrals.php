<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/icons.php';
require_auth();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    die('شناسه کاربر نامعتبر است.');
}

try {
    $referrals = db_fetchAll($pdo, "SELECT id, username, namecustom, Balance, register, agent FROM user WHERE affiliates = ? ORDER BY register DESC", [$id]);

    if (empty($referrals)) {
        echo '<div class="empty" style="padding: 20px; text-align: center; color: var(--mute);">';
        echo '<p>هیچ زیرمجموعه‌ای یافت نشد.</p>';
        echo '</div>';
        exit;
    }
    ?>
    <div class="tbl-wrap dash-users" style="margin: 10px 0; background: var(--sf2); border: 1px solid var(--bd); border-radius: 8px; padding: 8px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
        <h4 style="margin: 4px 8px 12px; font-size: 0.85rem; color: var(--mute); display: flex; align-items: center; gap: 6px;">
            <?= icon('users', 14) ?> لیست زیرمجموعه‌ها
        </h4>
        <table class="tbl-md">
            <thead>
                <tr>
                    <th style="text-align: right;">کاربر</th>
                    <th style="text-align: right;">موجودی</th>
                    <th style="text-align: right;">گروه</th>
                    <th style="text-align: right;">تاریخ عضویت</th>
                    <th style="text-align: center;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($referrals as $ref):
                    $refName = $ref['namecustom'] ?? '';
                    if ($refName === 'none') $refName = '';
                    $refUname = $ref['username'] ?? '';
                    if ($refUname === 'none' || $refUname === 'NOT_USERNAME') $refUname = '';
                    $refAgent = $ref['agent'] ?? 'f';
                    ?>
                    <tr style="border-bottom: 1px solid var(--bd);">
                        <td data-label="کاربر" style="text-align: right;">
                            <div class="dash-unified-content" style="align-items: center; display: flex; gap: 8px;">
                                <div class="profile-avatar" style="width: 32px; height: 32px; font-size: 14px; display: flex; align-items: center; justify-content: center; background: var(--sf3); border-radius: 50%;">
                                    <?= mb_substr($refName ?: ($refUname ?: $ref['id']), 0, 1) ?>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <a href="user.php?id=<?= (int)$ref['id'] ?>" class="cm" style="color: var(--text); font-weight: 600; text-decoration: none;">
                                        <?= htmlspecialchars($refName ?: ($refUname ? '@' . $refUname : 'کاربر بی‌نام')) ?>
                                    </a>
                                    <div class="profile-id-box" style="font-size: 0.75rem; color: var(--mute); margin: 0; display: flex; align-items: center; gap: 2px;">
                                        <span style="color: var(--ac);"><?= icon('hash', 10) ?></span>
                                        <?= htmlspecialchars($ref['id']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="موجودی" style="text-align: right;">
                            <span class="cn" style="font-weight: 600; color: var(--text);">
                                <?= number_format((int)($ref['Balance'] ?? 0)) ?>
                                <span class="cf" style="font-size: 0.75rem; color: var(--mute);">تومان</span>
                            </span>
                        </td>
                        <td data-label="گروه" style="text-align: right;">
                            <span class="tag <?= user_role_tag($refAgent) ?>">
                                <?= user_role_label($refAgent) ?>
                            </span>
                        </td>
                        <td data-label="تاریخ" style="text-align: right; color: var(--mute);">
                            <?= safe_date($ref['register'] ?? null, 'Y/m/d H:i') ?>
                        </td>
                        <td data-label="عملیات" style="text-align: center;">
                            <a href="user_action.php?action=remove_single_affiliate&id=<?= (int)$ref['id'] ?>&_csrf=<?= csrf_token() ?>&back=affiliates.php"
                               class="btn btn-no btn-sm"
                               style="padding: 4px 10px; font-size: 0.75rem; font-weight: 600;"
                               data-confirm="آیا مطمئن هستید که می‌خواهید این کاربر را از زیرمجموعه بودن خارج کنید؟"
                               title="لغو زیرمجموعگی">
                                <?= icon('user-x', 12) ?> لغو زیرمجموعگی
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
} catch (Exception $e) {
    http_response_code(500);
    error_log('get_referrals error: ' . $e->getMessage());
    echo '<div style="padding: 20px; text-align: center; color: var(--red);">';
    echo 'خطا در بارگذاری اطلاعات زیرمجموعه‌ها.';
    echo '</div>';
}
