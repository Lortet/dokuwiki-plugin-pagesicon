(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var meta = document.querySelector('meta[name="pagesicon-favicon"]');
        if (!meta) return;

        var href = meta.getAttribute('content');
        if (!href) return;

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
    });
})();
