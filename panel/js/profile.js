window.switchTab = function (name) {
    var panes = { invs: 'paneOrders', pay: 'panePay', refs: 'paneRefs' };
    var tabs = { invs: 'tabInvs', pay: 'tabPay', refs: 'tabRefs' };
    var links = { invs: 'linkAllInvs', pay: 'linkAllPays', refs: 'linkAllRefs' };

    Object.values(panes).forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });

    Object.values(links).forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });

    Object.values(tabs).forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.background = 'transparent';
            el.style.color = 'var(--mute)';
            el.style.border = 'none';
        }
    });

    var pane = document.getElementById(panes[name]);
    if (pane) pane.style.display = 'block';

    var link = document.getElementById(links[name]);
    if (link) link.style.display = 'inline-block';

    var tab = document.getElementById(tabs[name]);
    if (tab) {
        tab.style.background = 'var(--ac)';
        tab.style.color = 'var(--btn-ac-text, #fff)';
    }
};
