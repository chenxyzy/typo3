<?php
namespace TYPO3\CMS\Version\Controller;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\DiffUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Versioning module, including workspace management
 */
class VersionModuleController extends \TYPO3\CMS\Backend\Module\BaseScriptClass {

	/**
	 * Module configuration
	 *
	 * @var array
	 */
	public $MCONF = array();

	/**
	 * Module menu items
	 *
	 * @var array
	 */
	public $MOD_MENU = array();

	/**
	 * Module session settings
	 *
	 * @var array
	 */
	public $MOD_SETTINGS = array();

	/**
	 * document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * @var string
	 */
	public $content;

	/**
	 * Accumulated content
	 *
	 * @var int
	 */
	public $showWorkspaceCol = 0;

	/**
	 * @var array
	 */
	public $formatWorkspace_cache = array();

	/**
	 * @var array
	 */
	public $formatCount_cache = array();

	/**
	 * @var array
	 */
	public $targets = array();

	/**
	 * Accumulation of online targets.
	 *
	 * @var string
	 */
	public $pageModule = '';

	/**
	 * Name of page module
	 *
	 * @var bool
	 */
	public $publishAccess = FALSE;

	/**
	 * @var array
	 */
	public $stageIndex = array();

	/**
	 * @var array
	 */
	public $recIndex = array();

	/**
	 * @var IconFactory
	 */
	protected $iconFactory;

	/**
	 * The name of the module
	 *
	 * @var string
	 */
	protected $moduleName = 'web_txversionM1';

	/**
	 * Initialize language files
	 */
	public function __construct() {
		$this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
		$GLOBALS['SOBE'] = $this;
		$GLOBALS['LANG']->includeLLFile('EXT:version/Resources/Private/Language/locallang.xlf');
	}

	/**
	 * Initialize menu configuration
	 *
	 * @return void
	 */
	public function menuConfig() {
		// CLEANSE SETTINGS
		$this->MOD_SETTINGS = BackendUtility::getModuleData($this->MOD_MENU, GeneralUtility::_GP('SET'), $this->moduleName, 'ses');
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 *
	 * @return void
	 */
	public function main() {
		// Template markers
		$markers = array(
			'CSH' => '',
			'FUNC_MENU' => '',
			'WS_MENU' => '',
			'CONTENT' => ''
		);
		// Setting module configuration:
		$this->MCONF['name'] = $this->moduleName;
		$this->REQUEST_URI = str_replace('&sendToReview=1', '', GeneralUtility::getIndpEnv('REQUEST_URI'));
		// Draw the header.
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->setModuleTemplate('EXT:version/Resources/Private/Templates/version.html');

		// Setting up the context sensitive menu:
		$this->doc->getContextMenuCode();
		// Getting input data:
		$this->id = (int)GeneralUtility::_GP('id');

		// Record uid. Goes with table name to indicate specific record
		$this->uid = (int)GeneralUtility::_GP('uid');
		// // Record table. Goes with uid to indicate specific record
		$this->table = GeneralUtility::_GP('table');

		$this->details = GeneralUtility::_GP('details');
		// Page id. If set, indicates activation from Web>Versioning module
		$this->diffOnly = GeneralUtility::_GP('diffOnly');
		// Flag. If set, shows only the offline version and with diff-view
		// Force this setting:
		$this->MOD_SETTINGS['expandSubElements'] = TRUE;
		$this->MOD_SETTINGS['diff'] = $this->details || $this->MOD_SETTINGS['diff'] ? 1 : 0;
		// Reading the record:
		$record = BackendUtility::getRecord($this->table, $this->uid);
		if ($record['pid'] == -1) {
			$record = BackendUtility::getRecord($this->table, $record['t3ver_oid']);
		}
		$this->recordFound = is_array($record);
		$pidValue = $this->table === 'pages' ? $this->uid : $record['pid'];
		// Checking access etc.
		if ($this->recordFound && $GLOBALS['TCA'][$this->table]['ctrl']['versioningWS'] && !$this->id) {
			$this->uid = $record['uid'];
			// Might have changed if new live record was found!
			// Access check!
			// The page will show only if there is a valid page and if this page may be viewed by the user
			$this->pageinfo = BackendUtility::readPageAccess($pidValue, $this->perms_clause);
			$access = is_array($this->pageinfo) ? 1 : 0;
			if ($pidValue && $access || $GLOBALS['BE_USER']->user['admin'] && !$pidValue) {
				// If another page module was specified, replace the default Page module with the new one
				$newPageModule = trim($GLOBALS['BE_USER']->getTSConfigVal('options.overridePageModule'));
				$this->pageModule = BackendUtility::isModuleSetInTBE_MODULES($newPageModule) ? $newPageModule : 'web_layout';
				// Setting publish access permission for workspace:
				$this->publishAccess = $GLOBALS['BE_USER']->workspacePublishAccess($GLOBALS['BE_USER']->workspace);
				$this->versioningMgm();
			}
			// Setting up the buttons and markers for docheader
			$docHeaderButtons = $this->getButtons();
			$markers['CSH'] = $docHeaderButtons['csh'];
			$markers['FUNC_MENU'] = BackendUtility::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function']);
			$markers['CONTENT'] = $this->content;
		} else {
			// If no access or id value, create empty document
			$this->content = $this->doc->section($GLOBALS['LANG']->getLL('clickAPage_header'), $GLOBALS['LANG']->getLL('clickAPage_content'), 0, 1);
			// Setting up the buttons and markers for docheader
			$docHeaderButtons = $this->getButtons();
			$markers['CONTENT'] = $this->content;
		}
		// Build the <body> for the module
		$this->content = $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
	}

	/**
	 * Outputs accumulated module content to browser.
	 *
	 * @return void
	 * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8
	 */
	public function printContent() {
		GeneralUtility::logDeprecatedFunction();
		echo $this->content;
	}

	/**
	 * Create the panel of buttons for submitting the form or otherwise perform operations.
	 *
	 * @return array All available buttons as an assoc. array
	 */
	protected function getButtons() {
		$buttons = array(
			'csh' => '',
			'view' => '',
			'record_list' => '',
			'shortcut' => ''
		);
		// CSH
		if ($this->recordFound && $GLOBALS['TCA'][$this->table]['ctrl']['versioningWS']) {
			// View page
			$buttons['view'] = '
				<a href="#" onclick="' . htmlspecialchars(BackendUtility::viewOnClick($this->pageinfo['uid'], '', BackendUtility::BEgetRootLine($this->pageinfo['uid']))) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.showPage', TRUE) . '">
					' . $this->iconFactory->getIcon('actions-document-view', Icon::SIZE_SMALL) . '
				</a>';
			// Shortcut
			if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
				$buttons['shortcut'] = $this->doc->makeShortcutIcon('id, edit_record, pointer, new_unique_uid, search_field, search_levels, showLimit', implode(',', array_keys($this->MOD_MENU)), $this->moduleName);
			}
			// If access to Web>List for user, then link to that module.
			$buttons['record_list'] = BackendUtility::getListViewLink(array(
				'id' => $this->pageinfo['uid'],
				'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
			), '', $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.showList'));
		}
		return $buttons;
	}

	/******************************
	 *
	 * Versioning management
	 *
	 ******************************/
	/**
	 * Management of versions for record
	 *
	 * @return void
	 */
	public function versioningMgm() {
		// Diffing:
		$diff_1 = GeneralUtility::_POST('diff_1');
		$diff_2 = GeneralUtility::_POST('diff_2');
		if (GeneralUtility::_POST('do_diff')) {
			$content = '';
			$content .= '<div class="panel panel-space panel-default">';
			$content .= '<div class="panel-heading">' . $GLOBALS['LANG']->getLL('diffing') . '</div>';
			if ($diff_1 && $diff_2) {
				$diff_1_record = BackendUtility::getRecord($this->table, $diff_1);
				$diff_2_record = BackendUtility::getRecord($this->table, $diff_2);
				if (is_array($diff_1_record) && is_array($diff_2_record)) {
					$diffUtility = GeneralUtility::makeInstance(DiffUtility::class);
					$rows = array();
					$rows[] = '
									<tr>
										<th>' . $GLOBALS['LANG']->getLL('fieldname') . '</th>
										<th width="98%">' . $GLOBALS['LANG']->getLL('coloredDiffView') . ':</th>
									</tr>
								';
					foreach ($diff_1_record as $fN => $fV) {
						if ($GLOBALS['TCA'][$this->table]['columns'][$fN] && $GLOBALS['TCA'][$this->table]['columns'][$fN]['config']['type'] !== 'passthrough' && !GeneralUtility::inList('t3ver_label', $fN)) {
							if ((string)$diff_1_record[$fN] !== (string)$diff_2_record[$fN]) {
								$diffres = $diffUtility->makeDiffDisplay(
									BackendUtility::getProcessedValue($this->table, $fN, $diff_2_record[$fN], 0, 1),
									BackendUtility::getProcessedValue($this->table, $fN, $diff_1_record[$fN], 0, 1)
								);
								$rows[] = '
									<tr>
										<td>' . $fN . '</td>
										<td width="98%">' . $diffres . '</td>
									</tr>
								';
							}
						}
					}
					if (count($rows) > 1) {
						$content .= '<div class="table-fit"><table class="table">' . implode('', $rows) . '</table></div>';
					} else {
						$content .= '<div class="panel-body">' . $GLOBALS['LANG']->getLL('recordsMatchesCompletely') . '</div>';
					}
				} else {
					$content .= '<div class="panel-body">' . $GLOBALS['LANG']->getLL('errorRecordsNotFound') . '</div>';
				}
			} else {
				$content .= '<div class="panel-body">' . $GLOBALS['LANG']->getLL('errorDiffSources') . '</div>';
			}
			$content .= '</div>';
		}
		// Element:
		$record = BackendUtility::getRecord($this->table, $this->uid);
		$recTitle = BackendUtility::getRecordTitle($this->table, $record, TRUE);
		// Display versions:
		$content .= '
			<form name="theform" action="' . str_replace('&sendToReview=1', '', $this->REQUEST_URI) . '" method="post">
				<div class="panel panel-space panel-default">
				<div class="panel-heading">' . $recTitle . '</div>
					<div class="table-fit">
						<table class="table">
							<thead>
								<tr>
									<th colspan="2" class="col-icon"></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_title') . '">' . $GLOBALS['LANG']->getLL('tblHeader_title') . '</th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_uid') . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_uid') . '</i></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_oid') . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_oid') . '</i></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_id') . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_id') . '</i></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_wsid') . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_wsid') . '</i></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_state', TRUE) . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_state') . '</i></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_stage') . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_stage') . '</i></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_count') . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_count') . '</i></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_pid') . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_pid') . '</i></th>
									<th title="' . $GLOBALS['LANG']->getLL('tblHeaderDesc_t3ver_label') . '"><i>' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_label') . '</i></th>
									<th></th>
									<th colspan="2">
										<button class="btn btn-default btn-sm" type="submit"  name="do_diff" value="true">
											' . $GLOBALS['LANG']->getLL('diff') . '
										</button>
									</th>
								</tr>
							</thead>
							<tbody>
			';
		$versions = BackendUtility::selectVersionsOfRecord($this->table, $this->uid, '*', $GLOBALS['BE_USER']->workspace);
		foreach ($versions as $row) {
			$adminLinks = $this->adminLinks($this->table, $row);
			$content .= '
				<tr' . ($row['uid'] != $this->uid ? '' : ' class="active"') . '>
					<td class="col-icon">' .
						($row['uid'] != $this->uid ?
							'<a href="' . $this->doc->issueCommand('&cmd[' . $this->table . '][' . $this->uid . '][version][swapWith]=' . $row['uid'] . '&cmd[' . $this->table . '][' . $this->uid . '][version][action]=swap') . '" title="' . $GLOBALS['LANG']->getLL('swapWithCurrent', TRUE) . '">' . $this->iconFactory->getIcon('actions-version-swap-version', Icon::SIZE_SMALL) . '</a>' :
							'<span title="' . $GLOBALS['LANG']->getLL('currentOnlineVersion', TRUE) . '">' . $this->iconFactory->getIcon('status-status-current', Icon::SIZE_SMALL) . '</span>'
						) . '
					</td>
					<td class="col-icon">' . $this->iconFactory->getIconForRecord($this->table, $row, Icon::SIZE_SMALL)->render() . '</td>
					<td>' . htmlspecialchars(BackendUtility::getRecordTitle($this->table, $row, TRUE)) . '</td>
					<td>' . $row['uid'] . '</td>
					<td>' . $row['t3ver_oid'] . '</td>
					<td>' . $row['t3ver_id'] . '</td>
					<td>' . $row['t3ver_wsid'] . '</td>
					<td>' . $row['t3ver_state'] . '</td>
					<td>' . $row['t3ver_stage'] . '</td>
					<td>' . $row['t3ver_count'] . '</td>
					<td>' . $row['pid'] . '</td>
					<td>
						<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick('&edit[' . $this->table . '][' . $row['uid'] . ']=edit&columnsOnly=t3ver_label')) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:cm.edit', TRUE) . '">
							' . $this->iconFactory->getIcon('actions-document-open', Icon::SIZE_SMALL) . '
						</a>' . htmlspecialchars($row['t3ver_label']) . '
					</td>
					<td class="col-control">' . $adminLinks . '</td>
					<td class="text-center success"><input type="radio" name="diff_1" value="' . $row['uid'] . '"' . ($diff_1 == $row['uid'] ? ' checked="checked"' : '') . '/></td>
					<td class="text-center danger"><input type="radio" name="diff_2" value="' . $row['uid'] . '"' . ($diff_2 == $row['uid'] ? ' checked="checked"' : '') . '/></td>
				</tr>';
			// Show sub-content if the table is pages AND it is not the online branch (because that will mostly render the WHOLE tree below - not smart;)
			if ($this->table === 'pages' && $row['uid'] != $this->uid) {
				$sub = $this->pageSubContent($row['uid']);
				if ($sub) {
					$content .= '
						<tr>
							<td colspan="2"></td>
							<td colspan="11">' . $sub . '</td>
							<td class="success"></td>
							<td class="danger"></td>
						</tr>';
				}
			}
		}
		$content .= '
							</tbody>
						</table>
					</div>
				</div>
			</form>';
		$this->content .= $this->doc->section($GLOBALS['LANG']->getLL('title'), $content, 0, 1);
		// Create new:
		$content = '
			<form action="' . htmlspecialchars(BackendUtility::getModuleUrl('tce_db')) . '" method="post">
				<div class="row">
					<div class="col-sm-6 col-md-4 col-lg-3">
						<div class="form-group">
							<label for="typo3-new-version-label">' . $GLOBALS['LANG']->getLL('tblHeader_t3ver_label') . '</label>
							<input id="typo3-new-version-label" class="form-control" type="text" name="cmd[' . $this->table . '][' . $this->uid . '][version][label]" />
						</div>
						<div class="form-group">
							<input type="hidden" name="cmd[' . $this->table . '][' . $this->uid . '][version][action]" value="new" />
							<input type="hidden" name="prErr" value="1" />
							<input type="hidden" name="redirect" value="' . htmlspecialchars($this->REQUEST_URI) . '" />
							<input class="btn btn-default" type="submit" name="_" value="' . $GLOBALS['LANG']->getLL('createNewVersion') . '" />
						</div>
					</div>
				</div>
			</form>

		';
		$this->content .= $this->doc->section($GLOBALS['LANG']->getLL('createNewVersion'), $content, 0, 1);
	}

	/**
	 * Recursively look for children for page version with $pid
	 *
	 * @param int $pid UID of page record for which to look up sub-elements following that version
	 * @param int $c Counter, do not set (limits to 100 levels)
	 * @return string Table with content if any
	 */
	public function pageSubContent($pid, $c = 0) {
		$tableNames = ArrayUtility::removeArrayEntryByValue(array_keys($GLOBALS['TCA']), 'pages');
		$tableNames[] = 'pages';
		$content = '';
		foreach ($tableNames as $table) {
			// Basically list ALL tables - not only those being copied might be found!
			$mres = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, 'pid=' . (int)$pid . BackendUtility::deleteClause($table), '', $GLOBALS['TCA'][$table]['ctrl']['sortby'] ? $GLOBALS['TCA'][$table]['ctrl']['sortby'] : '');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($mres)) {
				$content .= '
					<table class="table">
						<tr>
							<th class="col-icon">' . $this->iconFactory->getIconForRecord($table, array(), Icon::SIZE_SMALL)->render() . '</th>
							<th class="col-title">' . $GLOBALS['LANG']->sL($GLOBALS['TCA'][$table]['ctrl']['title'], TRUE) . '</th>
							<th></th>
							<th></th>
						</tr>';
				while ($subrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($mres)) {
					$ownVer = $this->lookForOwnVersions($table, $subrow['uid']);
					$content .= '
						<tr>
							<td class="col-icon">' . $this->iconFactory->getIconForRecord($table, $subrow, Icon::SIZE_SMALL)->render() . '</td>
							<td class="col-title">' . htmlspecialchars(BackendUtility::getRecordTitle($table, $subrow, TRUE)) . '</td>
							<td>' . ($ownVer > 1 ? '<a href="' . htmlspecialchars(BackendUtility::getModuleUrl('web_txversionM1', array('table' => $table, 'uid' => $subrow['uid']))) . '">' . ($ownVer - 1) . '</a>' : '') . '</td>
							<td class="col-control">' . $this->adminLinks($table, $subrow) . '</td>
						</tr>';
					if ($table == 'pages' && $c < 100) {
						$sub = $this->pageSubContent($subrow['uid'], $c + 1);
						if ($sub) {
							$content .= '
								<tr>
									<td></td>
									<td></td>
									<td></td>
									<td width="98%">' . $sub . '</td>
								</tr>';
						}
					}
				}
				$content .= '</table>';
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($mres);
		}
		return $content;
	}

	/**
	 * Look for number of versions of a record
	 *
	 * @param string $table Table name
	 * @param int $uid Record uid
	 * @return int Number of versions for record, FALSE if none.
	 */
	public function lookForOwnVersions($table, $uid) {
		$versions = BackendUtility::selectVersionsOfRecord($table, $uid, 'uid', NULL);
		if (is_array($versions)) {
			return count($versions);
		}
		return FALSE;
	}

	/**
	 * Administrative links for a table / record
	 *
	 * @param string $table Table name
	 * @param array $row Record for which administrative links are generated.
	 * @return string HTML link tags.
	 */
	public function adminLinks($table, $row) {
		// Edit link:
		$adminLink = '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick('&edit[' . $table . '][' . $row['uid'] . ']=edit')) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:cm.edit', TRUE) . '">' . $this->iconFactory->getIcon('actions-document-open', Icon::SIZE_SMALL) . '</a>';
		// Delete link:
		$adminLink .= '<a class="btn btn-default" href="' . htmlspecialchars($this->doc->issueCommand('&cmd[' . $table . '][' . $row['uid'] . '][delete]=1')) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:cm.delete', TRUE) . '">' . $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL) . '</a>';
		if ($table === 'pages') {
			// If another page module was specified, replace the default Page module with the new one
			$newPageModule = trim($GLOBALS['BE_USER']->getTSConfigVal('options.overridePageModule'));
			$pageModule = BackendUtility::isModuleSetInTBE_MODULES($newPageModule) ? $newPageModule : 'web_layout';
			// Perform some access checks:
			$a_wl = $GLOBALS['BE_USER']->check('modules', 'web_list');
			$a_wp = $GLOBALS['BE_USER']->check('modules', $pageModule);
			$adminLink .= '<a class="btn btn-default" href="#" onclick="top.loadEditId(' . $row['uid'] . ');top.goToModule(\'' . $pageModule . '\'); return false;">'
				. $this->iconFactory->getIcon('actions-page-open', Icon::SIZE_SMALL)
				. '</a>';
			$adminLink .= '<a class="btn btn-default" href="#" onclick="top.loadEditId(' . $row['uid'] . ');top.goToModule(\'web_list\'); return false;">' . $this->iconFactory->getIcon('actions-system-list-open', Icon::SIZE_SMALL) . '</a>';
			// "View page" icon is added:
			$adminLink .= '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(BackendUtility::viewOnClick($row['uid'], '', BackendUtility::BEgetRootLine($row['uid']))) . '">' . $this->iconFactory->getIcon('actions-document-view', Icon::SIZE_SMALL) . '</a>';
		} else {
			if ($row['pid'] == -1) {
				$getVars = '&ADMCMD_vPrev[' . rawurlencode(($table . ':' . $row['t3ver_oid'])) . ']=' . $row['uid'];
				// "View page" icon is added:
				$adminLink .= '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars(BackendUtility::viewOnClick($row['_REAL_PID'], '', BackendUtility::BEgetRootLine($row['_REAL_PID']), '', '', $getVars)) . '">' . $this->iconFactory->getIcon('actions-document-view', Icon::SIZE_SMALL) . '</a>';
			}
		}
		return '<div class="btn-group btn-group-sm" role="group">' . $adminLink . '</div>';
	}


	/**
	 * Injects the request object for the current request and gathers all data.
	 *
	 * @param ServerRequestInterface $request the current request
	 * @param ResponseInterface $response the prepared response
	 * @return ResponseInterface the response with the content
	 */
	public function mainAction(ServerRequestInterface $request, ResponseInterface $response) {
		$this->init();
		$this->main();

		$response->getBody()->write($this->content);
		return $response;
	}
}
