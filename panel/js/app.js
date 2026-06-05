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

window.addEventListener('load', function () { _lb.done(); });

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

document.body.addEventListener('htmx:beforeRequest', function (e) {
    _lb.start();
    
    var elt = e.detail.elt;
    var isGet = e.detail.requestConfig.verb === 'get';
    if (isGet && elt && (elt.tagName === 'A' || elt.classList.contains('nav-item') || elt.classList.contains('bnav-item') || elt.closest('.sidebar-nav') || elt.closest('.bottom-nav'))) {
        var content = document.querySelector('main.content');
        if (content) {
            var targetPath = e.detail.requestConfig.path || '';
            content.innerHTML = getSkeletonHTML(targetPath);
            window.scrollTo({ top: 0, behavior: 'instant' });
        }
    }
});

document.body.addEventListener('htmx:beforeSwap', function (evt) {
    // If response status is 401/403 or contains login page identifiers, redirect whole window
    var resp = evt.detail.xhr.responseText || '';
    if (evt.detail.xhr.status === 401 || evt.detail.xhr.status === 403 || resp.indexOf('class="auth"') !== -1 || resp.indexOf('js/login.js') !== -1) {
        evt.preventDefault();
        window.location.href = 'login.php';
    }
});

document.body.addEventListener('htmx:afterSwap', function (e) {
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

document.getElementById('confirm-ok').addEventListener('click', function () {
    document.getElementById('confirm-veil').classList.remove('open');
    if (_confirmCb) { var cb = _confirmCb; _confirmCb = null; cb(); }
});

document.getElementById('confirm-veil').addEventListener('click', function (e) {
    if (e.target === this) closeConfirm();
});

document.body.addEventListener('htmx:confirm', function(e) {
    if(e.detail.elt.hasAttribute('data-confirm')) {
        e.preventDefault();
        showConfirm(e.detail.elt.getAttribute('data-confirm') || t('jsConfirmDefault'), function() {
            e.detail.issueRequest();
        });
    }
});

var _THEME_BG = {
    navy: '#0F172A', purple: '#180D2E', emerald: '#0A1F1C',
    sunset: '#1A0D0D', slate: '#080808', light: '#F1F5F9',
    linen: '#FAF7F2', mint: '#F0FDF4', lavender: '#FAF5FF'
};
var _LIGHT_THEMES = ['light', 'linen', 'mint', 'lavender'];

window.applyTheme = function (t) {
    var root = document.documentElement;
    root.setAttribute('data-theme', t);
    root.style.backgroundColor = _THEME_BG[t] || '#0F172A';
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

document.querySelectorAll('.modal-veil').forEach(function (v) {
    v.addEventListener('click', function (e) { if (e.target === v) v.classList.remove('open'); });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-veil.open').forEach(function (m) { m.classList.remove('open'); });
        closeSidebar();
        closeConfirm();
    }
});

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
};

document.body.addEventListener('htmx:load', function(e) {
    initUI(e.detail.elt);
});
initUI(document);

setTimeout(function () {
    document.querySelectorAll('.notice').forEach(function (n) {
        n.style.transition = 'opacity .4s,transform .4s';
        n.style.opacity    = '0';
        n.style.transform  = 'translateY(-4px)';
        setTimeout(function () { n.remove(); }, 420);
    });
}, 5500);
