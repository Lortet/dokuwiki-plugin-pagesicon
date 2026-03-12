<?php
if (!defined('DOKU_INC')) die();

class helper_plugin_pagesicon extends DokuWiki_Plugin {
    private const BUNDLED_DEFAULT_IMAGE_RELATIVE_PATH = 'lib/plugins/pagesicon/images/default_image.png';

    private function getBundledDefaultImagePath(): string {
        return DOKU_INC . self::BUNDLED_DEFAULT_IMAGE_RELATIVE_PATH;
    }

    private function getBundledDefaultImageUrl(): string {
        $path = $this->getBundledDefaultImagePath();
        if (!@file_exists($path)) return '';

        $base = rtrim((string)DOKU_BASE, '/');
        $url = $base . '/' . self::BUNDLED_DEFAULT_IMAGE_RELATIVE_PATH;
        $mtime = @filemtime($path);
        return $this->appendVersionToUrl($url, $mtime ? (int)$mtime : 0);
    }

    private function getConfiguredDefaultImageMediaID() {
        $mediaID = cleanID((string)$this->getConf('default_image'));
        if ($mediaID === '') return false;
        if (!@file_exists(mediaFN($mediaID))) return false;
        return $mediaID;
    }

    private function getMediaMTime(string $mediaID): int {
        $mediaID = cleanID($mediaID);
        if ($mediaID === '') return 0;
        $file = mediaFN($mediaID);
        if (!@file_exists($file)) return 0;
        $mtime = @filemtime($file);
        return $mtime ? (int)$mtime : 0;
    }

    private function appendVersionToUrl(string $url, int $mtime): string {
        if ($url === '' || $mtime <= 0) return $url;
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . 'pi_ts=' . $mtime;
    }

    /**
     * Added in version 2026-03-06.
     * Notifies consumers that an icon changed and triggers cache invalidation hooks.
     */
    public function notifyIconUpdated(string $targetPage, string $action = 'update', string $mediaID = ''): void {
        global $conf;

        @io_saveFile($conf['cachedir'] . '/purgefile', time());

        $data = [
            'target_page' => cleanID($targetPage),
            'action' => $action,
            'media_id' => cleanID($mediaID),
        ];
        \dokuwiki\Extension\Event::createAndTrigger('PLUGIN_PAGESICON_UPDATED', $data);
    }

    /**
     * Added in version 2026-03-11.
     * Returns the configured filename templates for the requested icon variant.
     */
    public function getVariantTemplates(string $variant): array {
        $confKey = $variant === 'small' ? 'icon_thumbnail_name' : 'icon_name';
        $raw = (string)$this->getConf($confKey);

        if (trim($raw) === '') {
            trigger_error('pagesicon: missing required configuration "' . $confKey . '"', E_USER_WARNING);
            return [];
        }

        $templates = array_values(array_unique(array_filter(array_map('trim', explode(';', $raw)))));
        if (!$templates) {
            trigger_error('pagesicon: configuration "' . $confKey . '" does not contain any usable value', E_USER_WARNING);
        }

        return $templates;
    }

    /**
     * Added in version 2026-03-11.
     * Normalizes an icon filename candidate to its base media name without namespace or extension.
     */
    public function normalizeIconBaseName(string $name): string {
        $name = trim($name);
        if ($name === '') return '';
        $name = noNS($name);
        $name = preg_replace('/\.[a-z0-9]+$/i', '', $name);
        $name = cleanID($name);
        return str_replace(':', '', $name);
    }

    /**
     * Added in version 2026-03-11.
     * Returns the allowed target base names for an upload, indexed by their normalized value.
     */
    public function getUploadNameChoices(string $targetPage, string $variant): array {
        $pageID = noNS($targetPage);
        $choices = [];

        foreach ($this->getVariantTemplates($variant) as $tpl) {
            $resolved = str_replace('~pagename~', $pageID, $tpl);
            $base = $this->normalizeIconBaseName($resolved);
            if ($base === '') continue;
            $choices[$base] = $base . '.ext';
        }

        return $choices;
    }

    private function buildConfiguredCandidatesFromRaw(string $raw, string $namespace, string $pageID): array {
        $configured = [];
        $entries = array_filter(array_map('trim', explode(';', $raw)));

        foreach ($entries as $entry) {
            $name = str_replace('~pagename~', $pageID, $entry);
            if ($name === '') continue;

            if (strpos($name, ':') === false && $namespace !== '') {
                $configured[] = $namespace . ':' . $name;
            } else {
                $configured[] = ltrim($name, ':');
            }
        }

        return array_values(array_unique($configured));
    }

    private function buildConfiguredCandidates(string $namespace, string $pageID, string $sizeMode): array {
        $bigRaw = trim((string)$this->getConf('icon_name'));
        $smallRaw = trim((string)$this->getConf('icon_thumbnail_name'));

        $big = $this->buildConfiguredCandidatesFromRaw($bigRaw, $namespace, $pageID);
        $small = $this->buildConfiguredCandidatesFromRaw($smallRaw, $namespace, $pageID);

        if ($sizeMode === 'big') return $big;
        if ($sizeMode === 'small') return $small;
        if ($sizeMode === 'smallorbig') return array_values(array_unique(array_merge($small, $big)));

        // Default: bigorsmall
        return array_values(array_unique(array_merge($big, $small)));
    }

    private function normalizeSizeMode(string $size): string {
        $size = strtolower(trim($size));
        $allowed = ['big', 'small', 'bigorsmall', 'smallorbig'];
        if (in_array($size, $allowed, true)) return $size;
        return 'bigorsmall';
    }

    /**
     * Added in version 2026-03-11.
     * Returns the configured list of allowed icon file extensions.
     */
    public function getConfiguredExtensions(): array {
        $raw = trim((string)$this->getConf('extensions'));
        if ($raw === '') {
            trigger_error('pagesicon: missing required configuration "extensions"', E_USER_WARNING);
            return [];
        }

        $extensions = array_values(array_unique(array_filter(array_map(function ($ext) {
            return strtolower(ltrim(trim((string)$ext), '.'));
        }, explode(';', $raw)))));

        if (!$extensions) {
            trigger_error('pagesicon: configuration "extensions" does not contain any usable value', E_USER_WARNING);
        }

        return $extensions;
    }

    private function hasKnownExtension(string $name, array $extensions): bool {
        $fileExt = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        return $fileExt !== '' && in_array($fileExt, $extensions, true);
    }

    private function getParentFallbackMode(): string {
        $mode = strtolower(trim((string)$this->getConf('parent_fallback')));
        if ($mode !== 'direct' && $mode !== 'first') return 'none';
        return $mode;
    }

    private function resolveOwnPageIconId(string $namespace, string $pageID, string $sizeMode, array $extensions) {
        $imageNames = $this->buildConfiguredCandidates($namespace, $pageID, $sizeMode);

        foreach ($imageNames as $name) {
            if ($this->hasKnownExtension($name, $extensions)) {
                if (@file_exists(mediaFN($name))) return $name;
                continue;
            }

            foreach ($extensions as $ext) {
                $path = $name . '.' . $ext;
                if (@file_exists(mediaFN($path))) return $path;
            }
        }

        return false;
    }

    private function resolveNamespacePageIconId(string $namespace, string $sizeMode, array $extensions) {
        global $conf;

        $namespace = cleanID($namespace);
        if ($namespace === '') return false;

        $parentNamespace = (string)(getNS($namespace) ?: '');
        $pageID = noNS($namespace);

        $iconID = $this->resolveOwnPageIconId($parentNamespace, $pageID, $sizeMode, $extensions);
        if ($iconID) return $iconID;

        $leafPageID = cleanID($namespace . ':' . $pageID);
        if ($leafPageID !== '' && page_exists($leafPageID)) {
            $iconID = $this->resolveOwnPageIconId($namespace, $pageID, $sizeMode, $extensions);
            if ($iconID) return $iconID;
        }

        if (isset($conf['start'])) {
            $startId = cleanID((string)$conf['start']);
            if ($startId !== '') {
                $iconID = $this->resolveOwnPageIconId($namespace, $startId, $sizeMode, $extensions);
                if ($iconID) return $iconID;
            }
        }

        return false;
    }

    /**
     * Added in version 2026-03-09.
     * Resolves the icon media ID for a page, or false when no icon matches.
     * Replaces the older getPageImage() name.
     */
    public function getPageIconId(
        string $namespace,
        string $pageID,
        string $size = 'bigorsmall'
    )
    {
        $sizeMode = $this->normalizeSizeMode($size);
        $extensions = $this->getConfiguredExtensions();
        $iconID = $this->resolveOwnPageIconId($namespace, $pageID, $sizeMode, $extensions);
        if ($iconID) return $iconID;

        $fallbackMode = $this->getParentFallbackMode();
        if ($fallbackMode === 'none') return false;

        $currentNamespace = $namespace ?: '';
        while ($currentNamespace !== '') {
            $parentNamespace = (string)(getNS($currentNamespace) ?: '');
            $lookupNamespace = $parentNamespace !== '' ? $parentNamespace : $currentNamespace;
            $iconID = $this->resolveNamespacePageIconId($lookupNamespace, $sizeMode, $extensions);
            if ($iconID) return $iconID;
            if ($fallbackMode === 'direct' || $parentNamespace === '') break;
            $currentNamespace = $parentNamespace;
        }

        return false;
    }

    /**
     * Added in version 2026-03-06.
     * Deprecated since version 2026-03-09, kept for backward compatibility.
     * Use getPageIconId() instead.
     */
    public function getPageImage(
        string $namespace,
        string $pageID,
        string $size = 'bigorsmall',
        bool $withDefault = false
    ) {
        return $this->getPageIconId($namespace, $pageID, $size);
    }

    /**
     * Added in version 2026-03-06.
     * Returns the icon management URL for a page, or null when upload is not allowed.
     */
    public function getUploadIconPage(string $targetPage = '') {
        global $ID;

        $targetPage = cleanID($targetPage);
        if ($targetPage === '') {
            $targetPage = cleanID(getNS((string)$ID));
        }
        if ($targetPage === '') {
            $targetPage = cleanID((string)$ID);
        }
        if ($targetPage === '') return null;

        if (auth_quickaclcheck($targetPage) < AUTH_UPLOAD) {
            return null;
        }

        return wl($targetPage, ['do' => 'pagesicon']);
    }

    /**
     * Added in version 2026-03-09.
     * Resolves the icon media ID associated with a media file, or false when none matches.
     * Replaces the older getMediaImage() name.
     */
    public function getMediaIconId(string $mediaID, string $size = 'bigorsmall') {
        $mediaID = cleanID($mediaID);
        if ($mediaID === '') return false;

        $namespace = getNS($mediaID);
        $filename = noNS($mediaID);
        $base = (string)pathinfo($filename, PATHINFO_FILENAME);
        $pageID = cleanID($base);
        if ($pageID === '') return false;

        return $this->getPageIconId($namespace, $pageID, $size);
    }

    /**
     * Added in version 2026-03-06.
     * Deprecated since version 2026-03-09, kept for backward compatibility.
     * Use getMediaIconId() instead.
     */
    public function getMediaImage(string $mediaID, string $size = 'bigorsmall', bool $withDefault = false) {
        return $this->getMediaIconId($mediaID, $size);
    }

    private function matchesPageIconVariant(string $mediaID, string $namespace, string $pageID): bool {
        $bigIconID = $this->getPageIconId($namespace, $pageID, 'big');
        if ($bigIconID && cleanID((string)$bigIconID) === $mediaID) return true;

        $smallIconID = $this->getPageIconId($namespace, $pageID, 'small');
        if ($smallIconID && cleanID((string)$smallIconID) === $mediaID) return true;

        return false;
    }

    /**
     * Added in version 2026-03-11.
     * Checks whether a media ID should be considered a page icon managed by the pagesicon plugin.
     */
    public function isPageIconMedia(string $mediaID): bool {
        global $conf;

        $mediaID = cleanID($mediaID);
        if ($mediaID === '') return false;

        $namespace = getNS($mediaID);
        $filename = noNS($mediaID);
        $basename = cleanID((string)pathinfo($filename, PATHINFO_FILENAME));
        if ($basename === '') return false;

        // Case 1: this media is the big or small icon selected for a page with the same base name.
        $sameNamePageID = $namespace !== '' ? ($namespace . ':' . $basename) : $basename;
        if (page_exists($sameNamePageID)) {
            if ($this->matchesPageIconVariant($mediaID, $namespace, $basename)) return true;
        }

        // Case 2: this media is the big or small icon selected for a page whose ID matches the namespace.
        if ($namespace !== '' && page_exists($namespace)) {
            $parentNamespace = getNS($namespace);
            $pageID = noNS($namespace);
            if ($this->matchesPageIconVariant($mediaID, $parentNamespace, $pageID)) return true;
        }

        // Case 3: this media is the big or small icon selected for a page whose ID
        // matches the namespace leaf, for example "...:playground:playground".
        if ($namespace !== '') {
            $namespaceLeaf = noNS($namespace);
            $leafPageID = cleanID($namespace . ':' . $namespaceLeaf);
            if ($leafPageID !== '' && page_exists($leafPageID)) {
                if ($this->matchesPageIconVariant($mediaID, $namespace, $namespaceLeaf)) return true;
            }
        }

        // Case 4: this media is the big or small icon selected for the namespace start page
        // (for example "...:start"), which often carries the visible page content.
        if ($namespace !== '' && isset($conf['start'])) {
            $startId = cleanID((string)$conf['start']);
            $startPage = $startId !== '' ? cleanID($namespace . ':' . $startId) : '';
            if ($startPage !== '' && page_exists($startPage)) {
                if ($this->matchesPageIconVariant($mediaID, $namespace, noNS($startPage))) return true;
            }
        }

        return false;
    }

    /**
     * Added in version 2026-03-09.
     * Returns the configured default icon URL, or the bundled fallback image when available.
     */
    public function getDefaultIconUrl(array $params = ['width' => 55], ?int &$mtime = null) {
        $mediaID = $this->getConfiguredDefaultImageMediaID();
        if ($mediaID) {
            $mtime = $this->getMediaMTime((string)$mediaID);
            $url = (string)ml((string)$mediaID, $params);
            if ($url === '') return false;
            return $this->appendVersionToUrl($url, $mtime);
        }

        $mtime = 0;
        $bundled = $this->getBundledDefaultImageUrl();
        if ($bundled !== '') return $bundled;

        return false;
    }

    /**
     * Added in version 2026-03-09.
     * Deprecated since version 2026-03-09, kept for backward compatibility.
     * Use getDefaultIconUrl() instead.
     */
    public function getDefaultImageIcon(array $params = ['width' => 55], ?int &$mtime = null) {
        return $this->getDefaultIconUrl($params, $mtime);
    }

    /**
     * Added in version 2026-03-09.
     * Returns a versioned icon URL for a page, or false when no icon matches.
     * Replaces the older getImageIcon() name.
     */
    public function getPageIconUrl(
        string $namespace,
        string $pageID,
        string $size = 'bigorsmall',
        array $params = ['width' => 55],
        ?int &$mtime = null,
        bool $withDefault = false
    ) {
        $mediaID = $this->getPageIconId($namespace, $pageID, $size);
        if (!$mediaID) {
            if ($withDefault) {
                return $this->getDefaultIconUrl($params, $mtime);
            }
            $mtime = 0;
            return false;
        }

        $mtime = $this->getMediaMTime((string)$mediaID);
        $url = (string)ml((string)$mediaID, $params);
        if ($url === '') return false;
        return $this->appendVersionToUrl($url, $mtime);
    }

    /**
     * Added in version 2026-03-06.
     * Deprecated since version 2026-03-09, kept for backward compatibility.
     * Use getPageIconUrl() instead.
     */
    public function getImageIcon(
        string $namespace,
        string $pageID,
        string $size = 'bigorsmall',
        array $params = ['width' => 55],
        ?int &$mtime = null,
        bool $withDefault = false
    ) {
        return $this->getPageIconUrl($namespace, $pageID, $size, $params, $mtime, $withDefault);
    }

    /**
     * Added in version 2026-03-09.
     * Returns a versioned icon URL for a media file, or false when no icon matches.
     * Replaces the older getMediaIcon() name.
     */
    public function getMediaIconUrl(
        string $mediaID,
        string $size = 'bigorsmall',
        array $params = ['width' => 55],
        ?int &$mtime = null,
        bool $withDefault = false
    ) {
        $iconMediaID = $this->getMediaIconId($mediaID, $size);
        if (!$iconMediaID) {
            if ($withDefault) {
                return $this->getDefaultIconUrl($params, $mtime);
            }
            $mtime = 0;
            return false;
        }

        $mtime = $this->getMediaMTime((string)$iconMediaID);
        $url = (string)ml((string)$iconMediaID, $params);
        if ($url === '') return false;
        return $this->appendVersionToUrl($url, $mtime);
    }

    /**
     * Added in version 2026-03-06.
     * Deprecated since version 2026-03-09, kept for backward compatibility.
     * Use getMediaIconUrl() instead.
     */
    public function getMediaIcon(
        string $mediaID,
        string $size = 'bigorsmall',
        array $params = ['width' => 55],
        ?int &$mtime = null,
        bool $withDefault = false
    ) {
        return $this->getMediaIconUrl($mediaID, $size, $params, $mtime, $withDefault);
    }

    /**
     * Added in version 2026-03-06.
     * Returns the icon management URL associated with a media file, or null when unavailable.
     */
    public function getUploadMediaIconPage(string $mediaID = '') {
        $mediaID = cleanID($mediaID);
        if ($mediaID === '') return null;

        $namespace = getNS($mediaID);
        $filename = noNS($mediaID);
        $base = (string)pathinfo($filename, PATHINFO_FILENAME);
        $targetPage = cleanID($namespace !== '' ? ($namespace . ':' . $base) : $base);
        if ($targetPage === '') return null;

        return $this->getUploadIconPage($targetPage);
    }
}
