<?php
if (!defined('DOKU_INC')) die();

class helper_plugin_pagesicon extends DokuWiki_Plugin
{
    private function getBundledDefaultImagePath(): string
    {
        return DOKU_INC . 'lib/plugins/pagesicon/images/default_image.png';
    }

    private function getBundledDefaultImageUrl(): string
    {
        $path = $this->getBundledDefaultImagePath();
        if (!@file_exists($path)) return '';

        $base = rtrim((string)DOKU_BASE, '/');
        $url = $base . '/lib/plugins/pagesicon/images/default_image.png';
        $mtime = @filemtime($path);
        return $this->appendVersionToUrl($url, $mtime ? (int)$mtime : 0);
    }

    private function getConfiguredDefaultImageMediaID()
    {
        $mediaID = cleanID((string)$this->getConf('default_image'));
        if ($mediaID === '') return false;
        if (!@file_exists(mediaFN($mediaID))) return false;
        return $mediaID;
    }

    private function getMediaMTime(string $mediaID): int
    {
        $mediaID = cleanID($mediaID);
        if ($mediaID === '') return 0;
        $file = mediaFN($mediaID);
        if (!@file_exists($file)) return 0;
        $mtime = @filemtime($file);
        return $mtime ? (int)$mtime : 0;
    }

    private function appendVersionToUrl(string $url, int $mtime): string
    {
        if ($url === '' || $mtime <= 0) return $url;
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . 'pi_ts=' . $mtime;
    }

    public function notifyIconUpdated(string $targetPage, string $action = 'update', string $mediaID = ''): void
    {
        global $conf;

        @io_saveFile($conf['cachedir'] . '/purgefile', time());

        $data = [
            'target_page' => cleanID($targetPage),
            'action' => $action,
            'media_id' => cleanID($mediaID),
        ];
        \dokuwiki\Extension\Event::createAndTrigger('PLUGIN_PAGESICON_UPDATED', $data);
    }

    private function buildConfiguredCandidatesFromRaw(string $raw, string $namespace, string $pageID): array
    {
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

    private function buildConfiguredCandidates(string $namespace, string $pageID, string $sizeMode): array
    {
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

    private function normalizeSizeMode(string $size): string
    {
        $size = strtolower(trim($size));
        $allowed = ['big', 'small', 'bigorsmall', 'smallorbig'];
        if (in_array($size, $allowed, true)) return $size;
        return 'bigorsmall';
    }

    private function getExtensions(): array
    {
        $raw = trim((string)$this->getConf('extensions'));
        if ($raw === '') return ['svg', 'png', 'jpg', 'jpeg'];

        $extensions = array_values(array_unique(array_filter(array_map(function ($ext) {
            return strtolower(ltrim(trim((string)$ext), '.'));
        }, explode(';', $raw)))));

        return $extensions ?: ['svg', 'png', 'jpg', 'jpeg'];
    }

    private function hasKnownExtension(string $name, array $extensions): bool
    {
        $fileExt = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        return $fileExt !== '' && in_array($fileExt, $extensions, true);
    }

    public function getPageIconId(
        string $namespace,
        string $pageID,
        string $size = 'bigorsmall'
    )
    {
        $sizeMode = $this->normalizeSizeMode($size);
        $extensions = $this->getExtensions();
        $namespace = $namespace ?: '';
        $pageBase = $namespace ? ($namespace . ':' . $pageID) : $pageID;
        $nsBase = $namespace ? ($namespace . ':') : '';

        $genericBig = [
            $pageBase,
            $pageBase . ':logo',
            $nsBase . 'logo',
        ];
        $genericSmall = [
            $pageBase . ':thumbnail',
            $nsBase . 'thumbnail',
        ];

        if ($sizeMode === 'big') {
            $generic = $genericBig;
        } elseif ($sizeMode === 'small') {
            $generic = $genericSmall;
        } elseif ($sizeMode === 'smallorbig') {
            $generic = array_merge($genericSmall, $genericBig);
        } else {
            $generic = array_merge($genericBig, $genericSmall);
        }

        $imageNames = array_merge($this->buildConfiguredCandidates($namespace, $pageID, $sizeMode), $generic);

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

    // Legacy alias kept for backward compatibility.
    public function getPageImage(
        string $namespace,
        string $pageID,
        string $size = 'bigorsmall',
        bool $withDefault = false
    ) {
        return $this->getPageIconId($namespace, $pageID, $size);
    }

    public function getUploadIconPage(string $targetPage = '')
    {
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

    public function getMediaIconId(string $mediaID, string $size = 'bigorsmall')
    {
        $mediaID = cleanID($mediaID);
        if ($mediaID === '') return false;

        $namespace = getNS($mediaID);
        $filename = noNS($mediaID);
        $base = (string)pathinfo($filename, PATHINFO_FILENAME);
        $pageID = cleanID($base);
        if ($pageID === '') return false;

        return $this->getPageIconId($namespace, $pageID, $size);
    }

    // Legacy alias kept for backward compatibility.
    public function getMediaImage(string $mediaID, string $size = 'bigorsmall', bool $withDefault = false)
    {
        return $this->getMediaIconId($mediaID, $size);
    }

    public function getDefaultIconUrl(array $params = ['width' => 55], ?int &$mtime = null)
    {
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

    // Legacy alias kept for backward compatibility.
    public function getDefaultImageIcon(array $params = ['width' => 55], ?int &$mtime = null)
    {
        return $this->getDefaultIconUrl($params, $mtime);
    }

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

    // Legacy alias kept for backward compatibility.
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

    // Legacy alias kept for backward compatibility.
    public function getMediaIcon(
        string $mediaID,
        string $size = 'bigorsmall',
        array $params = ['width' => 55],
        ?int &$mtime = null,
        bool $withDefault = false
    ) {
        return $this->getMediaIconUrl($mediaID, $size, $params, $mtime, $withDefault);
    }

    public function getUploadMediaIconPage(string $mediaID = '')
    {
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
