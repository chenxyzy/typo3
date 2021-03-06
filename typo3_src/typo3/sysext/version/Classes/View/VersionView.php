<?php
namespace TYPO3\CMS\Version\View;

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

use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Contains some parts for staging, versioning and workspaces
 * to interact with the TYPO3 Core Engine
 */
class VersionView {

	/**
	 * Creates the version selector for the page id inputted.
	 * Moved out of the core file \TYPO3\CMS\Backend\Template\DocumentTemplate
	 *
	 * @param int $id Page id to create selector for.
	 * @param bool $noAction If set, there will be no button for swapping page.
	 * @return void
	 * @see \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public function getVersionSelector($id, $noAction = FALSE) {
		if ($id <= 0) {
			return;
		}
		if ($GLOBALS['BE_USER']->workspace == 0) {
			// Get Current page record:
			$curPage = BackendUtility::getRecord('pages', $id);
			// If the selected page is not online, find the right ID
			$onlineId = $curPage['pid'] == -1 ? $curPage['t3ver_oid'] : $id;
			// Select all versions of online version:
			$versions = BackendUtility::selectVersionsOfRecord('pages', $onlineId, 'uid,pid,t3ver_label,t3ver_oid,t3ver_wsid,t3ver_id', NULL);
			// If more than one was found...:
			if (count($versions) > 1) {
				// Create selector box entries:
				$opt = array();
				foreach ($versions as $vRow) {
					if ($vRow['uid'] == $onlineId) {
						// Live version
						$label = '[' . $GLOBALS['LANG']->sL('LLL:EXT:version/Resources/Private/Language/locallang.xlf:versionSelect.live', TRUE) . ']';
					} else {
						$label = $vRow['t3ver_label'] . ' (' . $GLOBALS['LANG']->sL('LLL:EXT:version/Resources/Private/Language/locallang.xlf:versionId', TRUE) . ' ' . $vRow['t3ver_id'] . ($vRow['t3ver_wsid'] != 0 ? ' ' . $GLOBALS['LANG']->sL('LLL:EXT:version/Resources/Private/Language/locallang.xlf:workspaceId', TRUE) . ' ' . $vRow['t3ver_wsid'] : '') . ')';
					}
					$opt[] = '<option value="' . htmlspecialchars(GeneralUtility::linkThisScript(array('id' => $vRow['uid']))) . '"' . ($id == $vRow['uid'] ? ' selected="selected"' : '') . '>' . htmlspecialchars($label) . '</option>';
				}
				/** @var $iconFactory \TYPO3\CMS\Core\Imaging\IconFactory */
				$iconFactory = GeneralUtility::makeInstance(IconFactory::class);
				// Add management link:
				$management = '
					<a class="btn btn-default" href="' . htmlspecialchars(\TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl('web_txversionM1', array('table' => 'pages', 'uid' => $onlineId))) . '">
						' . $iconFactory->getIcon('actions-version-open', Icon::SIZE_SMALL) . '
						' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:ver.mgm', TRUE) . '
					</a>';
				// Create onchange handler:
				$onChange = 'window.location.href=this.options[this.selectedIndex].value;';
				// Controls:
				if ($id == $onlineId) {
					$controls = '<strong class="text-success">' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:ver.online', TRUE) . '</strong>';
				} elseif (!$noAction) {
					$href = $GLOBALS['TBE_TEMPLATE']->issueCommand(
						'&cmd[pages][' . $onlineId . '][version][swapWith]=' . $id . '&cmd[pages][' . $onlineId . '][version][action]=swap',
						GeneralUtility::linkThisScript(array('id' => $onlineId))
					);
					$controls = '
						<a href="' . htmlspecialchars($href) . '" class="btn btn-default" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:ver.swapPage', TRUE) . '">
							' . $iconFactory->getIcon('actions-version-swap-version', Icon::SIZE_SMALL) . '
							' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:ver.swap', TRUE) . '
						</a>';
				}
				// Write out HTML code:
				return '
					<!--
						Version selector:
					-->
					<div id="typo3-version-selector" class="form-inline form-inline-spaced">
						<div class="form-group">
							<label for="version-selector">' . $GLOBALS['LANG']->sL('LLL:EXT:version/Resources/Private/Language/locallang.xlf:versionSelect.label', TRUE) . '</label>
							<select id="version-selector" class="form-control" onchange="' . htmlspecialchars($onChange) . '">
								' . implode('', $opt) . '
							</select>
						</div>
						<div class="form-group">
							' . $controls . '
						</div>
						<div class="form-group">
							' . $management . '
						</div>
					</div>
				';
			}
		}
	}

}
