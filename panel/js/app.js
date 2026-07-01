var _lb = (function () {
    var el   = document.getElementById('load-bar');
    var t    = null;
    var live = false;

    function setW(pct, dur) {
        if (!el) return;
        el.style.setProperty('--lb-w',  pct + '%');
        el.style.setProperty('--lb-dur', dur + 'ms');
        el.className = 'lb-go';
    }

    function start() {
        if (!el) return;
        live = true;
        clearTimeout(t);
        el.className = '';
        el.style.setProperty('--lb-w',  '0%');
        el.style.setProperty('--lb-dur','0ms');
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                setW(30, 300);
                t = setTimeout(function () { setW(60, 900);  }, 350);
                t = setTimeout(function () { setW(80, 1200); }, 1300);
                t = setTimeout(function () { setW(90, 800);  }, 2600);
            });
        });
    }

    function done() {
        if (!el || !live) return;
        live = false;
        clearTimeout(t);
        el.className = 'lb-end';
        t = setTimeout(function () {
            el.className = '';
            el.style.setProperty('--lb-w', '0%');
        }, 450);
    }

    return { start: start, done: done };
}());


function getSkeletonHTML(path) {
    var isDashboard = path.includes('index.php') || path === '' || path === '/' || (!path.includes('.php') && !path.includes('?'));
    var isList = path.includes('users.php') || path.includes('invoice.php') || path.includes('service.php') || path.includes('product.php') || path.includes('payment.php') || path.includes('panels_manage.php');
    var isSettings = path.includes('bot_settings.php') || path.includes('settings.php') || path.includes('keyboard.php');

    var html = '<div class="skeleton-container">';
    
    // Header
    html += '<div class="skeleton-header">' +
            '<div class="skeleton-title skeleton-pulse"></div>' +
            '<div class="skeleton-subtitle skeleton-pulse" style="margin-top:8px;"></div>' +
            '</div>';

    if (isDashboard) {
        // Stats grid
        html += '<div class="skeleton-grid" style="margin-top: 10px;">' +
                '<div class="skeleton-card skeleton-pulse"></div>' +
                '<div class="skeleton-card skeleton-pulse"></div>' +
                '<div class="skeleton-card skeleton-pulse"></div>' +
                '</div>';
        // Big charts
        html += '<div class="skeleton-row-box skeleton-pulse" style="height: 300px; margin-top: 20px;"></div>';
    } else if (isList) {
        // Search bar
        html += '<div class="skeleton-line skeleton-pulse" style="width: 200px; height: 38px; border-radius: 8px; margin: 10px 0 20px 0;"></div>';
        // Table skeleton
        html += '<div class="skeleton-row-box" style="gap: 20px;">' +
                '<div class="skeleton-line skeleton-pulse w-full" style="height: 25px;"></div>' +
                '<div class="skeleton-line skeleton-pulse w-full"></div>' +
                '<div class="skeleton-line skeleton-pulse w-2-3"></div>' +
                '<div class="skeleton-line skeleton-pulse w-full"></div>' +
                '<div class="skeleton-line skeleton-pulse w-1-2"></div>' +
                '<div class="skeleton-line skeleton-pulse w-full"></div>' +
                '</div>';
    } else if (isSettings) {
        // Settings layout
        html += '<div class="arvan-tab-card" style="border: none; box-shadow: none; background: transparent; margin-top: 15px;">' +
                '<div class="arvan-sidebar" style="background: transparent; border: none; min-height: auto; width: 200px; margin-left: 20px;">' +
                '<div class="skeleton-sub-tabs" style="display: flex; flex-direction: column; gap: 10px;">' +
                '<div class="skeleton-line skeleton-pulse" style="width: 150px; height: 35px; border-radius: 8px;"></div>' +
                '<div class="skeleton-line skeleton-pulse" style="width: 130px; height: 35px; border-radius: 8px;"></div>' +
                '<div class="skeleton-line skeleton-pulse" style="width: 140px; height: 35px; border-radius: 8px;"></div>' +
                '</div>' +
                '</div>' +
                '<div class="arvan-content-area" style="padding: 0; flex: 1;">' +
                '<div class="skeleton-grid">' +
                '<div class="skeleton-card skeleton-pulse" style="height: 80px;"></div>' +
                '<div class="skeleton-card skeleton-pulse" style="height: 80px;"></div>' +
                '<div class="skeleton-card skeleton-pulse" style="height: 80px;"></div>' +
                '<div class="skeleton-card skeleton-pulse" style="height: 80px;"></div>' +
                '</div>' +
                '</div>' +
                '</div>';
    } else {
        // General fallback
        html += '<div class="skeleton-row-box skeleton-pulse" style="height: 250px; margin-top: 15px;"></div>';
    }

    html += '</div>';
    return html;
}





var _TOAST_ICONS = {
    ok:   '<polyline points="20 6 9 17 4 12"/>',
    no:   '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    warn: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
};

window.toast = function (msg, type, dur) {
    type = type || 'info';
    dur  = dur  || 4500;
    var area = document.getElementById('toast-area');
    if (!area) return;
    var el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.innerHTML =
        '<div class="toast-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
        (_TOAST_ICONS[type] || _TOAST_ICONS.info) +
        '</svg></div>' +
        '<div style="flex:1;color:var(--text2)">' + String(msg).replace(/</g, '&lt;') + '</div>' +
        '<button class="toast-close">✕</button>';
    area.appendChild(el);
    var timer = setTimeout(function () {
        el.classList.add('closing');
        setTimeout(function () { el.remove(); }, 260);
    }, dur);
    el.querySelector('.toast-close').addEventListener('click', function () {
        clearTimeout(timer);
        el.remove();
    });
};

var _confirmCb = null;

window.showConfirm = function (msg, cb, title) {
    document.getElementById('confirm-title').textContent = title || t('jsConfirmTitle');
    document.getElementById('confirm-msg').textContent   = msg   || t('jsConfirmMsg');
    _confirmCb = cb;
    document.getElementById('confirm-veil').classList.add('open');
};

window.closeConfirm = function () {
    document.getElementById('confirm-veil').classList.remove('open');
    _confirmCb = null;
};




var _THEME_BG = {
    navy: '#222831', light: '#F1F5F9'
};
var _LIGHT_THEMES = ['light'];

window.applyTheme = function (t) {
    var root = document.documentElement;
    root.setAttribute('data-theme', t);
    root.style.backgroundColor = _THEME_BG[t] || '#222831';
    var isLight = _LIGHT_THEMES.indexOf(t) >= 0;
    root.style.colorScheme = isLight ? 'light' : 'dark';
    localStorage.setItem('panel-theme', t);
    // Remember last used dark / light separately
    if (isLight) localStorage.setItem('panel-theme-light', t);
    else         localStorage.setItem('panel-theme-dark',  t);
    var mtc = document.getElementById('mtc');
    if (mtc && _THEME_BG[t]) mtc.content = _THEME_BG[t];
    // Update the topbar toggle icon
    var btn = document.getElementById('theme-quick-toggle');
    if (btn) {
        btn.setAttribute('data-dark', isLight ? '0' : '1');
        btn.title = isLight ? 'تم تیره' : 'تم روشن';
        btn.innerHTML = isLight
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
    }
};

// Toggle between the last-used dark theme and the last-used light theme
window.toggleDarkLight = function () {
    var cur = localStorage.getItem('panel-theme') || 'navy';
    var isCurrentlyLight = _LIGHT_THEMES.indexOf(cur) >= 0;
    var next;
    if (isCurrentlyLight) {
        next = localStorage.getItem('panel-theme-dark') || 'navy';
    } else {
        next = localStorage.getItem('panel-theme-light') || 'light';
    }
    applyTheme(next);
    // If on settings page, update the active card
    document.querySelectorAll('.theme-card').forEach(function (c) {
        c.classList.toggle('active', c.dataset.tk === next);
    });
};

window.toggleSidebar = function () {
    var sb = document.getElementById('sidebar');
    sb.classList.toggle('collapsed');
    localStorage.setItem('panel-sb-collapsed', sb.classList.contains('collapsed') ? '1' : '0');
};

function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('backdrop').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('backdrop').classList.remove('show');
    document.body.style.overflow = '';
}

(function () {
    if (document.documentElement.classList.contains('sb-pre-collapsed')) {
        document.getElementById('sidebar').classList.add('collapsed');
        document.documentElement.classList.remove('sb-pre-collapsed');
    }
}());

var _backdrop = document.getElementById('backdrop');
if (_backdrop) _backdrop.addEventListener('click', closeSidebar);

var _swipeSb = document.getElementById('sidebar');
if (_swipeSb) {
    var _swipeX = 0;
    _swipeSb.addEventListener('touchstart', function (e) { _swipeX = e.touches[0].clientX; }, { passive: true });
    _swipeSb.addEventListener('touchmove',  function (e) { if (e.touches[0].clientX - _swipeX > 40) closeSidebar(); }, { passive: true });
}

window.openModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.classList.add('open');
};
window.closeModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('open');
};

// ─── Category page UI ─────────────────────────────────────────────────────────
if (!window._categoryEventsAttached) {
    window._categoryEventsAttached = true;

    // Add Category button
    document.body.addEventListener('click', function(e) {
        var addBtn = e.target.closest('#btnAddCategory');
        if (addBtn) {
            var modal    = document.getElementById('categoryModal');
            var titleEl  = document.getElementById('catModalTitle');
            var actionEl = document.getElementById('catAction');
            var idEl     = document.getElementById('catId');
            var nameEl   = document.getElementById('catName');
            var statusEl = document.getElementById('catStatus');
            if (!modal) return;
            if (titleEl)  titleEl.innerText = 'افزودن دسته‌بندی';
            if (actionEl) actionEl.value    = 'add';
            if (idEl)     idEl.value        = '';
            if (nameEl)   nameEl.value      = '';
            if (statusEl) statusEl.value    = 'active';
            modal.classList.add('open');
            if (nameEl) nameEl.focus();
        }
    });

    // Edit Category button
    document.body.addEventListener('click', function(e) {
        var editBtn = e.target.closest('[data-edit-cat]');
        if (editBtn) {
            var modal    = document.getElementById('categoryModal');
            var titleEl  = document.getElementById('catModalTitle');
            var actionEl = document.getElementById('catAction');
            var idEl     = document.getElementById('catId');
            var nameEl   = document.getElementById('catName');
            var statusEl = document.getElementById('catStatus');
            if (!modal) return;
            try {
                var cat = JSON.parse(editBtn.getAttribute('data-edit-cat'));
                if (titleEl)  titleEl.innerText = 'ویرایش دسته‌بندی';
                if (actionEl) actionEl.value    = 'edit';
                if (idEl)     idEl.value        = cat.id;
                if (nameEl)   nameEl.value      = cat.name;
                if (statusEl) statusEl.value    = cat.status;
                modal.classList.add('open');
                if (nameEl) nameEl.focus();
            } catch(err) {
                console.error("Could not parse category JSON", err);
            }
        }
    });
    
    // Close modal on close button click
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('#btnCloseCatModal') || e.target.closest('#btnCancelCatModal')) {
            var modal = document.getElementById('categoryModal');
            if (modal) modal.classList.remove('open');
        }
    });
}

window.closeCategoryModal = function () {
    var modal = document.getElementById('categoryModal');
    if (modal) modal.classList.remove('open');
};

function initCategoryUI(context) {
    // No-op: handled by event delegation in DOMContentLoaded.
}




window.initUI = function(context) {
    context.querySelectorAll('.search-box').forEach(function (box) {
        if (box.dataset.initialized) return;
        box.dataset.initialized = 'true';
        var inp = box.querySelector('input');
        var btn = box.querySelector('.search-clear');
        if (!inp || !btn) return;
        function update() { btn.style.display = inp.value ? 'grid' : 'none'; }
        inp.addEventListener('input', update);
        update();
        btn.addEventListener('click', function () {
            inp.value = '';
            inp.focus();
            update();
            inp.dispatchEvent(new Event('input'));
        });
    });

    context.querySelectorAll('[data-filter]').forEach(function (inp) {
        if (inp.dataset.initialized) return;
        inp.dataset.initialized = 'true';
        var tbl = document.getElementById(inp.dataset.filter);
        if (!tbl) return;
        inp.addEventListener('input', function () {
            var q = inp.value.trim().toLowerCase();
            var items = tbl.querySelectorAll('tbody tr, .filterable-item');
            items.forEach(function (item) {
                if (item.classList.contains('empty') || item.querySelector('.empty')) return;
                item.style.display = !q || item.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });

    // Category page bindings (safe to call on every page – exits immediately if modal absent)
    initCategoryUI(context);
};

initUI(document);

function initBroadcastUI(context) {
    context = context || document;
    if (!context.querySelector || !context.querySelector('#broadcastForm')) return;

    window.toggleFields();
    window.toggleBtnFields();
}

window.setBroadcastFieldState = function(el, enabled, required) {
    if (!el) return;
    el.disabled = !enabled;
    if (required) {
        el.setAttribute('required', 'required');
    } else {
        el.removeAttribute('required');
    }
};

window.setDynamicFieldsState = function(enabled, required) {
    document.querySelectorAll('.dyn-btn-text').forEach(el => window.setBroadcastFieldState(el, enabled, required));
    document.querySelectorAll('.dyn-btn-link').forEach(el => window.setBroadcastFieldState(el, enabled, required));
};

window.addDynamicButton = function(text, link, color) {
    var container = document.getElementById('dynamicButtonsContainer');
    if (!container) return;
    var row = document.createElement('div');
    row.className = 'dynamic-button-row';
    row.style.display = 'flex';
    row.style.gap = '10px';
    row.style.alignItems = 'center';
    row.style.marginBottom = '10px';
    
    var t = text ? text.replace(/"/g, '&quot;') : '';
    var l = link ? link.replace(/"/g, '&quot;') : '';
    var c = color || 'default';
    
    row.innerHTML = `
        <input type="text" class="input dyn-btn-text" name="custom_btn_text_url[]" placeholder="متن دکمه" value="${t}" style="flex: 2;" required>
        <input type="url" class="input dyn-btn-link" name="custom_btn_link[]" placeholder="لینک" dir="ltr" value="${l}" style="flex: 3;" required>
        <select class="input select dyn-btn-color" name="custom_btn_color[]" style="flex: 1; min-width: 110px;">
            <option value="default" ${c === 'default' ? 'selected' : ''}>پیش‌فرض</option>
            <option value="primary" ${c === 'primary' ? 'selected' : ''}>آبی (Primary)</option>
            <option value="success" ${c === 'success' ? 'selected' : ''}>سبز (Success)</option>
            <option value="danger" ${c === 'danger' ? 'selected' : ''}>قرمز (Danger)</option>
        </select>
        <button type="button" class="btn btn-sm" onclick="window.removeDynamicButton(this)" style="background:var(--nos); color:var(--no); border:none; border-radius:8px; padding:8px 12px; font-size: 16px;">❌</button>
    `;
    container.appendChild(row);
};

window.removeDynamicButton = function(btn) {
    var rows = document.querySelectorAll('.dynamic-button-row');
    if (rows.length > 1) {
        btn.closest('.dynamic-button-row').remove();
    } else {
        alert('حداقل یک دکمه باید وجود داشته باشد.');
    }
};

window.toggleFields = function () {
    var type = document.getElementById('messageType');
    var btn = document.getElementById('btnmessage');
    var msg = document.getElementById('messageGroup');
    var textGroup = document.getElementById('textGroup');
    var linkGroup = document.getElementById('linkGroup');
    var messageText = document.getElementById('messageText');
    var channelLink = document.getElementById('channelLink');
    var pingmessage = document.getElementById('pingmessage');

    if (!type || !btn || !msg || !textGroup || !linkGroup) return;

    if (type.value === 'unpinmessage') {
        btn.value = 'none';
        btn.disabled = true;
        btn.style.opacity = '0.5';
        msg.style.display = 'none';
        if (pingmessage) pingmessage.checked = false;
        window.setBroadcastFieldState(messageText, false, false);
        window.setBroadcastFieldState(channelLink, false, false);
    } else if (type.value === 'forwardlink') {
        btn.disabled = false;
        btn.style.opacity = '1';
        msg.style.display = 'block';
        msg.style.opacity = '1';
        textGroup.style.display = 'none';
        linkGroup.style.display = 'block';
        window.setBroadcastFieldState(messageText, false, false);
        window.setBroadcastFieldState(channelLink, true, true);
    } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        msg.style.display = 'block';
        msg.style.opacity = '1';
        textGroup.style.display = 'block';
        linkGroup.style.display = 'none';
        window.setBroadcastFieldState(messageText, true, true);
        window.setBroadcastFieldState(channelLink, false, false);
    }

    window.toggleBtnFields();
};

window.toggleBtnFields = function () {
    var btn = document.getElementById('btnmessage');
    var customUrlFields = document.getElementById('customUrlFields');
    var customProductFields = document.getElementById('customProductFields');
    var customBtnTextProd = document.getElementById('customBtnTextProd');
    var customBtnCallback = document.getElementById('customBtnCallback');

    if (!btn || !customUrlFields || !customProductFields) return;

    var btnVal = btn.disabled ? 'none' : btn.value;
    if (btnVal === 'custom_url') {
        customUrlFields.style.display = 'block';
        customProductFields.style.display = 'none';
        window.setDynamicFieldsState(true, true);
        if (document.getElementById('dynamicButtonsContainer') && document.getElementById('dynamicButtonsContainer').children.length === 0) {
            window.addDynamicButton();
        }
        window.setBroadcastFieldState(customBtnTextProd, false, false);
        window.setBroadcastFieldState(customBtnCallback, false, false);
    } else if (btnVal === 'custom_product') {
        customUrlFields.style.display = 'none';
        customProductFields.style.display = 'block';
        window.setDynamicFieldsState(false, false);
        window.setBroadcastFieldState(customBtnTextProd, true, true);
        window.setBroadcastFieldState(customBtnCallback, true, true);
    } else {
        customUrlFields.style.display = 'none';
        customProductFields.style.display = 'none';
        window.setDynamicFieldsState(false, false);
        window.setBroadcastFieldState(customBtnTextProd, false, false);
        window.setBroadcastFieldState(customBtnCallback, false, false);
    }
};

window.reuseBroadcast = function (btn) {
    if (!btn) return;
    var data = JSON.parse(btn.getAttribute('data-history') || '{}');

    if (data.message_type === 'text') {
        document.getElementById('messageType').value = 'sendmessage';
        document.getElementById('messageText').value = data.content || '';
    } else if (data.message_type === 'forwardlink') {
        document.getElementById('messageType').value = 'forwardlink';
        document.getElementById('channelLink').value = data.content || '';
    } else {
        document.getElementById('messageType').value = 'unpinmessage';
    }

    document.getElementById('targetUsers').value = ['all', 'customer', 'nonecustomer'].indexOf(data.target_audience) >= 0 ? data.target_audience : 'all';
    document.getElementById('targetAgent').value = 'all';

    var btnmessage = document.getElementById('btnmessage');
    btnmessage.value = data.button_type || 'none';
    
    var container = document.getElementById('dynamicButtonsContainer');
    if (container) container.innerHTML = '';
    
    if (data.button_type === 'custom_url' || data.button_type === 'custom_url_dynamic') {
        btnmessage.value = 'custom_url';
        if (data.button_type === 'custom_url_dynamic' && data.button_data) {
            try {
                var btns = JSON.parse(data.button_data);
                if (Array.isArray(btns)) {
                    btns.forEach(function(b) {
                        window.addDynamicButton(b.text, b.url, b.color);
                    });
                }
            } catch(e) {}
        } else {
             window.addDynamicButton(data.button_text || '', data.button_data || '');
        }
    } else if (data.button_type === 'custom_product') {
        document.getElementById('customBtnTextProd').value = data.button_text || '';
        document.getElementById('customBtnCallback').value = data.button_data || '';
    }

    window.toggleFields();
    window.toggleBtnFields();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};


initBroadcastUI(document);

setTimeout(function () {
    document.querySelectorAll('.notice').forEach(function (n) {
        n.style.transition = 'opacity .4s,transform .4s';
        n.style.opacity    = '0';
        n.style.transform  = 'translateY(-4px)';
        setTimeout(function () { n.remove(); }, 420);
    });
}, 5500);


if (!window.__appListenersAdded) {
    window.__appListenersAdded = true;
    var __previousContent = null;

window.addEventListener('load', function () { _lb.done(); });

document.addEventListener('htmx:beforeRequest', function (e) {
    _lb.start();
    
    var elt = e.detail.elt;
    var isGet = e.detail.requestConfig.verb === 'get';
    if (isGet && elt && (elt.tagName === 'A' || elt.classList.contains('nav-item') || elt.classList.contains('bnav-item') || elt.closest('.sidebar-nav') || elt.closest('.bottom-nav'))) {
        var content = document.querySelector('main.content');
        if (content) {
            __previousContent = content.innerHTML;
            var targetPath = e.detail.requestConfig.path || '';
            content.innerHTML = getSkeletonHTML(targetPath);
            window.scrollTo({ top: 0, behavior: 'instant' });
        }
    }
});

document.addEventListener('htmx:beforeSwap', function (evt) {
    // If response status is 401/403 or contains login page identifiers, redirect whole window
    var resp = evt.detail.xhr.responseText || '';
    if (evt.detail.xhr.status === 401 || evt.detail.xhr.status === 403 || resp.indexOf('class="auth"') !== -1 || resp.indexOf('js/login.js') !== -1) {
        evt.preventDefault();
        window.location.href = 'login.php';
    }
});

document.addEventListener('htmx:responseError', function (e) {
    _lb.done();
    var content = document.querySelector('main.content');
    if (content && __previousContent) {
        content.innerHTML = __previousContent;
    }
    var status = e.detail.xhr.status;
    if (status === 401 || status === 403) {
        window.location.href = 'login.php';
    } else {
        if (window.toast) {
            window.toast('خطا در بارگذاری صفحه (' + status + ')', 'no');
        } else {
            alert('خطا در بارگذاری صفحه (' + status + ')');
        }
    }
});

document.addEventListener('htmx:sendError', function (e) {
    _lb.done();
    var content = document.querySelector('main.content');
    if (content && __previousContent) {
        content.innerHTML = __previousContent;
    }
    if (window.toast) {
        window.toast('خطا در ارتباط با سرور. وضعیت شبکه خود را بررسی کنید.', 'warn');
    } else {
        alert('خطا در ارتباط با سرور. وضعیت شبکه خود را بررسی کنید.');
    }
});

document.addEventListener('htmx:afterSwap', function (e) {
    _lb.done();
    closeSidebar();
});

// Heartbeat ping every 5 minutes (300000ms) to keep PHP session alive
setInterval(function () {
    fetch('ajax/ping.php')
        .then(function (res) {
            if (res.status === 401) {
                window.location.href = 'login.php';
            }
        })
        .catch(function (err) {
            console.warn('Session ping failed:', err);
        });
}, 300000);

document.getElementById('confirm-ok').addEventListener('click', function () {
    document.getElementById('confirm-veil').classList.remove('open');
    if (_confirmCb) { var cb = _confirmCb; _confirmCb = null; cb(); }
});

document.getElementById('confirm-veil').addEventListener('click', function (e) {
    if (e.target === this) closeConfirm();
});

document.addEventListener('htmx:confirm', function(e) {
    if(e.detail.elt.hasAttribute('data-confirm')) {
        e.preventDefault();
        showConfirm(e.detail.elt.getAttribute('data-confirm') || t('jsConfirmDefault'), function() {
            e.detail.issueRequest(true);
        });
    }
});

document.querySelectorAll('.modal-veil').forEach(function (v) {
    var startedOnVeil = false;
    v.addEventListener('mousedown', function (e) {
        startedOnVeil = (e.target === v);
    });
    v.addEventListener('click', function (e) {
        if (startedOnVeil && e.target === v) {
            v.classList.remove('open');
            if (v.id === 'categoryModal' && typeof window.closeCategoryModal === 'function') {
                window.closeCategoryModal();
            } else if (v.id === 'editChannelModal' && typeof window.closeEditModal === 'function') {
                window.closeEditModal();
            }
        }
    });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-veil.open').forEach(function (m) { m.classList.remove('open'); });
        closeSidebar();
        closeConfirm();
    }
});

document.addEventListener('htmx:load', function(e) {
    initUI(e.detail.elt);
    initBroadcastUI(e.detail.elt);
});

document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'messageType') {
        window.toggleFields();
    } else if (e.target && e.target.id === 'btnmessage') {
        window.toggleBtnFields();
    }
});

document.addEventListener('submit', function (e) {
    var form = e.target;
	if (!form || (form.id !== 'broadcastForm' && form.id !== 'cancelBroadcastForm')) return;

	e.preventDefault();
    e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') {
        e.stopImmediatePropagation();
    }

    if (form.id === 'cancelBroadcastForm') {
        if (!confirm('عملیات ارسال همگانی لغو شود؟ پیام‌هایی که قبلاً ارسال شده‌اند قابل برگشت نیستند.')) {
            return;
        }

        var cancelFeedback = document.getElementById('broadcastFeedback');
        var cancelBtn = document.getElementById('broadcastCancelBtn');
        if (cancelBtn) cancelBtn.disabled = true;
        if (cancelFeedback) cancelFeedback.innerHTML = '<div class="alert alert-info">در حال لغو عملیات...</div>';

        fetch(form.getAttribute('action') || 'ajax/broadcast_cancel.php', {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'HX-Request': 'true' }
        })
        .then(function (res) { return res.text(); })
        .then(function (html) {
            if (cancelFeedback) cancelFeedback.innerHTML = html;
            Array.prototype.forEach.call((cancelFeedback || document).querySelectorAll('script'), function (script) {
                try { Function(script.textContent)(); } catch (err) { console.error(err); }
            });
            if (cancelBtn && html.indexOf('alert-success') === -1) {
                cancelBtn.disabled = false;
            }
        })
        .catch(function () {
            if (cancelFeedback) cancelFeedback.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور. لطفا دوباره تلاش کنید.</div>';
            if (cancelBtn) cancelBtn.disabled = false;
        });
        return;
    }

	window.toggleFields();

    var feedback = document.getElementById('broadcastFeedback');
    var submitBtn = document.getElementById('broadcastSubmitBtn');
    if (submitBtn) submitBtn.disabled = true;
    if (feedback) feedback.innerHTML = '<div class="alert alert-info">در حال ثبت عملیات...</div>';

	fetch(form.getAttribute('action') || 'ajax/broadcast_action.php', {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'HX-Request': 'true' }
    })
    .then(function (res) { return res.text(); })
    .then(function (html) {
        if (feedback) feedback.innerHTML = html;
        Array.prototype.forEach.call((feedback || document).querySelectorAll('script'), function (script) {
            try { Function(script.textContent)(); } catch (err) { console.error(err); }
        });
        if (submitBtn && html.indexOf('alert-success') === -1) {
            submitBtn.disabled = false;
        }
    })
    .catch(function () {
        if (feedback) feedback.innerHTML = '<div class="alert alert-danger">خطا در ارتباط با سرور. لطفا دوباره تلاش کنید.</div>';
        if (submitBtn) submitBtn.disabled = false;
    });
}, true);

// Collapsible Sidebar logic
document.addEventListener('click', function(e) {
    var groupBtn = e.target.closest('.nav-group-btn');
    if (groupBtn) {
        var group = groupBtn.closest('.nav-group');
        group.classList.toggle('open');
        // Optionally close other groups
        // document.querySelectorAll('.nav-group').forEach(function(g) {
        //     if (g !== group) g.classList.remove('open');
        // });
    }
});

}
