window.openEditModal = function (p) {
    document.getElementById('edit_id').value = p.id || '';
    document.getElementById('edit_name').value = p.name_product || '';
    document.getElementById('edit_price').value = p.price_product || '';
    document.getElementById('edit_volume').value = p.Volume_constraint || '';
    document.getElementById('edit_time').value = p.Service_time || '';
    document.getElementById('edit_cat').value = p.category || '';
    document.getElementById('edit_agent').value = p.agent || '';
    document.getElementById('edit_note').value = p.note || '';
    document.getElementById('edit_data_limit_reset').value = p.data_limit_reset || 'no_reset';
    document.getElementById('edit_one_buy_status').value = p.one_buy_status || '0';
    document.getElementById('edit_inbounds').value = p.inbounds || '';
    document.getElementById('edit_proxies').value = p.proxies || '';
    document.getElementById('edit_hide_panel').value = p.hide_panel || '{}';
    var sel = document.getElementById('edit_panel');
    if (sel) {
        for (var i = 0; i < sel.options.length; i++) {
            sel.options[i].selected = sel.options[i].value === (p.Location || '');
        }
    }

    openModal('editModal');
};
