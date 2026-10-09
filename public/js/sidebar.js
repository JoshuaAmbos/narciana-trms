// Narciana TRMS - Responsive sidebar toggle (drawer / rail / full)
function toggleSidebarWidth() {
    var width = window.innerWidth;
    if (width < 1024) {
        document.body.classList.toggle('nav-open');
        return;
    }
    document.documentElement.classList.remove('preload-nav');
    document.body.classList.remove('preload-nav');

    var isLandscapeTablet = width >= 1024 && width <= 1280;
    var current = 'default';
    if (document.body.classList.contains('side-expanded')) current = 'expanded';
    else if (document.body.classList.contains('side-rail')) current = 'rail';
    else if (document.body.classList.contains('side-collapsed')) current = 'collapsed';
    else current = isLandscapeTablet ? 'rail' : 'expanded';

    document.body.classList.remove('side-rail', 'side-expanded', 'side-collapsed');
    document.documentElement.classList.remove('side-rail', 'side-expanded', 'side-collapsed');

    var next;
    if (current === 'rail') {
        next = 'expanded';
    } else if (current === 'expanded') {
        next = 'collapsed';
    } else {
        next = 'rail';
    }
    document.body.classList.add('side-' + next);
    document.documentElement.classList.add('side-' + next);
    try {
        localStorage.setItem('narciana_nav_mode', next);
    } catch (e) {}
}

(function() {
    try {
        var saved = localStorage.getItem('narciana_nav_mode');
        if (saved && ['rail', 'expanded', 'collapsed'].indexOf(saved) !== -1) {
            document.body.classList.add('side-' + saved);
            document.documentElement.classList.add('side-' + saved);
        }
    } catch (e) {}

    // Remove preload-nav so transitions only fire upon subsequent user toggle interactions
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            document.documentElement.classList.remove('preload-nav');
            document.body.classList.remove('preload-nav');
        });
    });
})();

