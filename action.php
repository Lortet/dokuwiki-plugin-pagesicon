<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_MEDIAMANAGER_URL_BASE')) define('DOKU_MEDIAMANAGER_URL_BASE', DOKU_BASE . 'lib/exe/mediamanager.php');

class action_plugin_pagesicon extends DokuWiki_Action_Plugin {
	public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE', $this, 'displayPageIcon');
		$controller->register_hook('RENDERER_CONTENT_POSTPROCESS', 'AFTER', $this, 'injectLinkIcons');
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

	private function isLayoutIncludePage(): bool {
		global $ID, $INFO;
		// DokuWiki populates $INFO['id'] once for the originally requested page, but
		// temporarily changes $ID while rendering layout includes (sidebar, footer, …)
		// via tpl_include_page(). Comparing them detects any layout include without
		// having to hardcode page names.
		return isset($INFO['id']) && (string)$ID !== (string)$INFO['id'];
	}

	public function setPageFavicon(Doku_Event $event): void {
		global $ACT, $ID;

		if (!(bool)$this->getConf('show_as_favicon')) return;
		if ($ACT !== 'show') return;

		if ($this->isLayoutIncludePage()) return;

		$helper = plugin_load('helper', 'pagesicon');
		if (!$helper) return;

		$namespace = getNS((string)$ID);
		$pageID = noNS((string)$ID);
		$size = $this->getIconSize();
		$sizeMode = $size > 35 ? 'bigorsmall' : 'smallorbig';
		$favicon = $helper->getPageIconUrl($namespace, $pageID, $sizeMode, ['w' => $size]);
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

		if (!isset($event->data['meta']) || !is_array($event->data['meta'])) {
			$event->data['meta'] = [];
		}
		$event->data['meta'][] = ['name' => 'pagesicon-favicon', 'content' => $favicon];
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

	private function hasIconAlready(string $html): bool {
		return strpos($html, 'class="pagesicon-injected"') !== false;
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

	private function getMediaManagerUrl(string $mediaID): string {
		$namespace = getNS($mediaID);
		return DOKU_MEDIAMANAGER_URL_BASE . '?ns=' . rawurlencode($namespace);
	}

	private function getFallbackModeLabel(string $mode): string {
		$langKey = 'fallback_mode_' . $mode;
		$label = (string)$this->getLang($langKey);
		if ($label !== '' && $label !== $langKey) return $label;

		if ($mode === 'direct') return 'Direct parent only';
		if ($mode === 'first') return 'First icon found while walking up';
		return 'No fallback';
	}

	private function getIconOriginLabel(array $details): string {
		$origin = (string)($details['origin'] ?? '');
		$sourcePage = (string)($details['source_page'] ?? '');

		if ($origin === 'current_page') {
			return (string)$this->getLang('icon_origin_current_page');
		}

		if ($origin === 'direct_parent') {
			return sprintf((string)$this->getLang('icon_origin_direct_parent'), $sourcePage);
		}

		if ($origin === 'first_parent_found') {
			return sprintf((string)$this->getLang('icon_origin_first_parent_found'), $sourcePage);
		}

		return $sourcePage;
	}

	private function renderCurrentIconPreview(array $details, string $defaultTarget, string $actionPage, int $previewSize): void {
		$mediaID = (string)($details['media_id'] ?? '');
		$mediaPath = (string)($details['media_path'] ?? '');
		$originLabel = $this->getIconOriginLabel($details);
		/** @var helper_plugin_pagesicon|null $helper */
		$helper = plugin_load('helper', 'pagesicon');
		$previewUrl = ($helper && method_exists($helper, 'getManagedMediaUrl'))
			? $helper->getManagedMediaUrl($mediaID, ['w' => $previewSize])
			: ml($mediaID, ['w' => $previewSize], true, '&');

		echo '<a href="' . hsc($this->getMediaManagerUrl($mediaID)) . '" target="_blank" title="' . hsc($this->getLang('open_media_manager')) . '">';
		echo '<img src="' . hsc((string)$previewUrl) . '" alt="" width="' . $previewSize . '" style="display:block;margin:6px 0;" />';
		echo '</a>';
		echo '<small>' . hsc(noNS($mediaID)) . '</small>';
		if ($originLabel !== '') {
			echo '<br /><small>' . hsc(sprintf($this->getLang('current_icon_origin'), $originLabel)) . '</small>';
		}
		if ($mediaPath !== '') {
			echo '<br /><small>' . hsc(sprintf($this->getLang('current_icon_media_path'), $mediaPath)) . '</small>';
		}
		echo '<form action="' . wl($actionPage) . '" method="post" style="margin-top:6px;">';
		formSecurityToken();
		echo '<input type="hidden" name="do" value="pagesicon" />';
		echo '<input type="hidden" name="media_id" value="' . hsc($mediaID) . '" />';
		echo '<input type="hidden" name="pagesicon_delete_submit" value="1" />';
		echo '<button type="submit" class="button">' . hsc($this->getLang('delete_icon')) . '</button>';
		echo '</form>';
	}

	private function buildOwnIconDetailsList($helper, string $namespace, string $pageID, string $size, $currentDetails): array {
		if (!$helper || !method_exists($helper, 'getOwnPageIconMediaIds')) return [];

		$currentMediaID = is_array($currentDetails) ? cleanID((string)($currentDetails['media_id'] ?? '')) : '';
		$mediaIDs = $helper->getOwnPageIconMediaIds($namespace, $pageID, $size);
		$detailsList = [];

		foreach ($mediaIDs as $mediaID) {
			$mediaID = cleanID((string)$mediaID);
			if ($mediaID === '' || $mediaID === $currentMediaID) continue;

			$detailsList[] = [
				'media_id' => $mediaID,
				'media_path' => $mediaID,
				'origin' => 'current_page',
				'source_page' => cleanID(($namespace !== '' ? $namespace . ':' : '') . $pageID),
			];
		}

		return $detailsList;
	}

	private function renderCurrentIconSection(string $title, $currentDetails, array $ownVariants, string $defaultTarget, string $actionPage, int $previewSize): void {
		echo '<div class="pagesicon-current-item">';
		echo '<strong>' . hsc($title) . '</strong><br />';

		if ($currentDetails) {
			$this->renderCurrentIconPreview($currentDetails, $defaultTarget, $actionPage, $previewSize);
		} else {
			echo '<small>' . hsc($this->getLang('current_icon_none')) . '</small>';
		}

		if ($ownVariants) {
			echo '<div style="margin-top:8px;">';
			echo '<small><strong>' . hsc($this->getLang('current_icon_other_variants')) . '</strong></small>';
			foreach ($ownVariants as $variantDetails) {
				echo '<div style="margin-top:6px;">';
				$this->renderCurrentIconPreview($variantDetails, $defaultTarget, $actionPage, $previewSize);
				echo '</div>';
			}
			echo '</div>';
		}

		echo '</div>';
	}

	private function getDebugStepLabel(string $label): string {
		$langKey = 'debug_step_' . $label;
		$translated = (string)$this->getLang($langKey);
		if ($translated !== '' && $translated !== $langKey) return $translated;
		return $label;
	}

	private function formatDebugNamespace(string $namespace): string {
		$namespace = cleanID($namespace);
		return $namespace !== '' ? $namespace : (string)$this->getLang('debug_root_namespace');
	}

	private function splitDebugMediaId(string $mediaID): array {
		$mediaID = cleanID($mediaID);
		return [
			'path' => $this->formatDebugNamespace(getNS($mediaID)),
			'file' => noNS($mediaID),
		];
	}

	private function renderDebugMediaFileCell($helper, string $mediaID, bool $exists): string {
		$mediaID = cleanID($mediaID);
		$file = noNS($mediaID);
		if ($file === '') return '-';

		$label = '<code>' . hsc($file) . '</code>';
		$link = hsc($this->getMediaManagerUrl($mediaID));
		$html = '<a href="' . $link . '" target="_blank" title="' . hsc($this->getLang('open_media_manager')) . '">' . $label . '</a>';

		if (!$exists) return $html;

		$previewUrl = ($helper && method_exists($helper, 'getManagedMediaUrl'))
			? $helper->getManagedMediaUrl($mediaID, ['w' => 48])
			: ml($mediaID, ['w' => 48], true, '&');
		if ($previewUrl) {
			$html .= '<br /><a href="' . $link . '" target="_blank" title="' . hsc($this->getLang('open_media_manager')) . '">';
			$html .= '<img src="' . hsc((string)$previewUrl) . '" alt="" width="24" style="display:block;margin-top:4px;" />';
			$html .= '</a>';
		}

		return $html;
	}

	private function renderDebugInfo($helper, string $namespace, string $pageID): void {
		if (!$helper || !method_exists($helper, 'getPageIconDebugInfo')) return;

		$debugBig = $helper->getPageIconDebugInfo($namespace, $pageID, 'big');
		$debugSmall = $helper->getPageIconDebugInfo($namespace, $pageID, 'small');

		echo '<div class="pagesicon-debug" style="margin:16px 0 20px;">';
		echo '<h2>' . hsc($this->getLang('debug_title')) . '</h2>';
		echo '<p><small>' . hsc($this->getLang('debug_intro')) . '</small></p>';

		foreach (['big' => $debugBig, 'small' => $debugSmall] as $variant => $debugInfo) {
			echo '<h3>' . hsc(sprintf($this->getLang('debug_variant_title'), $variant)) . '</h3>';
			if (!is_array($debugInfo) || empty($debugInfo['steps'])) {
				echo '<p><small>' . hsc($this->getLang('current_icon_none')) . '</small></p>';
				continue;
			}

			$renderedSteps = [];
			echo '<div style="overflow-x:auto;max-width:100%;">';
			echo '<table class="inline" style="min-width:720px;table-layout:fixed;">';
			echo '<thead><tr>';
			echo '<th>' . hsc($this->getLang('debug_col_scope')) . '</th>';
			echo '<th>' . hsc($this->getLang('debug_col_media_path')) . '</th>';
			echo '<th>' . hsc($this->getLang('debug_col_media_file')) . '</th>';
			echo '<th>' . hsc($this->getLang('debug_col_exists')) . '</th>';
			echo '</tr></thead><tbody>';

			foreach (array_values($debugInfo['steps']) as $stepIndex => $step) {
				$stepLabel = sprintf($this->getLang('debug_step_title'), $stepIndex + 1, $this->getDebugStepLabel((string)($step['label'] ?? '')));
				$targetPage = cleanID((string)($step['target_page'] ?? ''));
				$targetNamespace = $this->formatDebugNamespace((string)($step['namespace'] ?? ''));
				$contextNamespace = $this->formatDebugNamespace((string)($step['context_namespace'] ?? ''));
				$checks = $step['checks'] ?? [];
				$stepKey = $stepLabel . '|' . $targetPage . '|' . $targetNamespace . '|' . $contextNamespace;
				if (isset($renderedSteps[$stepKey])) continue;
				$renderedSteps[$stepKey] = true;

				if (!is_array($checks) || !$checks) {
					echo '<tr>';
					echo '<td style="white-space:normal;word-break:break-word;">' . hsc($stepLabel) . '</td>';
					echo '<td>-</td>';
					echo '<td>-</td>';
					echo '<td>' . hsc($this->getLang('debug_exists_no')) . '</td>';
					echo '</tr>';
					continue;
				}

				foreach ($checks as $checkIndex => $check) {
					$mediaID = cleanID((string)($check['media_id'] ?? ''));
					$exists = !empty($check['exists']);
					$media = $this->splitDebugMediaId($mediaID);
					echo '<tr>';
					echo '<td style="white-space:normal;word-break:break-word;">' . ($checkIndex === 0 ? hsc($stepLabel) : '') . '</td>';
					echo '<td style="white-space:normal;word-break:break-word;"><code>' . hsc((string)$media['path']) . '</code></td>';
					echo '<td style="white-space:normal;word-break:break-word;">' . $this->renderDebugMediaFileCell($helper, $mediaID, $exists) . '</td>';
					echo '<td>' . hsc($exists ? $this->getLang('debug_exists_yes') : $this->getLang('debug_exists_no')) . '</td>';
					echo '</tr>';
				}
			}

			echo '</tbody></table>';
			echo '</div>';
		}

		echo '</div>';
	}

	private function handleRefreshCachePost(): void {
		global $INPUT, $ID;

		if (!$INPUT->post->has('pagesicon_refresh_cache_submit')) return;
		if (!checkSecurityToken()) return;

		$targetPage = cleanID((string)$ID);
		if ($targetPage === '') return;
		if (!$this->canUploadToTarget($targetPage)) {
			msg($this->getLang('error_no_upload_permission'), -1);
			return;
		}

		$helper = plugin_load('helper', 'pagesicon');
		if ($helper && method_exists($helper, 'notifyIconUpdated')) {
			$helper->notifyIconUpdated($targetPage, 'refresh-cache', '');
		}

		msg((string)$this->getLang('cache_refresh_success'), 1);
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
		$ownBig = ($helper && method_exists($helper, 'getOwnPageIconMediaIds')) ? $helper->getOwnPageIconMediaIds($namespace, $pageID, 'big') : [];
		$ownSmall = ($helper && method_exists($helper, 'getOwnPageIconMediaIds')) ? $helper->getOwnPageIconMediaIds($namespace, $pageID, 'small') : [];
		$allowed = array_values(array_filter(array_unique(array_merge([$currentBig, $currentSmall], $ownBig, $ownSmall))));
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
		$currentBig = ($helper && method_exists($helper, 'getPageIconDetails')) ? $helper->getPageIconDetails($namespace, $pageID, 'big') : false;
		$currentSmall = ($helper && method_exists($helper, 'getPageIconDetails')) ? $helper->getPageIconDetails($namespace, $pageID, 'small') : false;
		$ownBigVariants = $this->buildOwnIconDetailsList($helper, $namespace, $pageID, 'big', $currentBig);
		$ownSmallVariants = $this->buildOwnIconDetailsList($helper, $namespace, $pageID, 'small', $currentSmall);
		$fallbackMode = ($helper && method_exists($helper, 'getCurrentFallbackMode')) ? $helper->getCurrentFallbackMode() : 'none';
		$showDebug = (bool)$INPUT->bool('pagesicon_debug');
		$debugUrl = wl($actionPage, ['do' => 'pagesicon', 'pagesicon_debug' => $showDebug ? 0 : 1], false, '&');

		echo '<h1>' . hsc($this->getLang('menu')) . '</h1>';
		echo '<p>' . hsc($this->getLang('intro')) . '</p>';
		echo '<p><small>' . hsc(sprintf($this->getLang('fallback_mode_current'), $this->getFallbackModeLabel($fallbackMode))) . '</small></p>';
		echo '<p><small>' . hsc($this->getLang('icon_scope_help')) . '</small></p>';
		echo '<p><small>' . hsc(sprintf($this->getLang('allowed_extensions'), $allowed)) . '</small></p>';
		echo '<div class="pagesicon-current-preview" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;margin:10px 0 16px;">';
		$this->renderCurrentIconSection((string)$this->getLang('current_big_icon'), $currentBig, $ownBigVariants, $defaultTarget, $actionPage, $previewSize);
		$this->renderCurrentIconSection((string)$this->getLang('current_small_icon'), $currentSmall, $ownSmallVariants, $defaultTarget, $actionPage, $previewSize);
		echo '</div>';
		if ($showDebug) {
			$this->renderDebugInfo($helper, $namespace, $pageID);
		}

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

		echo '<p style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
		echo '<button type="submit" class="button">' . hsc($this->getLang('upload_button')) . '</button>';
		echo '<button type="submit" class="button" formaction="' . wl($actionPage) . '" formmethod="post" formenctype="application/x-www-form-urlencoded" formnovalidate="formnovalidate" name="pagesicon_refresh_cache_submit" value="1">' . hsc($this->getLang('refresh_cache_button')) . '</button>';
		echo '<a class="button" href="' . hsc($debugUrl) . '">' . hsc($showDebug ? $this->getLang('debug_hide_button') : $this->getLang('debug_button')) . '</a>';
		echo '</p>';
		echo '</form>';
	}
		
	public function displayPageIcon(Doku_Event &$event, $param): void {
		global $ACT, $ID;

		if($ACT !== 'show') return;
		if(!(bool)$this->getConf('show_on_top')) return;

		if($this->isLayoutIncludePage()) return;

		$namespace = getNS($ID);
		$pageID = noNS((string)$ID);
		/** @var helper_plugin_pagesicon|null $helper */
		$helper = plugin_load('helper', 'pagesicon');
		if(!$helper) return;
		$sizeMode = $this->getIconSize() > 35 ? 'bigorsmall' : 'smallorbig';
		$logoMediaID = $helper->getPageIconId($namespace, $pageID, $sizeMode);
		if(!$logoMediaID) return;
		if($this->hasIconAlready($event->data)) return;

		$size = $this->getIconSize();
		$src = $helper->getPageIconUrl($namespace, $pageID, $sizeMode, ['w' => $size]);
		if(!$src) return;
		$iconHtml = '<img src="' . hsc((string)$src) . '" class="media pagesicon-image" loading="lazy" alt="" width="' . $size . '" />';

		$inlineIcon = '<span class="pagesicon-injected pagesicon-injected-inline">' . $iconHtml . '</span> ';
		$updated = preg_replace('/<h1\b([^>]*)>/i', '<h1$1>' . $inlineIcon, $event->data, 1, $count);
		if ($count > 0 && $updated !== null) {
			$event->data = $updated;
			return;
		}

		// Fallback: no H1 found, keep old behavior
		$event->data = '<div class="pagesicon-injected">' . $iconHtml . '</div>' . "\n" . $event->data;
	}

	private static array $linkIconCache = [];

	private function getLinkIconUrl(object $helper, string $pageId): ?string {
		if (!array_key_exists($pageId, self::$linkIconCache)) {
			$url = $helper->getPageIconUrl(getNS($pageId), noNS($pageId), 'smallorbig', ['w' => 16]);
			self::$linkIconCache[$pageId] = $url ?: null;
		}
		return self::$linkIconCache[$pageId];
	}

	public function injectLinkIcons(Doku_Event $event): void {
		if ($event->data[0] !== 'xhtml') return;

		$conf = $this->getConf('link_icons');
		if ($conf === 'none') return;

		$helper = plugin_load('helper', 'pagesicon');
		if (!$helper) return;

		$event->data[1] = preg_replace_callback(
			'~(<a\b[^>]*\bclass="[^"]*\bwikilink([12])[^"]*"[^>]*\btitle="([^"]+)"[^>]*>)~',
			function ($m) use ($conf, $helper) {
				if ($m[2] === '2' && $conf !== 'all') return $m[1];
				$pageId = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$iconUrl = $this->getLinkIconUrl($helper, $pageId);
				if (!$iconUrl) return $m[1];
				return $m[1] . '<img src="' . hsc((string)$iconUrl) . '" class="pagesicon-link" alt="" width="16" height="16" loading="lazy">';
			},
			(string)$event->data[1]
		);
	}

	public function handleAction(Doku_Event $event): void {
		if ($event->data !== 'pagesicon') return;
		$event->preventDefault();
	}

	public function renderAction(Doku_Event $event): void {
		global $ACT;
		if ($ACT !== 'pagesicon') return;

		$this->handleRefreshCachePost();
		$this->handleDeletePost();
		$this->handleUploadPost();
		$this->renderUploadForm();

		$event->preventDefault();
		$event->stopPropagation();
	}
}
