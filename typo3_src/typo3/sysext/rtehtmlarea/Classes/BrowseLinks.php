<?php
namespace TYPO3\CMS\Rtehtmlarea;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Recordlist\Browser\ElementBrowser;

/**
 * Script class for the Element Browser window.
 */
class BrowseLinks extends ElementBrowser {

	/**
	 * @var int
	 */
	public $editorNo;

	/**
	 * TYPO3 language code of the content language
	 *
	 * @var int
	 */
	public $contentTypo3Language;

	/**
	 * Language service object for localization to the content language
	 *
	 * @var LanguageService
	 */
	protected $contentLanguageService;

	/**
	 * @var array
	 */
	public $additionalAttributes = array();

	/**
	 * @var array
	 */
	public $buttonConfig = array();

	/**
	 * @var array
	 */
	public $anchorTypes = array('page', 'url', 'file', 'mail');

	/**
	 * @var array
	 */
	public $classesAnchorDefault = array();

	/**
	 * @var array
	 */
	public $classesAnchorDefaultTitle = array();

	/**
	 * @var array
	 */
	public $classesAnchorClassTitle = array();

	/**
	 * @var array
	 */
	public $classesAnchorDefaultTarget = array();

	/**
	 * @var array
	 */
	public $classesAnchorJSOptions = array();

	/**
	 * @var
	 */
	protected $defaultLinkTarget;

	/**
	 * @var string
	 */
	protected $hookName = 'ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Create content language service
		$this->contentLanguageService = GeneralUtility::makeInstance(LanguageService::class);
	}

	/**
	 * Initialize class variables
	 *
	 * @return void
	 */
	public function initVariables() {
		parent::initVariables();

		// Process bparams
		$pArr = explode('|', $this->bparams);
		$pRteArr = explode(':', $pArr[1]);
		$this->editorNo = $pRteArr[0];
		$this->contentTypo3Language = $pRteArr[1];
		$this->RTEtsConfigParams = $pArr[2];
		if (!$this->editorNo) {
			$this->editorNo = GeneralUtility::_GP('editorNo');
			$this->contentTypo3Language = GeneralUtility::_GP('contentTypo3Language');
			$this->RTEtsConfigParams = GeneralUtility::_GP('RTEtsConfigParams');
		}
		$pArr[1] = implode(':', array($this->editorNo, $this->contentTypo3Language));
		$pArr[2] = $this->RTEtsConfigParams;
		$this->bparams = implode('|', $pArr);

		$this->contentLanguageService->init($this->contentTypo3Language);

		$this->buttonConfig = $this->getButtonConfig('link');
	}

	/**
	 * Initialize document template object
	 *
	 * @return void
	 */
	protected function initDocumentTemplate() {
		parent::initDocumentTemplate();

		// Add attributes to body tag. Note: getBodyTagAdditions will invoke the hooks
		$this->doc->bodyTagAdditions = $this->getBodyTagAdditions();

		$pageRenderer = $this->getPageRenderer();
		$pageRenderer->addCssFile(ExtensionManagementUtility::extRelPath('t3skin') . 'rtehtmlarea/htmlarea.css');
		$pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/LegacyTree', 'function(Tree) {
			Tree.ajaxID = "SC_alt_file_navframe::expandCollapse";
		}');
	}

	/**
	 * Get the configuration of the button
	 *
	 * @param string $buttonName: the name of the button
	 * @return array the configuration array of the image button
	 */
	protected function getButtonConfig($buttonName) {
		return isset($this->RTEProperties['default.']['buttons.'][$buttonName . '.'])
			? $this->RTEProperties['default.']['buttons.'][$buttonName . '.']
			: array();
	}

	/**
	 * Initialize the current or default values of the link attributes
	 *
	 * @return void
	 */
	protected function initLinkAttributes() {
		// Initializing the class value
		$this->setClass = isset($this->curUrlArray['class']) ? $this->curUrlArray['class'] : '';
		// Processing the classes configuration
		if (!empty($this->buttonConfig['properties.']['class.']['allowedClasses'])) {
			$classesAnchorArray = GeneralUtility::trimExplode(',', $this->buttonConfig['properties.']['class.']['allowedClasses'], TRUE);
			// Collecting allowed classes and configured default values
			$classesAnchor = array();
			$classesAnchor['all'] = array();
			$titleReadOnly = $this->buttonConfig['properties.']['title.']['readOnly']
				|| $this->buttonConfig[$this->act . '.']['properties.']['title.']['readOnly'];
			if (is_array($this->RTEProperties['classesAnchor.'])) {
				foreach ($this->RTEProperties['classesAnchor.'] as $label => $conf) {
					if (in_array($conf['class'], $classesAnchorArray)) {
						$classesAnchor['all'][] = $conf['class'];
						if (in_array($conf['type'], $this->anchorTypes)) {
							$classesAnchor[$conf['type']][] = $conf['class'];
							if ($this->buttonConfig[$conf['type'] . '.']['properties.']['class.']['default'] == $conf['class']) {
								$this->classesAnchorDefault[$conf['type']] = $conf['class'];
								if ($conf['titleText']) {
									$this->classesAnchorDefaultTitle[$conf['type']] = $this->getLLContent(trim($conf['titleText']));
								}
								if (isset($conf['target'])) {
									$this->classesAnchorDefaultTarget[$conf['type']] = trim($conf['target']);
								}
							}
						}
						if ($titleReadOnly && $conf['titleText']) {
							$this->classesAnchorClassTitle[$conf['class']] = ($this->classesAnchorDefaultTitle[$conf['type']] = $this->getLLContent(trim($conf['titleText'])));
						}
					}
				}
			}
			// Constructing the class selector options
			foreach ($this->anchorTypes as $anchorType) {
				$currentClass = $this->curUrlInfo['act'] === $anchorType ? $this->curUrlArray['class'] : '';
				foreach ($classesAnchorArray as $class) {
					if (!in_array($class, $classesAnchor['all']) || in_array($class, $classesAnchor['all']) && is_array($classesAnchor[$anchorType]) && in_array($class, $classesAnchor[$anchorType])) {
						$selected = '';
						if ($currentClass == $class || !$currentClass && $this->classesAnchorDefault[$anchorType] == $class) {
							$selected = 'selected="selected"';
						}
						$classLabel = !empty($this->RTEProperties['classes.'][$class . '.']['name'])
							? $this->getPageConfigLabel($this->RTEProperties['classes.'][$class . '.']['name'], 0)
							: $class;
						$classStyle = !empty($this->RTEProperties['classes.'][$class . '.']['value'])
							? $this->RTEProperties['classes.'][$class . '.']['value']
							: '';
						$this->classesAnchorJSOptions[$anchorType] .= '<option ' . $selected . ' value="' . $class . '"' . ($classStyle ? ' style="' . $classStyle . '"' : '') . '>' . $classLabel . '</option>';
					}
				}
				if ($this->classesAnchorJSOptions[$anchorType] && !($this->buttonConfig['properties.']['class.']['required'] || $this->buttonConfig[$this->act . '.']['properties.']['class.']['required'])) {
					$selected = '';
					if (!$this->setClass && !$this->classesAnchorDefault[$anchorType]) {
						$selected = 'selected="selected"';
					}
					$this->classesAnchorJSOptions[$anchorType] = '<option ' . $selected . ' value=""></option>' . $this->classesAnchorJSOptions[$anchorType];
				}
			}
		}
		// Initializing the title value
		$this->setTitle = isset($this->curUrlArray['title']) ? $this->curUrlArray['title'] : '';
		// Initializing the target value
		$this->setTarget = isset($this->curUrlArray['target']) ? $this->curUrlArray['target'] : '';
		// Default target
		$this->defaultLinkTarget = $this->classesAnchorDefault[$this->act] && $this->classesAnchorDefaultTarget[$this->act]
			? $this->classesAnchorDefaultTarget[$this->act]
			: (isset($this->buttonConfig[$this->act . '.']['properties.']['target.']['default'])
				? $this->buttonConfig[$this->act . '.']['properties.']['target.']['default']
				: (isset($this->buttonConfig['properties.']['target.']['default'])
					? $this->buttonConfig['properties.']['target.']['default']
					: ''));
		// Initializing additional attributes
		if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rtehtmlarea']['plugins']['TYPO3Link']['additionalAttributes']) {
			$addAttributes = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rtehtmlarea']['plugins']['TYPO3Link']['additionalAttributes'], TRUE);
			foreach ($addAttributes as $attribute) {
				$this->additionalAttributes[$attribute] = isset($this->curUrlArray[$attribute]) ? $this->curUrlArray[$attribute] : '';
			}
		}
	}

	/**
	 * Provide the additional parameters to be included in the template body tag
	 *
	 * @return string the body tag additions
	 */
	public function getBodyTagAdditions() {
		$bodyTagAdditions = array();
		// call hook for extra additions
		foreach ($this->hookObjects as $hookObject) {
			if (method_exists($hookObject, 'addBodyTagAdditions')) {
				$bodyTagAdditions = $hookObject->addBodyTagAdditions($bodyTagAdditions);
			}
		}
		return GeneralUtility::implodeAttributes($bodyTagAdditions, TRUE);
	}

	/**
	 * Generate JS code to be used on the link insert/modify dialogue
	 *
	 * @return string the generated JS code
	 */
	public function getJSCode() {
		// BEGIN accumulation of header JavaScript:
		$JScode = '';
		$JScode .= '
			var plugin = window.parent.RTEarea["' . $this->editorNo . '"].editor.getPlugin("TYPO3Link");
			var HTMLArea = window.parent.HTMLArea;
			var add_href=' . GeneralUtility::quoteJSvalue($this->curUrlArray['href'] ? '&curUrl[href]=' . rawurlencode($this->curUrlArray['href']) : '') . ';
			var add_target=' . GeneralUtility::quoteJSvalue($this->setTarget ? '&curUrl[target]=' . rawurlencode($this->setTarget) : '') . ';
			var add_class=' . GeneralUtility::quoteJSvalue($this->setClass ? '&curUrl[class]=' . rawurlencode($this->setClass) : '') . ';
			var add_title=' . GeneralUtility::quoteJSvalue($this->setTitle ? '&curUrl[title]=' . rawurlencode($this->setTitle) : '') . ';
			var add_params=' . GeneralUtility::quoteJSvalue($this->bparams ? '&bparams=' . rawurlencode($this->bparams) : '') . ';
			var additionalValues = ' . (!empty($this->additionalAttributes) ? json_encode($this->additionalAttributes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}') . ';';
		// Attributes setting functions
		$JScode .= '
			var cur_href=' . GeneralUtility::quoteJSvalue($this->curUrlArray['href'] ? ($this->curUrlInfo['query'] ? substr($this->curUrlArray['href'], 0, -strlen($this->curUrlInfo['query'])) : $this->curUrlArray['href']) : '') . ';
			var cur_target=' . GeneralUtility::quoteJSvalue($this->setTarget ?: '') . ';
			var cur_class=' . GeneralUtility::quoteJSvalue($this->setClass ?: '') . ';
			var cur_title=' . GeneralUtility::quoteJSvalue($this->setTitle ?: '') . ';

			function browse_links_setTarget(value) {
				cur_target=value;
				add_target="&curUrl[target]="+encodeURIComponent(value);
			}
			function browse_links_setClass(value) {
				cur_class=value;
				add_class="&curUrl[class]="+encodeURIComponent(value);
			}
			function browse_links_setTitle(value) {
				cur_title=value;
				add_title="&curUrl[title]="+encodeURIComponent(value);
			}
			function browse_links_setHref(value) {
				cur_href=value;
				add_href="&curUrl[href]="+value;
			}
			function browse_links_setAdditionalValue(name, value) {
				additionalValues[name] = value;
			}
		';
		// Link setting functions
		$JScode .= '
			function link_typo3Page(id,anchor) {
				var parameters = (document.ltargetform.query_parameters && document.ltargetform.query_parameters.value) ? (document.ltargetform.query_parameters.value.charAt(0) == "&" ? "" : "&") + document.ltargetform.query_parameters.value : "";
				var theLink = \'' . $this->siteURL . '?id=\' + id + parameters + (anchor ? anchor : "");
				if (document.ltargetform.anchor_title) browse_links_setTitle(document.ltargetform.anchor_title.value);
				if (document.ltargetform.anchor_class) browse_links_setClass(document.ltargetform.anchor_class.value);
				if (document.ltargetform.ltarget) browse_links_setTarget(document.ltargetform.ltarget.value);
				if (document.ltargetform.lrel) browse_links_setAdditionalValue("rel", document.ltargetform.lrel.value);
				browse_links_setAdditionalValue("data-htmlarea-external", "");
				plugin.createLink(theLink,cur_target,cur_class,cur_title,additionalValues);
				return false;
			}
			function link_folder(folder) {
				if (folder && folder.substr(0, 5) == "file:") {
					var theLink = \'' . $this->siteURL . '?file:\' + encodeURIComponent(folder.substr(5));
				} else {
					var theLink = \'' . $this->siteURL . '?\' + folder;
				}
				if (document.ltargetform.anchor_title) browse_links_setTitle(document.ltargetform.anchor_title.value);
				if (document.ltargetform.anchor_class) browse_links_setClass(document.ltargetform.anchor_class.value);
				if (document.ltargetform.ltarget) browse_links_setTarget(document.ltargetform.ltarget.value);
				if (document.ltargetform.lrel) browse_links_setAdditionalValue("rel", document.ltargetform.lrel.value);
				browse_links_setAdditionalValue("data-htmlarea-external", "");
				plugin.createLink(theLink,cur_target,cur_class,cur_title,additionalValues);
				return false;
			}
			function link_current() {
				var parameters = (document.ltargetform.query_parameters && document.ltargetform.query_parameters.value) ? (document.ltargetform.query_parameters.value.charAt(0) == "&" ? "" : "&") + document.ltargetform.query_parameters.value : "";
				if (document.ltargetform.anchor_title) browse_links_setTitle(document.ltargetform.anchor_title.value);
				if (document.ltargetform.anchor_class) browse_links_setClass(document.ltargetform.anchor_class.value);
				if (document.ltargetform.ltarget) browse_links_setTarget(document.ltargetform.ltarget.value);
				if (document.ltargetform.lrel) browse_links_setAdditionalValue("rel", document.ltargetform.lrel.value);
				if (cur_href!="http://" && cur_href!="mailto:") {
					plugin.createLink(cur_href + parameters,cur_target,cur_class,cur_title,additionalValues);
				}
				return false;
			}
		';
		// General "jumpToUrl" and launchView functions:
		$JScode .= '
			function jumpToUrl(URL,anchor) {
				if (URL.charAt(0) === \'?\') {
					URL = ' . GeneralUtility::quoteJSvalue($this->getThisScript()) . ' + URL.substring(1);
				}
				var add_act = URL.indexOf("act=")==-1 ? "&act=' . $this->act . '" : "";
				var add_mode = URL.indexOf("mode=")==-1 ? "&mode=' . $this->mode . '" : "";
				var add_editorNo = URL.indexOf("editorNo=")==-1 ? "&editorNo=' . $this->editorNo . '" : "";
				var add_contentTypo3Language = URL.indexOf("contentTypo3Language=")==-1 ? "&contentTypo3Language=' . $this->contentTypo3Language . '" : "";
				var add_additionalValues = "";
				if (plugin.pageTSConfiguration && plugin.pageTSConfiguration.additionalAttributes) {
					var additionalAttributes = plugin.pageTSConfiguration.additionalAttributes.split(",");
					for (var i = additionalAttributes.length; --i >= 0;) {
						if (additionalValues[additionalAttributes[i]] != "") {
							add_additionalValues += "&curUrl[" + additionalAttributes[i] + "]=" + encodeURIComponent(additionalValues[additionalAttributes[i]]);
						}
					}
				}
				window.location.href = URL + add_act + add_mode + add_editorNo + add_contentTypo3Language + add_href + add_target + add_class + add_title + add_additionalValues + add_params + (typeof(anchor) === "string" ? anchor : "");
				return false;
			}
		';
		// Hook to overwrite or extend javascript functions
		if (
			isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->hookName]['extendJScode'])
			&& is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->hookName]['extendJScode'])
		) {
			$_params = array(
				'conf' => []
			);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->hookName]['extendJScode'] as $objRef) {
				$processor =& GeneralUtility::getUserObj($objRef);
				$JScode .= $processor->extendJScode($_params, $this);
			}
		}
		return $JScode;
	}

	/******************************************************************
	 *
	 * Main functions
	 *
	 ******************************************************************/
	/**
	 * Rich Text Editor (RTE) link selector (MAIN function)
	 * Generates the link selector for the Rich Text Editor.
	 * Can also be used to select links for the TCEforms (see $wiz)
	 *
	 * @param bool $wiz If set, the "remove link" is not shown in the menu: Used for the "Select link" wizard which is used by the TCEforms
	 * @return string Modified content variable.
	 */
	protected function main_rte($wiz = FALSE) {
		// Starting content:
		$content = $this->doc->startPage($this->getLanguageService()->getLL('Insert/Modify Link', TRUE));
		// Making menu in top:
		$content .= $this->doc->getTabMenuRaw($this->buildMenuArray($wiz, $this->getAllowedItems('page,file,folder,url,mail')));
		// Adding the menu and header to the top of page:
		$content .= $this->printCurrentUrl($this->curUrlInfo['info']) . '<br />';
		// Depending on the current action we will create the actual module content for selecting a link:
		switch ($this->act) {
			case 'mail':
				$extUrl = $this->getEmailSelectorHtml();
				$content .= $this->addAttributesForm($extUrl);
				break;
			case 'url':
				$extUrl = $this->getExternalUrlSelectorHtml();
				$content .= $this->addAttributesForm($extUrl);
				break;
			case 'file':
			case 'folder':
				$content .= $this->addAttributesForm();
				$content .= $this->getFileSelectorHtml(FolderTree::class);
				break;
			case 'page':
				$content .= $this->addAttributesForm();
				$content .= $this->getPageSelectorHtml(PageTree::class);
				break;
			default:
				// call hook
				foreach ($this->hookObjects as $hookObject) {
					$content .= $hookObject->getTab($this->act);
				}
		}
		// End page, return content:
		$content .= $this->doc->endPage();
		$content = $this->doc->insertStylesAndJS($content);
		return $content;
	}

	/**
	 * Returns HTML of the email link from
	 *
	 * @return string
	 */
	protected function getEmailSelectorHtml() {
		$extUrl = '
			<!--
				Enter mail address:
			-->
			<tr>
				<td>
					<label>
						' . $this->getLanguageService()->getLL('emailAddress', TRUE) . ':
					</label>
				</td>
				<td>
					<input type="text" name="lemail"' . $this->doc->formWidth(20)
						. ' value="' . htmlspecialchars(($this->curUrlInfo['act'] == 'mail' ? $this->curUrlInfo['info'] : '')) . '" />
					<input class="btn btn-default" type="submit" value="' . $this->getLanguageService()->getLL('setLink', TRUE)
						. '" onclick="browse_links_setTarget(\'\');browse_links_setHref(\'mailto:\'+document.ltargetform.lemail.value);'
						. 'browse_links_setAdditionalValue(\'data-htmlarea-external\', \'\');return link_current();" />
				</td>
			</tr>';
		return $extUrl;
	}

	/**
	 * Returns HTML of the external url link from
	 *
	 * @return string
	 */
	protected function getExternalUrlSelectorHtml() {
		$extUrl = '
			<!--
				Enter External URL:
			-->
			<tr>
				<td>
					<label>
						URL:
					</label>
				</td>
				<td colspan="3">
					<input type="text" name="lurl"' . $this->doc->formWidth(20)
						. ' value="' . htmlspecialchars(($this->curUrlInfo['act'] == 'url' ? $this->curUrlInfo['info'] : 'http://'))
						. '" />
					<input class="btn btn-default" type="submit" value="' . $this->getLanguageService()->getLL('setLink', TRUE)
						. '" onclick="if (/^[A-Za-z0-9_+]{1,8}:/.test(document.ltargetform.lurl.value)) { '
						. ' browse_links_setHref(document.ltargetform.lurl.value); } else { browse_links_setHref(\'http://\''
						. '+document.ltargetform.lurl.value); } browse_links_setAdditionalValue(\'data-htmlarea-external\', \'1\');'
						. 'return link_current();" />
				</td>
			</tr>';
		return $extUrl;
	}

	/**
	 * Get the allowed items or tabs
	 *
	 * @param string $items: initial list of possible items
	 * @return array the allowed items
	 */
	public function getAllowedItems($items) {
		$allowedItems = explode(',', $items);
		// Calling hook for extra options
		foreach ($this->hookObjects as $hookObject) {
			$allowedItems = $hookObject->addAllowedItems($allowedItems);
		}

		// Removing items as per configuration
		if (is_array($this->buttonConfig['options.']) && $this->buttonConfig['options.']['removeItems']) {
			$allowedItems = array_diff($allowedItems, GeneralUtility::trimExplode(',', $this->buttonConfig['options.']['removeItems'], TRUE));
		}

		reset($allowedItems);
		if (!in_array($this->act, $allowedItems)) {
			$this->act = current($allowedItems);
		}
		return $allowedItems;
	}

	/**
	 * Creates a form for link attributes
	 *
	 * @param string $rows: html code for some initial rows of the table to be wrapped in form
	 * @return string The HTML code of the form
	 */
	public function addAttributesForm($rows = '') {
		// additional fields for links
		$additionalAttributeFields = '';
		if (
			isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->hookName]['addAttributeFields'])
			&& is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->hookName]['addAttributeFields'])
		) {
			$conf = array();
			$_params = array(
				'conf' => &$conf
			);
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->hookName]['addAttributeFields'] as $objRef) {
				$processor =& GeneralUtility::getUserObj($objRef);
				$additionalAttributeFields .= $processor->getAttributefields($_params, $this);
			}
		}

		// Add page id, target, class selector box, title and parameters fields:
		$lpageId = $this->addPageIdSelector();
		$queryParameters = $this->addQueryParametersSelector();
		$ltarget = $this->addTargetSelector();
		$lclass = $this->addClassSelector();
		$ltitle = $this->addTitleSelector();
		$rel = $this->addRelField();

		$ltargetForm = '';
		if ($rows || $lpageId || $queryParameters || $lclass || $ltitle || $ltarget || $rel) {
			$ltargetForm = $this->wrapInForm($rows . $lpageId . $queryParameters . $lclass . $ltitle . $ltarget . $rel . $additionalAttributeFields);
		}
		return $ltargetForm;
	}

	/**
	 * Wrap in form
	 *
	 * @param string $string
	 * @return string
	 */
	public function wrapInForm($string) {
		$form = '
			<!--
				Selecting target for link:
			-->
			<form action="" name="ltargetform" id="ltargetform">
				<table id="typo3-linkTarget" class="htmlarea-window-table">' . $string;
		if ($this->act === $this->curUrlInfo['act'] && $this->act != 'mail' && $this->curUrlArray['href']) {
			$form .= '
					<tr>
						<td>
						</td>
						<td colspan="3">
							<input class="btn btn-default" type="submit" value="' . $this->getLanguageService()->getLL('update', TRUE) . '" onclick="'
								. ($this->act === 'url' ? 'browse_links_setAdditionalValue(\'data-htmlarea-external\', \'1\'); ' : '')
								. 'return link_current();" />
						</td>
					</tr>';
		}
		$form .= '
				</table>
			</form>';
		return $form;
	}

	/**
	 * Add page id selector
	 *
	 * @return string
	 */
	public function addPageIdSelector() {
		if ($this->act === 'page' && isset($this->buttonConfig['pageIdSelector.']['enabled'])
			&& $this->buttonConfig['pageIdSelector.']['enabled']
		) {
			return '
				<tr>
					<td>
						<label>
							' . $this->getLanguageService()->getLL('page_id', TRUE) . ':
						</label>
					</td>
					<td colspan="3">
						<input type="text" size="6" name="luid" /> <input class="btn btn-default" type="submit" value="'
							. $this->getLanguageService()->getLL('setLink', TRUE) . '" onclick="return link_typo3Page(document.ltargetform.luid.value);" />
					</td>
				</tr>';
		}
		return '';
	}

	/**
	 * Add rel field
	 *
	 * @return string
	 */
	public function addRelField() {
		// Unset rel attribute if we changed tab
		$currentRel = $this->curUrlInfo['act'] === $this->act && isset($this->curUrlArray['rel']) ? $this->curUrlArray['rel'] : '';
		if (($this->act === 'page' || $this->act === 'url' || $this->act === 'file')
			&& isset($this->buttonConfig['relAttribute.']['enabled']) && $this->buttonConfig['relAttribute.']['enabled']
		) {
			return '
						<tr>
							<td><label>' . $this->getLanguageService()->getLL('linkRelationship', TRUE) . ':</label></td>
							<td colspan="3">
								<input type="text" name="lrel" value="' . $currentRel . '"  '
				. $this->doc->formWidth(30) . ' />
							</td>
						</tr>';
		}
		return '';
	}

	/**
	 * Add query parameter selector
	 *
	 * @return string
	 */
	public function addQueryParametersSelector() {
		if ($this->act === 'page' && isset($this->buttonConfig['queryParametersSelector.']['enabled'])
			&& $this->buttonConfig['queryParametersSelector.']['enabled']
		) {
			return '
						<tr>
							<td><label>' . $this->getLanguageService()->getLL('query_parameters', TRUE) . ':</label></td>
							<td colspan="3">
								<input type="text" name="query_parameters" value="' . ($this->curUrlInfo['query'] ?: '')
				. '" ' . $this->doc->formWidth(30) . ' />
							</td>
						</tr>';
		}
		return '';
	}

	/**
	 * Add target selector
	 *
	 * @return string
	 */
	public function addTargetSelector() {
		if ($this->act === 'mail') {
			return '';
		}
		$targetSelectorConfig = array();
		if (is_array($this->buttonConfig['targetSelector.'])) {
			$targetSelectorConfig = $this->buttonConfig['targetSelector.'];
		}
		// Reset the target to default if we changed tab
		$currentTarget = $this->curUrlInfo['act'] === $this->act && isset($this->curUrlArray['target']) ? $this->curUrlArray['target'] : '';
		$target = $currentTarget ?: $this->defaultLinkTarget;
		$lang = $this->getLanguageService();
		$ltarget = '
				<tr id="ltargetrow"' . ($targetSelectorConfig['disabled'] ? ' style="display: none;"' : '') . '>
					<td><label>' . $lang->getLL('target', TRUE) . ':</label></td>
					<td><input type="text" name="ltarget" onchange="browse_links_setTarget(this.value);" value="'
					. htmlspecialchars($target) . '"' . $this->doc->formWidth(10) . ' /></td>';
		$ltarget .= '
					<td colspan="2">';
		if (!$targetSelectorConfig['disabled']) {
			$ltarget .= '
						<select name="ltarget_type" onchange="browse_links_setTarget(this.options[this.selectedIndex].value);document.ltargetform.ltarget.value=this.options[this.selectedIndex].value;this.selectedIndex=0;">
							<option></option>
							<option value="_top">' . $lang->getLL('top', TRUE) . '</option>
							<option value="_blank">' . $lang->getLL('newWindow', TRUE) . '</option>
						</select>';
		}
		$ltarget .= '
					</td>
				</tr>';
		return $ltarget;
	}

	/**
	 * Return html code for the class selector
	 *
	 * @return string the html code to be added to the form
	 */
	public function addClassSelector() {
		$selectClass = '';
		if ($this->classesAnchorJSOptions[$this->act]) {
			$selectClass = '
						<tr>
							<td><label>' . $this->getLanguageService()->getLL('anchor_class', TRUE) . ':</label></td>
							<td colspan="3">
								<select name="anchor_class" onchange="' . $this->getClassOnChangeJS() . '">
									' . $this->classesAnchorJSOptions[$this->act] . '
								</select>
							</td>
						</tr>';
		}
		return $selectClass;
	}

	/**
	 * Return JS code for the class selector onChange event
	 *
	 * @return 	string	class selector onChange JS code
	 */
	public function getClassOnChangeJS() {
		return '
					if (document.ltargetform.anchor_class) {
						document.ltargetform.anchor_class.value = document.ltargetform.anchor_class.options[document.ltargetform.anchor_class.selectedIndex].value;
						if (document.ltargetform.anchor_class.value && HTMLArea.classesAnchorSetup) {
							for (var i = HTMLArea.classesAnchorSetup.length; --i >= 0;) {
								var anchorClass = HTMLArea.classesAnchorSetup[i];
								if (anchorClass[\'name\'] == document.ltargetform.anchor_class.value) {
									if (anchorClass[\'titleText\'] && document.ltargetform.anchor_title) {
										document.ltargetform.anchor_title.value = anchorClass[\'titleText\'];
										document.getElementById(\'rtehtmlarea-browse-links-title-readonly\').innerHTML = anchorClass[\'titleText\'];
										browse_links_setTitle(anchorClass[\'titleText\']);
									}
									if (typeof anchorClass[\'target\'] !== \'undefined\') {
										if (document.ltargetform.ltarget) {
											document.ltargetform.ltarget.value = anchorClass[\'target\'];
										}
										browse_links_setTarget(anchorClass[\'target\']);
									} else if (document.ltargetform.ltarget && document.getElementById(\'ltargetrow\').style.display == \'none\') {
											// Reset target to default if field is not displayed and class has no configured target
										document.ltargetform.ltarget.value = \'' . ($this->defaultLinkTarget ?: '') . '\';
										browse_links_setTarget(document.ltargetform.ltarget.value);
									}
									break;
								}
							}
						}
						browse_links_setClass(document.ltargetform.anchor_class.value);
					}
								';
	}

	/**
	 * Add title selector
	 *
	 * @return string
	 */
	public function addTitleSelector() {
		// Reset the title to default if we changed tab
		$currentTitle = $this->curUrlInfo['act'] === $this->act && isset($this->curUrlArray['title']) ? $this->curUrlArray['title'] : '';
		$title = $currentTitle ?: (!$this->classesAnchorDefault[$this->act] ? '' : $this->classesAnchorDefaultTitle[$this->act]);
		$readOnly = isset($this->buttonConfig[$this->act . '.']['properties.']['title.']['readOnly'])
			? $this->buttonConfig[$this->act . '.']['properties.']['title.']['readOnly']
			: (isset($this->buttonConfig['properties.']['title.']['readOnly'])
				? $this->buttonConfig['properties.']['title.']['readOnly']
				: FALSE);
		if ($readOnly) {
			$currentClass = $this->curUrlInfo['act'] === $this->act ? $this->curUrlArray['class'] : '';
			if (!$currentClass) {
				$currentClass = !$this->classesAnchorDefault[$this->act] ? '' : $this->classesAnchorDefault[$this->act];
			}
			$title = $currentClass
				? $this->classesAnchorClassTitle[$currentClass]
				: $this->classesAnchorDefaultTitle[$this->act];
		}
		return '
						<tr>
							<td><label for="rtehtmlarea-browse-links-anchor_title" id="rtehtmlarea-browse-links-title-label">' . $this->getLanguageService()->getLL('anchor_title', TRUE) . ':</label></td>
							<td colspan="3">
								<span id="rtehtmlarea-browse-links-title-input" style="display: ' . ($readOnly ? 'none' : 'inline') . ';">
									<input type="text" id="rtehtmlarea-browse-links-anchor_title" name="anchor_title" value="' . htmlspecialchars($title) . '" ' . $this->doc->formWidth(30) . ' />
								</span>
								<span id="rtehtmlarea-browse-links-title-readonly" style="display: ' . ($readOnly ? 'inline' : 'none') . ';">' . htmlspecialchars($title) . '</span>
							</td>
						</tr>';
	}

	/**
	 * Localize a string using the language of the content element rather than the language of the BE interface
	 *
	 * @param string string: the label to be localized
	 * @return string Localized string.
	 */
	public function getLLContent($string) {
		return $this->contentLanguageService->sL($string);
	}

	/**
	 * Localize a label obtained from Page TSConfig
	 *
	 * @param string $string The label to be localized
	 * @param bool $JScharCode If needs to be converted to an array of char numbers
	 * @return string Localized string
	 */
	public function getPageConfigLabel($string, $JScharCode = TRUE) {
		if (substr($string, 0, 4) !== 'LLL:') {
			$label = $string;
		} else {
			$label = $this->getLanguageService()->sL(trim($string));
		}
		$label = str_replace('"', '\\"', str_replace('\\\'', '\'', $label));
		return $JScharCode ? GeneralUtility::quoteJSvalue($label) : $label;
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * @return BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

}
