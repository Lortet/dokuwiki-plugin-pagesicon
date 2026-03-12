<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_MEDIAMANAGER_URL_BASE')) define('DOKU_MEDIAMANAGER_URL_BASE', DOKU_BASE . 'lib/exe/mediamanager.php');

class action_plugin_pagesicon extends DokuWiki_Action_Plugin {
	public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, '_displaypageicon');
		$controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'setPageFavicon');
		$controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'addUploadFormScript');
		$controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'addFaviconRuntimeScript');
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleAction');
		$controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'renderAction');
		$controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addPageAction');
	}

	public function addPageAction(Doku_Event $event): void {
		global $ID;

		if (($event->data['view'] ?? '') !== 'page') return;
		if ($this->isActionDisabled('pagesicon')) return;
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
			public function __construct(string $targetPage, string $label, string $title) {
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
		return (int)$this->getConf('icon_size');
	}

	private function isActionDisabled(string $actionName): bool {
		global $conf;

		$disabled = explode(',', (string)($conf['disableactions'] ?? ''));
		$disabled = array_map(static function ($value) {
			return strtolower(trim((string)$value));
		}, $disabled);
		$actionName = strtolower(trim($actionName));
		if ($actionName === '') return false;

		return in_array($actionName, $disabled, true);
	}

	private function isLayoutIncludePage(string $pageID): bool {
		// DokuWiki may temporarily switch $ID while rendering layout includes such as
		// sidebar/footer. In these hooks we have no reliable "main content only" flag,
		// so we explicitly ignore those technical pages to avoid replacing the current
		// page icon/favicon with the one from the layout include.
		return $pageID === 'sidebar' || $pageID === 'footer';
	}

	public function setPageFavicon(Doku_Event $event): void {
		global $ACT, $ID;

		if (!(bool)$this->getConf('show_as_favicon')) return;
		if ($ACT !== 'show') return;

		$pageID = noNS((string)$ID);
		if ($this->isLayoutIncludePage($pageID)) return;

		$helper = plugin_load('helper', 'pagesicon');
		if (!$helper) return;

		$namespace = getNS((string)$ID);
		$favicon = $helper->getPageIconUrl($namespace, $pageID, 'smallorbig', ['w' => 32]);
		if (!$favicon) return;
		$favicon = html_entity_decode((string)$favicon, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
		$links[] = ['rel' => 'shortcut icon', 'href' => $favicon]; // Kept for legacy browser compatibility.
		$event->data['link'] = $links;
	}

	public function addUploadFormScript(Doku_Event $event): void {
		global $ACT;

		if ($ACT !== 'pagesicon') return;

		if (!isset($event->data['script']) || !is_array($event->data['script'])) {
			$event->data['script'] = [];
		}

		$event->data['script'][] = [
			'type' => 'text/javascript',
			'src' => DOKU_BASE . 'lib/plugins/pagesicon/script/upload-form.js',
			'_data' => 'pagesicon-upload-form',
		];
	}

	public function addFaviconRuntimeScript(Doku_Event $event): void {
		global $ACT;

		if (!(bool)$this->getConf('show_as_favicon')) return;
		if ($ACT !== 'show') return;

		if (!isset($event->data['script']) || !is_array($event->data['script'])) {
			$event->data['script'] = [];
		}

		$event->data['script'][] = [
			'type' => 'text/javascript',
			'src' => DOKU_BASE . 'lib/plugins/pagesicon/script/favicon-runtime.js',
			'_data' => 'pagesicon-favicon-runtime',
		];
	}

	private function hasIconAlready(string $html, string $mediaID): bool {
		return strpos($html, 'class="pagesicon-injected"') !== false;
	}

	private function injectFaviconRuntimeScript(string &$html, string $faviconHref): void {
		if ($faviconHref === '') return;
		$faviconHref = html_entity_decode($faviconHref, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		$marker = '<span class="pagesicon-favicon-runtime" data-href="' . hsc($faviconHref) . '" hidden></span>';
		$html = $marker . $html;
	}

	private function canUploadToTarget(string $targetPage): bool {
		if ($targetPage === '') return false;
		return auth_quickaclcheck($targetPage) >= AUTH_UPLOAD;
	}

	private function getDefaultTarget(): string {
		global $ID;
		return cleanID((string)$ID);
	}

	private function getDefaultVariant(): string {
		global $INPUT;
		$defaultVariant = strtolower($INPUT->str('icon_variant'));
		if (!in_array($defaultVariant, ['big', 'small'], true)) {
			$defaultVariant = 'big';
		}
		return $defaultVariant;
	}

	private function getPostedBaseName(array $choices): string {
		global $INPUT;
		/** @var helper_plugin_pagesicon|null $helper */
		$helper = plugin_load('helper', 'pagesicon');
		$selected = $helper ? $helper->normalizeIconBaseName($INPUT->post->str('icon_filename')) : '';
		if ($selected !== '' && isset($choices[$selected])) return $selected;
		return (string)array_key_first($choices);
	}

	private function getMediaManagerUrl(string $targetPage): string {
		$namespace = getNS($targetPage);
		return DOKU_MEDIAMANAGER_URL_BASE . '?ns=' . rawurlencode($namespace);
	}

	private function renderCurrentIconPreview(string $mediaID, string $defaultTarget, string $actionPage, int $previewSize): void {
		echo '<a href="' . hsc($this->getMediaManagerUrl($defaultTarget)) . '" target="_blank" title="' . hsc($this->getLang('open_media_manager')) . '">';
		echo '<img src="' . ml($mediaID, ['w' => $previewSize]) . '" alt="" width="' . $previewSize . '" style="display:block;margin:6px 0;" />';
		echo '</a>';
		echo '<small>' . hsc(noNS($mediaID)) . '</small>';
		echo '<form action="' . wl($actionPage) . '" method="post" style="margin-top:6px;">';
		formSecurityToken();
		echo '<input type="hidden" name="do" value="pagesicon" />';
		echo '<input type="hidden" name="media_id" value="' . hsc($mediaID) . '" />';
		echo '<input type="hidden" name="pagesicon_delete_submit" value="1" />';
		echo '<button type="submit" class="button">' . hsc($this->getLang('delete_icon')) . '</button>';
		echo '</form>';
	}

	private function handleDeletePost(): void {
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
		$currentBig = ($helper && method_exists($helper, 'getPageIconId')) ? (string)$helper->getPageIconId($namespace, $pageID, 'big') : '';
		$currentSmall = ($helper && method_exists($helper, 'getPageIconId')) ? (string)$helper->getPageIconId($namespace, $pageID, 'small') : '';
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

		if ($helper) {
			$helper->notifyIconUpdated($targetPage, 'delete', $mediaID);
		}
		msg(sprintf($this->getLang('delete_success'), hsc($mediaID)), 1);
	}

	private function handleUploadPost(): void {
		global $INPUT, $ID, $conf;

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

		$helper = plugin_load('helper', 'pagesicon');
		$allowed = ($helper && method_exists($helper, 'getConfiguredExtensions'))
			? $helper->getConfiguredExtensions()
			: [];
		if (!in_array($ext, $allowed, true)) {
			msg(sprintf($this->getLang('error_extension_not_allowed'), hsc($ext), hsc(implode(', ', $allowed))), -1);
			return;
		}

		$choices = ($helper && method_exists($helper, 'getUploadNameChoices'))
			? $helper->getUploadNameChoices($targetPage, $variant)
			: [];
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

		@chmod($targetFile, $conf['fmode']);
		if ($helper) {
			$helper->notifyIconUpdated($targetPage, 'upload', $mediaID);
		}
		msg(sprintf($this->getLang('upload_success'), hsc($mediaID)), 1);
	}

	private function renderUploadForm(): void {
		global $ID, $INPUT;

		$defaultTarget = $this->getDefaultTarget();
		$defaultVariant = $this->getDefaultVariant();
		$helper = plugin_load('helper', 'pagesicon');
		$allowed = ($helper && method_exists($helper, 'getConfiguredExtensions'))
			? implode(', ', $helper->getConfiguredExtensions())
			: '';
		$currentChoices = ($helper && method_exists($helper, 'getUploadNameChoices'))
			? $helper->getUploadNameChoices($defaultTarget, $defaultVariant)
			: [];
		$selectedBase = $helper ? $helper->normalizeIconBaseName($INPUT->str('icon_filename')) : '';
		if (!isset($currentChoices[$selectedBase])) {
			$selectedBase = (string)array_key_first($currentChoices);
		}
		$filenameHelp = hsc($this->getLang('icon_filename_help'));
		$actionPage = $defaultTarget !== '' ? $defaultTarget : cleanID((string)$ID);
		$namespace = getNS($defaultTarget);
		$pageID = noNS($defaultTarget);
		$previewSize = $this->getIconSize();
		$currentBig = ($helper && method_exists($helper, 'getPageIconId')) ? $helper->getPageIconId($namespace, $pageID, 'big') : false;
		$currentSmall = ($helper && method_exists($helper, 'getPageIconId')) ? $helper->getPageIconId($namespace, $pageID, 'small') : false;

		echo '<h1>' . hsc($this->getLang('menu')) . '</h1>';
		echo '<p>' . hsc($this->getLang('intro')) . '</p>';
		echo '<p><small>' . hsc(sprintf($this->getLang('allowed_extensions'), $allowed)) . '</small></p>';
		echo '<div class="pagesicon-current-preview" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;margin:10px 0 16px;">';
		echo '<div class="pagesicon-current-item">';
		echo '<strong>' . hsc($this->getLang('current_big_icon')) . '</strong><br />';
		if ($currentBig) {
			$this->renderCurrentIconPreview($currentBig, $defaultTarget, $actionPage, $previewSize);
		} else {
			echo '<small>' . hsc($this->getLang('current_icon_none')) . '</small>';
		}
		echo '</div>';
		echo '<div class="pagesicon-current-item">';
		echo '<strong>' . hsc($this->getLang('current_small_icon')) . '</strong><br />';
		if ($currentSmall) {
			$this->renderCurrentIconPreview($currentSmall, $defaultTarget, $actionPage, $previewSize);
		} else {
			echo '<small>' . hsc($this->getLang('current_icon_none')) . '</small>';
		}
		echo '</div>';
		echo '</div>';

		echo '<form action="' . wl($actionPage) . '" method="post" enctype="multipart/form-data"'
			. ' class="pagesicon-upload-form"'
			. ' data-page-name="' . hsc(noNS($defaultTarget)) . '"'
			. ' data-big-templates="' . hsc(json_encode($helper ? $helper->getVariantTemplates('big') : [])) . '"'
			. ' data-small-templates="' . hsc(json_encode($helper ? $helper->getVariantTemplates('small') : [])) . '">';
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
		if ($currentChoices) {
			echo '<select id="pagesicon_icon_filename" name="icon_filename" class="edit">';
			foreach ($currentChoices as $value => $label) {
				$selected = $value === $selectedBase ? ' selected="selected"' : '';
				echo '<option value="' . hsc($value) . '"' . $selected . '>' . hsc($label) . '</option>';
			}
			echo '</select>';
			echo '<br /><small>' . $filenameHelp . '</small>';
		} else {
			echo '<span class="error">' . hsc($this->getLang('error_no_filename_choices')) . '</span>';
		}
		echo '</td>';
		echo '</tr>';
		echo '</table></div>';

		echo '<p><button type="submit" class="button">' . hsc($this->getLang('upload_button')) . '</button></p>';
		echo '</form>';
	}
		
    public function _displaypageicon(Doku_Event &$event, $param) {
        global $ACT, $ID;

		if($ACT !== 'show') return;
		if(!(bool)$this->getConf('show_on_top')) return;

		$pageID = noNS($ID);
		if($this->isLayoutIncludePage($pageID)) return;

		$namespace = getNS($ID);
		$pageID = noNS((string)$ID);
		/** @var helper_plugin_pagesicon|null $helper */
		$helper = plugin_load('helper', 'pagesicon');
		if(!$helper) return;
		$sizeMode = $this->getIconSize() > 35 ? 'bigorsmall' : 'smallorbig';
		$logoMediaID = $helper->getPageIconId($namespace, $pageID, $sizeMode);
		if(!$logoMediaID) return;
		if($this->hasIconAlready($event->data, $logoMediaID)) return;

		$size = $this->getIconSize();
		$src = $helper->getPageIconUrl($namespace, $pageID, $sizeMode, ['w' => $size]);
		if(!$src) return;
		$iconHtml = '<img src="' . $src . '" class="media pagesicon-image" loading="lazy" alt="" width="' . $size . '" />';
		if ((bool)$this->getConf('show_as_favicon')) {
			$favicon = $helper->getPageIconUrl($namespace, $pageID, $sizeMode, ['w' => 32]);
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

	public function handleAction(Doku_Event $event): void {
		if ($event->data !== 'pagesicon') return;
		$event->preventDefault();
	}

	public function renderAction(Doku_Event $event): void {
		global $ACT;
		if ($ACT !== 'pagesicon') return;

		$this->handleDeletePost();
		$this->handleUploadPost();
		$this->renderUploadForm();

		$event->preventDefault();
		$event->stopPropagation();
	}
}
