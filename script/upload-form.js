(function () {
    function parseTemplates(raw) {
        if (!raw) return [];
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function cleanBase(name) {
        name = String(name || '').trim();
        if (!name) return '';

        var parts = name.split(':');
        name = parts[parts.length - 1] || '';
        name = name.replace(/\.[a-z0-9]+$/i, '');
        name = name.replace(/[^a-zA-Z0-9_\-]/g, '_').replace(/^_+|_+$/g, '');
        return name;
    }

    function updateChoices(variant, filename, pageName, templates) {
        var selected = filename.value;
        filename.innerHTML = '';

        var variantKey = variant.value === 'small' ? 'small' : 'big';
        var seen = {};

        (templates[variantKey] || []).forEach(function (tpl) {
            var resolved = String(tpl || '').replace(/~pagename~/g, pageName);
            var base = cleanBase(resolved);
            if (!base || seen[base]) return;

            seen[base] = true;
            var option = document.createElement('option');
            option.value = base;
            option.textContent = base + '.ext';
            filename.appendChild(option);
        });

        for (var i = 0; i < filename.options.length; i++) {
            if (filename.options[i].value === selected) {
                filename.selectedIndex = i;
                return;
            }
        }

        filename.selectedIndex = filename.options.length ? 0 : -1;
    }

    function initForm(form) {
        var variant = form.querySelector('#pagesicon_icon_variant');
        var filename = form.querySelector('#pagesicon_icon_filename');
        if (!variant || !filename) return;

        var pageName = form.dataset.pageName || '';
        var templates = {
            big: parseTemplates(form.dataset.bigTemplates),
            small: parseTemplates(form.dataset.smallTemplates)
        };

        variant.addEventListener('change', function () {
            updateChoices(variant, filename, pageName, templates);
        });

        updateChoices(variant, filename, pageName, templates);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.pagesicon-upload-form').forEach(initForm);
    });
})();
