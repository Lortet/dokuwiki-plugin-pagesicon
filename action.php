<?php
if(!defined('DOKU_INC')) die();

class action_plugin_pagesicon extends DokuWiki_Action_Plugin {
	
	public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, '_displaypageicon');
		$controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'setPageFavicon');
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleAction');
		$controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'renderAction');
		$controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addPageAction');
	}

	public function addPageAction(Doku_Event $event): void
	{
		global $ID;

		if (($event->data['view'] ?? '') !== 'page') return;
		if (auth_quickaclcheck((string)$ID) < AUTH_UPLOAD) return;

		foreach (($event->data['items'] ?? []) as $item) {
			if ($item instanceof \dokuwiki\Menu\Item\AbstractItem && $item->getType() === 'pagesicon') {
				return;
			}
		}

		$label = (string)$this->getLang('page_action');
		if ($label === '') $label = 'Gerer l\'icone';
		$title = (string)$this->getLang('page_action_title');
		if ($title === '') $title = $label;
		$targetPage = cleanID((string)$ID);

		$event->data['items'][] = new class($targetPage, $label, $title) extends \dokuwiki\Menu\Item\AbstractItem {
			public function __construct(string $targetPage, string $label, string $title)
			{
				parent::__construct();
				$this->type = 'pagesicon';
				$this->id = $targetPage;
				$this->params = [
					'do' => 'pagesicon',
				];
				$this->label = $label;
				$this->title = $title;
				$this->svg = DOKU_INC . 'lib/images/menu/folder-multiple-image.svg';
			}
		};
	}

	private function getIconSize(): int {
		$size = (int)$this->getConf('icon_size');
		if($size < 8) return 55;
		if($size > 512) return 512;
		return $size;
	}

	public function setPageFavicon(Doku_Event $event): void
	{
		global $ACT, $ID;

		if (!(bool)$this->getConf('show_as_favicon')) return;
		if ($ACT !== 'show') return;

		$pageID = noNS((string)$ID);
		if ($pageID === 'sidebar' || $pageID === 'footer') return;

		$helper = plugin_load('helper', 'pagesicon');
		if (!$helper) return;

		$namespace = getNS((string)$ID);
		$iconMediaID = $helper->getPageImage($namespace, $pageID, 'smallorbig');
		if (!$iconMediaID) return;

		$favicon = html_entity_decode((string)ml($iconMediaID, ['w' => 32]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$favicon = $this->addVersionToUrl($favicon, $this->getMediaVersionStamp($iconMediaID), false);
		if (!$favicon) return;

		if (!isset($event->data['link']) || !is_array($event->data['link'])) {
			$event->data['link'] = [];
		}

		$links = [];
		foreach ($event->data['link'] as $link) {
			if (!is_array($link)) {
				$links[] = $link;
				continue;
			}

			$rels = $link['rel'] ?? '';
			if (!is_array($rels)) {
				$rels = preg_split('/\s+/', strtolower(trim((string)$rels))) ?: [];
			}
			$rels = array_filter(array_map('strtolower', (array)$rels));
			if (in_array('icon', $rels, true)) {
				continue;
			}
			$links[] = $link;
		}

		$links[] = ['rel' => 'icon', 'href' => $favicon];
		$links[] = ['rel' => 'shortcut icon', 'href' => $favicon];
		$event->data['link'] = $links;
	}

	private function hasIconAlready(string $html, string $mediaID): bool {
		return strpos($html, 'class="pagesicon-injected"') !== false;
	}

	private function getMediaVersionStamp(string $mediaID): string
	{
		$file = mediaFN($mediaID);
		if (!@file_exists($file)) return '';
		$mtime = @filemtime($file);
		if (!$mtime) return '';
		return (string)$mtime;
	}

	private function addVersionToUrl(string $url, string $version, bool $htmlEncodedAmp = true): string
	{
		if ($url === '' || $version === '') return $url;
		$sep = strpos($url, '?') === false ? '?' : ($htmlEncodedAmp ? '&amp;' : '&');
		return $url . $sep . 'pi_ts=' . rawurlencode($version);
	}

	private function injectFaviconRuntimeScript(string &$html, string $faviconHref): void
	{
		if ($faviconHref === '') return;

		$href = json_encode($faviconHref);
		$script = '<script>(function(){'
			. 'var href=' . $href . ';'
			. 'if(!href||!document.head)return;'
			. 'var links=document.head.querySelectorAll(\'link[rel*="icon"]\');'
			. 'for(var i=0;i<links.length;i++){links[i].parentNode.removeChild(links[i]);}'
			. 'var icon=document.createElement("link");icon.rel="icon";icon.href=href;document.head.appendChild(icon);'
			. 'var shortcut=document.createElement("link");shortcut.rel="shortcut icon";shortcut.href=href;document.head.appendChild(shortcut);'
			. '})();</script>';

		$html = $script . $html;
	}

	private function getAllowedExtensions(): array
	{
		$raw = trim((string)$this->getConf('extensions'));
		if ($raw === '') return ['svg', 'png', 'jpg', 'jpeg'];

		$extensions = array_values(array_unique(array_filter(array_map(function ($ext) {
			return strtolower(ltrim(trim((string)$ext), '.'));
		}, explode(';', $raw)))));

		return $extensions ?: ['svg', 'png', 'jpg', 'jpeg'];
	}

	private function canUploadToTarget(string $targetPage): bool
	{
		if ($targetPage === '') return false;
		return auth_quickaclcheck($targetPage) >= AUTH_UPLOAD;
	}

	private function getDefaultTarget(): string
	{
		global $ID;
		return cleanID((string)$ID);
	}

	private function getDefaultVariant(): string
	{
		global $INPUT;
		$defaultVariant = strtolower($INPUT->str('icon_variant'));
		if (!in_array($defaultVariant, ['big', 'small'], true)) {
			$defaultVariant = 'big';
		}
		return $defaultVariant;
	}

	private function getVariantTemplates(string $variant): array
	{
		$raw = $variant === 'small'
			? (string)$this->getConf('icon_thumbnail_name')
			: (string)$this->getConf('icon_name');

		$templates = array_values(array_unique(array_filter(array_map('trim', explode(';', $raw)))));
		if (!$templates) {
			return [$variant === 'small' ? 'thumbnail' : 'icon'];
		}
		return $templates;
	}

	private function normalizeBaseName(string $name): string
	{
		$name = trim($name);
		if ($name === '') return '';
		$name = noNS($name);
		$name = preg_replace('/\.[a-z0-9]+$/i', '', $name);
		$name = cleanID($name);
		return str_replace(':', '', $name);
	}

	private function getUploadNameChoices(string $targetPage, string $variant): array
	{
		$pageID = noNS($targetPage);
		$choices = [];

		foreach ($this->getVariantTemplates($variant) as $tpl) {
			$resolved = str_replace('~pagename~', $pageID, $tpl);
			$base = $this->normalizeBaseName($resolved);
			if ($base === '') continue;
			$choices[$base] = $base . '.ext';
		}

		if (!$choices) {
			$fallback = $variant === 'small' ? 'thumbnail' : 'icon';
			$choices[$fallback] = $fallback . '.ext';
		}

		return $choices;
	}

	private function getPostedBaseName(array $choices): string
	{
		global $INPUT;
		$selected = $this->normalizeBaseName($INPUT->post->str('icon_filename'));
		if ($selected !== '' && isset($choices[$selected])) return $selected;
		return (string)array_key_first($choices);
	}

	private function getMediaManagerUrl(string $targetPage): string
	{
		$namespace = getNS($targetPage);
		return DOKU_BASE . 'lib/exe/mediamanager.php?ns=' . rawurlencode($namespace);
	}

	private function notifyIconUpdated(string $targetPage, string $action, string $mediaID = ''): void
	{
		/** @var helper_plugin_pagesicon|null $helper */
		$helper = plugin_load('helper', 'pagesicon');
		if ($helper && method_exists($helper, 'notifyIconUpdated')) {
			$helper->notifyIconUpdated($targetPage, $action, $mediaID);
			return;
		}

		global $conf;
		@io_saveFile($conf['cachedir'] . '/purgefile', time());
		$data = [
			'target_page' => cleanID($targetPage),
			'action' => $action,
			'media_id' => cleanID($mediaID),
		];
		\dokuwiki\Extension\Event::createAndTrigger('PLUGIN_PAGESICON_UPDATED', $data);
	}

	private function handleDeletePost(): void
	{
		global $INPUT, $ID;

		if (!$INPUT->post->has('pagesicon_delete_submit')) return;
		if (!checkSecurityToken()) return;

		$targetPage = cleanID((string)$ID);
		$mediaID = cleanID($INPUT->post->str('media_id'));

		if ($targetPage === '' || $mediaID === '') {
			msg($this->getLang('error_delete_invalid'), -1);
			return;
		}
		if (!$this->canUploadToTarget($targetPage)) {
			msg($this->getLang('error_no_upload_permission'), -1);
			return;
		}
		$namespace = getNS($targetPage);
		$pageID = noNS($targetPage);
		$helper = plugin_load('helper', 'pagesicon');
		$currentBig = ($helper && method_exists($helper, 'getPageImage')) ? (string)$helper->getPageImage($namespace, $pageID, 'big') : '';
		$currentSmall = ($helper && method_exists($helper, 'getPageImage')) ? (string)$helper->getPageImage($namespace, $pageID, 'small') : '';
		$allowed = array_values(array_filter(array_unique([$currentBig, $currentSmall])));
		if (!$allowed || !in_array($mediaID, $allowed, true)) {
			msg($this->getLang('error_delete_invalid'), -1);
			return;
		}

		$file = mediaFN($mediaID);
		if (!@file_exists($file)) {
			msg($this->getLang('error_delete_not_found'), -1);
			return;
		}
		if (!@unlink($file)) {
			msg($this->getLang('error_delete_failed'), -1);
			return;
		}

		$this->notifyIconUpdated($targetPage, 'delete', $mediaID);
		msg(sprintf($this->getLang('delete_success'), hsc($mediaID)), 1);
	}

	private function handleUploadPost(): void
	{
		global $INPUT, $ID;

		if (!$INPUT->post->has('pagesicon_upload_submit')) return;
		if (!checkSecurityToken()) return;

		$targetPage = cleanID((string)$ID);
		if (!$this->canUploadToTarget($targetPage)) {
			msg($this->getLang('error_no_upload_permission'), -1);
			return;
		}

		$variant = strtolower($INPUT->post->str('icon_variant'));
		if (!in_array($variant, ['big', 'small'], true)) {
			$variant = 'big';
		}

		if (!isset($_FILES['pagesicon_file']) || !is_array($_FILES['pagesicon_file'])) {
			msg($this->getLang('error_missing_file'), -1);
			return;
		}

		$upload = $_FILES['pagesicon_file'];
		if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			msg($this->getLang('error_upload_failed') . ' (' . (int)($upload['error'] ?? -1) . ')', -1);
			return;
		}

		$originalName = (string)($upload['name'] ?? '');
		$tmpName = (string)($upload['tmp_name'] ?? '');
		if ($tmpName === '' || !is_uploaded_file($tmpName)) {
			msg($this->getLang('error_upload_failed'), -1);
			return;
		}

		$ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
		if ($ext === '') {
			msg($this->getLang('error_extension_missing'), -1);
			return;
		}

		$allowed = $this->getAllowedExtensions();
		if (!in_array($ext, $allowed, true)) {
			msg(sprintf($this->getLang('error_extension_not_allowed'), hsc($ext), hsc(implode(', ', $allowed))), -1);
			return;
		}

		$choices = $this->getUploadNameChoices($targetPage, $variant);
		$base = $this->getPostedBaseName($choices);
		$namespace = getNS($targetPage);
		$mediaBase = $namespace !== '' ? ($namespace . ':' . $base) : $base;
		$mediaID = cleanID($mediaBase . '.' . $ext);
		$targetFile = mediaFN($mediaID);

		io_makeFileDir($targetFile);
		if (!@is_dir(dirname($targetFile))) {
			msg($this->getLang('error_write_dir'), -1);
			return;
		}

		$moved = @move_uploaded_file($tmpName, $targetFile);
		if (!$moved) {
			$moved = @copy($tmpName, $targetFile);
		}
		if (!$moved) {
			msg($this->getLang('error_write_file'), -1);
			return;
		}

		@chmod($targetFile, 0664);
		$this->notifyIconUpdated($targetPage, 'upload', $mediaID);
		msg(sprintf($this->getLang('upload_success'), hsc($mediaID)), 1);
	}

	private function renderUploadForm(): void
	{
		global $ID, $INPUT;

		$defaultTarget = $this->getDefaultTarget();
		$defaultVariant = $this->getDefaultVariant();
		$allowed = implode(', ', $this->getAllowedExtensions());
		$currentChoices = $this->getUploadNameChoices($defaultTarget, $defaultVariant);
		$selectedBase = $this->normalizeBaseName($INPUT->str('icon_filename'));
		if (!isset($currentChoices[$selectedBase])) {
			$selectedBase = (string)array_key_first($currentChoices);
		}
		$bigTemplates = json_encode($this->getVariantTemplates('big'));
		$smallTemplates = json_encode($this->getVariantTemplates('small'));
		$filenameHelp = hsc($this->getLang('icon_filename_help'));
		$actionPage = $defaultTarget !== '' ? $defaultTarget : cleanID((string)$ID);
		$namespace = getNS($defaultTarget);
		$pageID = noNS($defaultTarget);
		$helper = plugin_load('helper', 'pagesicon');
		$currentBig = ($helper && method_exists($helper, 'getPageImage')) ? $helper->getPageImage($namespace, $pageID, 'big') : false;
		$currentSmall = ($helper && method_exists($helper, 'getPageImage')) ? $helper->getPageImage($namespace, $pageID, 'small') : false;

		echo '<h1>' . hsc($this->getLang('menu')) . '</h1>';
		echo '<p>' . hsc($this->getLang('intro')) . '</p>';
		echo '<p><small>' . hsc(sprintf($this->getLang('allowed_extensions'), $allowed)) . '</small></p>';
		echo '<div class="pagesicon-current-preview" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;margin:10px 0 16px;">';
		echo '<div class="pagesicon-current-item">';
		echo '<strong>' . hsc($this->getLang('current_big_icon')) . '</strong><br />';
		if ($currentBig) {
			echo '<a href="' . hsc($this->getMediaManagerUrl($defaultTarget)) . '" target="_blank" title="' . hsc($this->getLang('open_media_manager')) . '">';
			echo '<img src="' . ml($currentBig, ['w' => 55]) . '" alt="" width="55" style="display:block;margin:6px 0;" />';
			echo '</a>';
			echo '<small>' . hsc(noNS($currentBig)) . '</small>';
			echo '<form action="' . wl($actionPage) . '" method="post" style="margin-top:6px;">';
			formSecurityToken();
			echo '<input type="hidden" name="do" value="pagesicon" />';
			echo '<input type="hidden" name="media_id" value="' . hsc($currentBig) . '" />';
			echo '<input type="hidden" name="pagesicon_delete_submit" value="1" />';
			echo '<button type="submit" class="button">' . hsc($this->getLang('delete_icon')) . '</button>';
			echo '</form>';
		} else {
			echo '<small>' . hsc($this->getLang('current_icon_none')) . '</small>';
		}
		echo '</div>';
		echo '<div class="pagesicon-current-item">';
		echo '<strong>' . hsc($this->getLang('current_small_icon')) . '</strong><br />';
		if ($currentSmall) {
			echo '<a href="' . hsc($this->getMediaManagerUrl($defaultTarget)) . '" target="_blank" title="' . hsc($this->getLang('open_media_manager')) . '">';
			echo '<img src="' . ml($currentSmall, ['w' => 55]) . '" alt="" width="55" style="display:block;margin:6px 0;" />';
			echo '</a>';
			echo '<small>' . hsc(noNS($currentSmall)) . '</small>';
			echo '<form action="' . wl($actionPage) . '" method="post" style="margin-top:6px;">';
			formSecurityToken();
			echo '<input type="hidden" name="do" value="pagesicon" />';
			echo '<input type="hidden" name="media_id" value="' . hsc($currentSmall) . '" />';
			echo '<input type="hidden" name="pagesicon_delete_submit" value="1" />';
			echo '<button type="submit" class="button">' . hsc($this->getLang('delete_icon')) . '</button>';
			echo '</form>';
		} else {
			echo '<small>' . hsc($this->getLang('current_icon_none')) . '</small>';
		}
		echo '</div>';
		echo '</div>';

		echo '<form action="' . wl($actionPage) . '" method="post" enctype="multipart/form-data">';
		formSecurityToken();
		echo '<input type="hidden" name="do" value="pagesicon" />';
		echo '<input type="hidden" name="pagesicon_upload_submit" value="1" />';

		echo '<div class="table"><table class="inline">';
		echo '<tr>';
		echo '<td class="label"><label for="pagesicon_icon_variant">' . hsc($this->getLang('icon_variant')) . '</label></td>';
		echo '<td>';
		echo '<select id="pagesicon_icon_variant" name="icon_variant" class="edit">';
		echo '<option value="big"' . ($defaultVariant === 'big' ? ' selected="selected"' : '') . '>' . hsc($this->getLang('icon_variant_big')) . '</option>';
		echo '<option value="small"' . ($defaultVariant === 'small' ? ' selected="selected"' : '') . '>' . hsc($this->getLang('icon_variant_small')) . '</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<td class="label"><label for="pagesicon_file">' . hsc($this->getLang('file')) . '</label></td>';
		echo '<td><input type="file" id="pagesicon_file" name="pagesicon_file" class="edit" required /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<td class="label"><label for="pagesicon_icon_filename">' . hsc($this->getLang('icon_filename')) . '</label></td>';
		echo '<td>';
		echo '<select id="pagesicon_icon_filename" name="icon_filename" class="edit">';
		foreach ($currentChoices as $value => $label) {
			$selected = $value === $selectedBase ? ' selected="selected"' : '';
			echo '<option value="' . hsc($value) . '"' . $selected . '>' . hsc($label) . '</option>';
		}
		echo '</select>';
		echo '<br /><small>' . $filenameHelp . '</small>';
		echo '</td>';
		echo '</tr>';
		echo '</table></div>';

		echo '<p><button type="submit" class="button">' . hsc($this->getLang('upload_button')) . '</button></p>';
		echo '</form>';

		echo '<script>(function(){'
			. 'var variant=document.getElementById("pagesicon_icon_variant");'
			. 'var filename=document.getElementById("pagesicon_icon_filename");'
			. 'if(!variant||!filename)return;'
			. 'var pageName=' . json_encode(noNS($defaultTarget)) . ';'
			. 'var templates={big:' . $bigTemplates . ',small:' . $smallTemplates . '};'
			. 'function cleanBase(name){name=(name||"").trim();if(!name)return"";'
			. 'var parts=name.split(":");name=parts[parts.length-1]||"";'
			. 'name=name.replace(/\\.[a-z0-9]+$/i,"");'
			. 'name=name.replace(/[^a-zA-Z0-9_\\-]/g,"_").replace(/^_+|_+$/g,"");'
			. 'return name;}'
			. 'function updateChoices(){'
			. 'var selected=filename.value;filename.innerHTML="";'
			. 'var variantKey=(variant.value==="small")?"small":"big";'
			. 'var seen={};'
			. '(templates[variantKey]||[]).forEach(function(tpl){'
			. 'var resolved=String(tpl||"").replace(/~pagename~/g,pageName);'
			. 'var base=cleanBase(resolved);if(!base||seen[base])return;seen[base]=true;'
			. 'var opt=document.createElement("option");opt.value=base;opt.textContent=base+".ext";filename.appendChild(opt);'
			. '});'
			. 'if(!filename.options.length){var fb=variantKey==="small"?"thumbnail":"icon";'
			. 'var o=document.createElement("option");o.value=fb;o.textContent=fb+".ext";filename.appendChild(o);}'
			. 'for(var i=0;i<filename.options.length;i++){if(filename.options[i].value===selected){filename.selectedIndex=i;return;}}'
			. 'filename.selectedIndex=0;'
			. '}'
			. 'variant.addEventListener("change",updateChoices);'
			. 'updateChoices();'
			. '})();</script>';
	}
		
    public function _displaypageicon(Doku_Event &$event, $param) {
        global $ACT, $ID;

		if($ACT !== 'show') return;
		if(!(bool)$this->getConf('show_on_top')) return;

		$pageID = noNS($ID);
		if($pageID === 'sidebar' || $pageID === 'footer') return;

		$namespace = getNS($ID);
		$pageID = noNS((string)$ID);
		/** @var helper_plugin_pagesicon|null $helper */
		$helper = plugin_load('helper', 'pagesicon');
		if(!$helper) return;
		$sizeMode = $this->getIconSize() > 35 ? 'bigorsmall' : 'smallorbig';
		$logoMediaID = $helper->getPageImage($namespace, $pageID, $sizeMode);
		if(!$logoMediaID) return;
		if($this->hasIconAlready($event->data, $logoMediaID)) return;

		$size = $this->getIconSize();
		$src = (string)ml($logoMediaID, ['w' => $size]);
		$src = $this->addVersionToUrl($src, $this->getMediaVersionStamp($logoMediaID), true);
		if(!$src) return;
		$iconHtml = '<img src="' . $src . '" class="media pagesicon-image" loading="lazy" alt="" width="' . $size . '" />';
		if ((bool)$this->getConf('show_as_favicon')) {
			$favicon = html_entity_decode((string)ml($logoMediaID, ['w' => 32]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$favicon = $this->addVersionToUrl($favicon, $this->getMediaVersionStamp($logoMediaID), false);
			$this->injectFaviconRuntimeScript($event->data, $favicon);
		}

		$inlineIcon = '<span class="pagesicon-injected pagesicon-injected-inline">' . $iconHtml . '</span> ';
		$updated = preg_replace('/<h1\b([^>]*)>/i', '<h1$1>' . $inlineIcon, $event->data, 1, $count);
		if ($count > 0 && $updated !== null) {
			$event->data = $updated;
			return;
		}

		// Fallback: no H1 found, keep old behavior
		$event->data = '<div class="pagesicon-injected">' . $iconHtml . '</div>' . "\n" . $event->data;
	}

	public function handleAction(Doku_Event $event): void
	{
		if ($event->data !== 'pagesicon') return;
		$event->preventDefault();
	}

	public function renderAction(Doku_Event $event): void
	{
		global $ACT;
		if ($ACT !== 'pagesicon') return;

		$this->handleDeletePost();
		$this->handleUploadPost();
		$this->renderUploadForm();

		$event->preventDefault();
		$event->stopPropagation();
	}
}
