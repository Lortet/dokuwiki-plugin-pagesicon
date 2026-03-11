(function () {
    function replaceFavicon(href) {
        if (!href || !document.head) return;

        var links = document.head.querySelectorAll('link[rel*="icon"]');
        for (var i = 0; i < links.length; i++) {
            if (links[i].parentNode) {
                links[i].parentNode.removeChild(links[i]);
            }
        }

        var icon = document.createElement('link');
        icon.rel = 'icon';
        icon.href = href;
        document.head.appendChild(icon);

        var shortcut = document.createElement('link');
        shortcut.rel = 'shortcut icon';
        shortcut.href = href;
        document.head.appendChild(shortcut);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var markers = document.querySelectorAll('.pagesicon-favicon-runtime[data-href]');
        if (!markers.length) return;

        var lastMarker = markers[markers.length - 1];
        replaceFavicon(lastMarker.getAttribute('data-href') || '');
    });
})();
