(function () {
    var t = localStorage.getItem('panel-theme') || 'navy';
    var bg = {
        navy: '#222831', purple: '#1D3557', emerald: '#1D3557',
        sunset: '#1D3557', slate: '#1D3557', light: '#F1F5F9',
        linen: '#FAF7F2', mint: '#F0FDF4', lavender: '#FAF5FF'
    };
    var root = document.documentElement;
    root.style.backgroundColor = bg[t] || '#222831';
    root.setAttribute('data-theme', t);
    root.style.colorScheme = (t === 'light' || t === 'linen' || t === 'mint' || t === 'lavender') ? 'light' : 'dark';
    var mtc = document.getElementById('mtc');
    if (mtc && bg[t]) mtc.content = bg[t];
    if (localStorage.getItem('panel-sb-collapsed') === '1' && window.innerWidth > 768)
        root.classList.add('sb-pre-collapsed');
}());
