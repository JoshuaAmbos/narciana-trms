// Narciana TRMS - Responsive sidebar toggle (drawer / rail / full)
function toggleSidebarWidth() {
    document.documentElement.classList.remove('preload-nav');
    document.body.classList.remove('preload-nav');
    document.body.classList.remove('nav-open');

    var width = window.innerWidth;
    var isLandscapeTablet = width >= 1024 && width <= 1280;
    var isPortrait = width < 1024;

    var current = 'default';
    if (document.body.classList.contains('side-expanded')) current = 'expanded';
    else if (document.body.classList.contains('side-rail')) current = 'rail';
    else if (document.body.classList.contains('side-collapsed')) current = 'collapsed';
    else current = (isLandscapeTablet || isPortrait) ? 'rail' : 'expanded';

    document.body.classList.remove('side-rail', 'side-expanded', 'side-collapsed');
    document.documentElement.classList.remove('side-rail', 'side-expanded', 'side-collapsed');

    var next;
    if (current === 'rail') {
        next = 'expanded';
    } else if (current === 'expanded') {
        next = 'collapsed';
    } else {
        next = isPortrait ? 'expanded' : 'rail';
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

/**
 * Generic Real-Time Client-Side Filter
 * @param {string} inputId - ID of the text input
 * @param {string} clearBtnId - ID of the clear (×) button
 * @param {string} itemSelector - CSS selector for target rows/cards
 * @param {string} [emptyMsgId] - Optional ID for empty state placeholder
 */
function initRealtimeSearch(inputId, clearBtnId, itemSelector, emptyMsgId = null) {
    const input = document.getElementById(inputId);
    const clearBtn = document.getElementById(clearBtnId);
    if (!input) return;

    function executeFilter() {
        const query = (input.value || '').toLowerCase().trim();
        const items = document.querySelectorAll(itemSelector);
        let matchCount = 0;

        items.forEach(el => {
            const searchData = (el.getAttribute('data-search') || el.textContent || '').toLowerCase();
            const matches = !query || searchData.includes(query);
            el.style.display = matches ? '' : 'none';
            if (matches) matchCount++;
        });

        if (clearBtn) {
            clearBtn.style.display = query ? 'inline-flex' : 'none';
        }

        if (emptyMsgId) {
            const emptyEl = document.getElementById(emptyMsgId);
            if (emptyEl) emptyEl.style.display = matchCount === 0 ? 'block' : 'none';
        }
    }

    input.addEventListener('input', executeFilter);

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            input.value = '';
            executeFilter();
            input.focus();
        });
    }

    // Run initial filter if loaded with pre-filled query
    if (input.value) {
        executeFilter();
    }
}

