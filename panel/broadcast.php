<?php
require 'inc/config.php';
require_auth();

$title = 'ارسال پیام همگانی';
require 'inc/layout_head.php';

// Fetch Broadcast History
$history_stmt = $pdo->prepare("SELECT * FROM broadcast_history ORDER BY id DESC LIMIT 10");
$history_stmt->execute();
$histories = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories and Products for inline buttons
$categories_stmt = $pdo->query("SELECT * FROM category");
$categories = $categories_stmt ? $categories_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$products_stmt = $pdo->query("SELECT * FROM product");
$products = $products_stmt ? $products_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

?>

<style>
/* Mission Control Aesthetic for Broadcast */
.broadcast-dashboard {
    max-width: 880px;
    margin: 0 auto;
}
.truncate-td {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.truncate-text {
    display: inline-block;
    text-align: right;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 768px) {
    .truncate-td {
        max-width: 100% !important;
    }
}
.bc-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--bd);
}

.bc-header h2 {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 12px;
}

.bc-header p {
    color: var(--dim);
    font-size: 0.95rem;
    margin-top: 8px;
}

.bc-alert {
    background: var(--warns);
    border: 1px solid var(--warn);
    padding: 20px 24px;
    border-radius: 16px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 20px;
    color: var(--text);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    animation: pulse 2s infinite ease-in-out;
}

.bc-alert-icon {
    width: 54px;
    height: 54px;
    background: var(--warn);
    color: #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.bc-alert-content h4 {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--warn);
    margin-bottom: 6px;
}

.bc-alert-content p {
    font-size: 0.85rem;
    color: var(--text2);
    margin: 0;
}

.bc-section {
    background: var(--sf2);
    border: 1px solid var(--bd);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    transition: all var(--tf);
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
}

.bc-section:hover {
    border-color: var(--bds);
    box-shadow: 0 8px 32px rgba(0,0,0,0.06);
}

.bc-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.bc-section-title svg {
    color: var(--ac);
}

.bc-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.bc-checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--sf);
    border: 1.5px solid var(--bd);
    border-radius: 14px;
    cursor: pointer;
    transition: all var(--tf);
}

.bc-checkbox-wrapper:hover {
    border-color: var(--ac);
    background: var(--sf3);
    box-shadow: 0 0 16px var(--acs);
}

.bc-checkbox-wrapper input[type="checkbox"] {
    margin: 0;
    accent-color: var(--ac);
    width: 22px;
    height: 22px;
    cursor: pointer;
    flex-shrink: 0;
}

.bc-checkbox-text strong {
    display: block;
    font-size: 0.95rem;
    color: var(--text);
    margin-bottom: 6px;
}

.bc-checkbox-text small {
    color: var(--dim);
    font-size: 0.8rem;
    line-height: 1.6;
    display: block;
}

.bc-submit {
    display: flex;
    justify-content: flex-start;
    margin-top: 36px;
}

.bc-submit .btn {
    padding: 16px 36px;
    font-size: 1.05rem;
    font-weight: 700;
    border-radius: 12px;
    box-shadow: 0 4px 16px var(--acs);
    transition: all var(--tf);
}

.bc-submit .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px var(--acs);
}

/* History Table Styles */
.bc-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: bold;
}

.badge-text { background: var(--acs); color: var(--ac); }
.badge-link { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
.badge-audience { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }

/* Mobile Adjustments */
@media (max-width: 768px) {
    .bc-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .bc-section {
        padding: 16px;
    }
    
    .bc-alert {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    
    .bc-submit .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="broadcast-dashboard fade-in">
    <div class="bc-header">
        <h2><?= icon('radio', 24) ?> ایستگاه پیام‌رسانی (Broadcast)</h2>
        <p>در این بخش می‌توانید به صورت همگانی برای گروه‌های مختلف کاربری ربات، پیام متنی ارسال کنید، از لینک کانال پیام را کپی کنید یا پیام‌های قبلی را از حالت پین خارج کنید.</p>
    </div>

    <?php if (is_file('../cronbot/info') || is_file('../cronbot/users.json')): ?>
        <div class="bc-alert">
            <div class="bc-alert-icon">
                <?= icon('alert-triangle', 24) ?>
            </div>
            <div class="bc-alert-content">
                <h4>عملیات در جریان است!</h4>
                <p>در حال حاضر یک عملیات ارسال پیام در سرور ربات در حال انجام است. برای جلوگیری از تداخل، لطفاً تا پایان آن صبر کنید.</p>
            </div>
        </div>
    <?php endif; ?>

    <form hx-post="ajax/broadcast_action.php" hx-swap="outerHTML" hx-indicator=".loader" id="broadcastForm">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        
        <!-- Section 1: Configuration -->
        <div class="bc-section">
            <div class="bc-section-title">
                <?= icon('settings', 18) ?> پیکربندی عملیات
            </div>
            <div class="bc-grid">
                <div class="field">
                    <label class="label">نوع فرمان</label>
                    <select class="input select" name="type" id="messageType" onchange="toggleFields()">
                        <option value="sendmessage">ارسال پیام متنی جدید</option>
                        <option value="forwardlink">ارسال با لینک پست کانال</option>
                        <option value="unpinmessage">حذف پین تمامی پیام‌ها (Unpin)</option>
                    </select>
                </div>
                
                <div class="field">
                    <label class="label">دکمه شیشه‌ای (اختیاری)</label>
                    <select class="input select" name="btnmessage" id="btnmessage" onchange="toggleBtnFields()">
                        <option value="none">بدون دکمه</option>
                        <option value="custom_url">لینک شخصی (آدرس اینترنتی)</option>
                        <option value="custom_product">لینک به دسته / محصول در ربات</option>
                        <optgroup label="دکمه‌های آماده ربات">
                            <option value="buy">دکمه خرید سرویس (فروشگاه)</option>
                            <option value="start">دکمه شروع مجدد ربات</option>
                            <option value="usertestbtn">دکمه دریافت حساب تست</option>
                            <option value="helpbtn">دکمه راهنما و پشتیبانی</option>
                            <option value="affiliatesbtn">دکمه سیستم همکاری در فروش</option>
                            <option value="addbalance">دکمه افزایش موجودی کیف پول</option>
                        </optgroup>
                    </select>
                </div>

                <div class="field" id="customUrlFields" style="display: none;">
                    <label class="label">متن و آدرس دکمه شخصی</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" class="input" name="custom_btn_text_url" id="customBtnTextUrl" placeholder="متن (مثال: کانال ما)" style="flex: 1;">
                        <input type="url" class="input" name="custom_btn_link" id="customBtnLink" placeholder="لینک (مثال: https://t.me/)" dir="ltr" style="flex: 2;">
                    </div>
                </div>

                <div class="field" id="customProductFields" style="display: none;">
                    <label class="label">متن دکمه و انتخاب محصول/دسته</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" class="input" name="custom_btn_text_prod" id="customBtnTextProd" placeholder="متن (مثال: تخفیف ویژه 1 ماهه)" style="flex: 1;">
                        <select class="input select" name="custom_btn_callback" id="customBtnCallback" style="flex: 2;">
                            <optgroup label="دسته‌بندی‌ها">
                                <?php foreach($categories as $cat): ?>
                                    <option value="categorynames_<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['remark'] ?? 'بدون نام') ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="محصولات">
                                <?php foreach($products as $prod): ?>
                                    <option value="prodcutservices_<?= htmlspecialchars($prod['id']) ?>"><?= htmlspecialchars($prod['name_product'] ?? 'بدون نام') ?> (<?= htmlspecialchars($prod['Location'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Targeting -->
        <div class="bc-section">
            <div class="bc-section-title">
                <?= icon('users', 18) ?> جامعه هدف (Targeting)
            </div>
            <div class="bc-grid">
                <div class="field">
                    <label class="label">بر اساس وضعیت اشتراک</label>
                    <select class="input select" name="target_users" id="targetUsers">
                        <option value="all">همه کاربران ربات</option>
                        <option value="customer">فقط مشتریان (دارای سرویس فعال)</option>
                        <option value="nonecustomer">فقط کاربران عادی (بدون سرویس)</option>
                    </select>
                </div>

                <div class="field">
                    <label class="label">بر اساس سطح دسترسی (نقش)</label>
                    <select class="input select" name="target_agent" id="targetAgent">
                        <option value="all">تمام نقش‌ها</option>
                        <option value="f">کاربران عادی</option>
                        <option value="n">نمایندگان فروش</option>
                        <option value="n2">نمایندگان ویژه (VIP)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section 3: Content -->
        <div class="bc-section" id="messageGroup">
            <div class="bc-section-title">
                <?= icon('message-square', 18) ?> محتوای پیام
            </div>
            
            <div class="field" id="textGroup" style="margin-bottom: 20px;">
                <label class="label">متن پیام (پشتیبانی کامل از HTML و استایل‌های تلگرام)</label>
                <textarea class="input textarea" name="message" id="messageText" rows="7" placeholder="متن پیام جذاب و اطلاع‌رسانی خود را اینجا بنویسید..."></textarea>
            </div>
            
            <div class="field" id="linkGroup" style="display: none; margin-bottom: 20px;">
                <label class="label">لینک پست کانال تلگرام</label>
                <input type="text" class="input" name="channel_link" id="channelLink" placeholder="مثال: https://t.me/MyChannel/123" dir="ltr">
                <small style="color:var(--dim); display:block; margin-top:5px;">ربات حتماً باید در این کانال عضو (یا ادمین) باشد تا بتواند پیام را کپی کند و ایموجی‌ها حفظ شوند.</small>
            </div>

            <label class="bc-checkbox-wrapper">
                <input type="checkbox" name="pingmessage" value="yes" id="pingmessage">
                <div class="bc-checkbox-text">
                    <strong>پین شدن پیام در ربات (Pin Message)</strong>
                    <small>در صورت فعال بودن این گزینه، پیام بلافاصله پس از رسیدن به کاربر در صفحه چت او سنجاق (Pin) خواهد شد که باعث افزایش چشمگیر بازدید می‌شود.</small>
                </div>
            </label>
        </div>

        <div class="bc-submit">
            <button type="submit" class="btn btn-primary" <?php if (is_file('../cronbot/info')) echo 'disabled'; ?>>
                <?= icon('send', 18) ?> آغاز عملیات ارسال
            </button>
        </div>
    </form>
    
    <!-- Section 4: History -->
    <div class="bc-section" style="margin-top: 40px;">
        <div class="bc-section-title">
            <?= icon('clock', 18) ?> تاریخچه پیام‌های اخیر
        </div>
        
        <?php if (count($histories) > 0): ?>
        <div class="tbl-wrap dash-bc-history" style="margin-top: 20px;">
            <table class="tbl-sm">
                <thead>
                    <tr>
                        <th>محتوا / لینک</th>
                        <th>نوع</th>
                        <th>جامعه هدف</th>
                        <th>وضعیت</th>
                        <th style="text-align:center;">زمان ارسال</th>
                        <th style="text-align:center;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($histories as $history): ?>
                    <tr>
                        <td data-label="محتوا / لینک" class="truncate-td" title="<?= htmlspecialchars($history['content']) ?>">
                            <span class="truncate-text"><?= htmlspecialchars($history['content']) ?></span>
                        </td>
                        <td data-label="نوع">
                            <?php if($history['message_type'] == 'text'): ?>
                                <span class="bc-badge badge-text">متنی</span>
                            <?php else: ?>
                                <span class="bc-badge badge-link">لینک کانال</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="جامعه هدف"><span class="bc-badge badge-audience"><?= htmlspecialchars($history['target_audience']) ?></span></td>
                        <td data-label="وضعیت">
                            <?php if($history['status'] == 'completed'): ?>
                                <span class="status-pill success" style="font-size:0.8rem; padding:4px 8px;">پایان یافته</span>
                            <?php else: ?>
                                <span class="status-pill warning" style="font-size:0.8rem; padding:4px 8px;">در جریان</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="زمان ارسال" dir="ltr" style="text-align:center;"><?= jdate('Y/m/d H:i', $history['created_at']) ?></td>
                        <td data-label="عملیات" style="text-align:center;">
                            <button type="button" class="btn btn-sm reuse-btn" data-history="<?= htmlspecialchars(json_encode($history), ENT_QUOTES, 'UTF-8') ?>" onclick="reuseBroadcast(this)" style="padding: 4px 10px; font-size: 0.8rem; background: var(--sf3); border: 1px solid var(--bd);">استفاده مجدد</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 30px; color: var(--dim); background: var(--sf); border-radius: 12px; border: 1px dashed var(--bd);">
            <?= icon('inbox', 32) ?>
            <p style="margin-top: 12px;">هنوز هیچ پیام همگانی ارسال نکرده‌اید. پیام‌های ارسالی شما در اینجا ذخیره خواهند شد.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleFields() {
    var type = document.getElementById('messageType').value;
    var btn = document.getElementById('btnmessage');
    var msg = document.getElementById('messageGroup');
    var textGroup = document.getElementById('textGroup');
    var linkGroup = document.getElementById('linkGroup');
    
    if (type === 'unpinmessage') {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        msg.style.display = 'none';
    } else if (type === 'forwardlink') {
        btn.disabled = false; // copyMessage supports inline keyboards!
        btn.style.opacity = '1';
        msg.style.display = 'block';
        msg.style.opacity = '1';
        textGroup.style.display = 'none';
        linkGroup.style.display = 'block';
    } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        msg.style.display = 'block';
        msg.style.opacity = '1';
        textGroup.style.display = 'block';
        linkGroup.style.display = 'none';
    }
}

function reuseBroadcast(btn) {
    var data = JSON.parse(btn.getAttribute('data-history'));
    if (data.message_type === 'text') {
        document.getElementById('messageType').value = 'sendmessage';
        document.getElementById('messageText').value = data.content;
    } else {
        document.getElementById('messageType').value = 'forwardlink';
        document.getElementById('channelLink').value = data.content;
    }
    
    document.getElementById('targetUsers').value = data.target_audience;
    document.getElementById('targetAgent').value = 'all'; // Default
    
    toggleFields();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Initialize on load
document.addEventListener('DOMContentLoaded', toggleFields);
</script>
<?php require 'inc/layout_foot.php'; ?>
