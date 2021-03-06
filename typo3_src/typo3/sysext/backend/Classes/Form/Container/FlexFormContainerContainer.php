<?php
namespace TYPO3\CMS\Backend\Form\Container;

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
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Flex form container implementation
 * This one is called by FlexFormSectionContainer and renders HTML for a single container.
 * For processing of single elements FlexFormElementContainer is called
 */
class FlexFormContainerContainer extends AbstractContainer {

	/**
	 * Entry method
	 *
	 * @return array As defined in initializeResultArray() of AbstractNode
	 */
	public function render() {
		$table = $this->data['tableName'];
		$row = $this->data['databaseRow'];
		$fieldName = $this->data['fieldName'];
		$flexFormFormPrefix = $this->data['flexFormFormPrefix'];
		$flexFormContainerElementCollapsed = $this->data['flexFormContainerElementCollapsed'];
		$flexFormContainerTitle = $this->data['flexFormContainerTitle'];
		$flexFormFieldIdentifierPrefix = $this->data['flexFormFieldIdentifierPrefix'];
		$parameterArray = $this->data['parameterArray'];

		// Every container adds its own part to the id prefix
		$flexFormFieldIdentifierPrefix = $flexFormFieldIdentifierPrefix . '-' . GeneralUtility::shortMd5(uniqid('id', TRUE));

		$iconFactory = GeneralUtility::makeInstance(IconFactory::class);
		$toggleIcons = '<span class="t3-flex-control-toggle-icon-open" style="' . ($flexFormContainerElementCollapsed ? 'display: none;' : '') . '">'
			. $iconFactory->getIcon('actions-move-down', Icon::SIZE_SMALL)
			. '</span>';
		$toggleIcons .= '<span class="t3-flex-control-toggle-icon-close" style="' . ($flexFormContainerElementCollapsed ? '' : 'display: none;') . '">'
			. $iconFactory->getIcon('actions-move-right', Icon::SIZE_SMALL)
			. '</span>';

		$flexFormContainerCounter = $this->data['flexFormContainerCounter'];
		$actionFieldName = '_ACTION_FLEX_FORM'
			. $parameterArray['itemFormElName']
			. $this->data['flexFormFormPrefix']
			. '[_ACTION]'
			. '[' . $flexFormContainerCounter . ']';
		$toggleFieldName = 'data[' . $table . '][' . $row['uid'] . '][' . $fieldName . ']'
			. $flexFormFormPrefix
			. '[' . $flexFormContainerCounter . ']'
			. '[_TOGGLE]';

		$moveAndDeleteContent = array();
		$userHasAccessToDefaultLanguage = $this->getBackendUserAuthentication()->checkLanguageAccess(0);
		if ($userHasAccessToDefaultLanguage) {
			$moveAndDeleteContent[] = '<div class="pull-right">';
			$moveAndDeleteContent[] = '<span title="' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:sortable.dragmove', TRUE) . '" class="t3-js-sortable-handle">' . $iconFactory->getIcon('actions-move-move', Icon::SIZE_SMALL) . '</span>';
			$moveAndDeleteContent[] = '<span title="' . $this->getLanguageService()->sL('LLL:EXT:lang/locallang_common.xlf:delete', TRUE) . '" class="t3-js-delete">' . $iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL) . '</span>';
			$moveAndDeleteContent[] = '</div>';
		}

		$options = $this->data;
		$options['flexFormFieldIdentifierPrefix'] = $flexFormFieldIdentifierPrefix;
		// Append container specific stuff to field prefix
		$options['flexFormFormPrefix'] =  $flexFormFormPrefix . '[' . $flexFormContainerCounter . '][' .  $this->data['flexFormContainerName'] . '][el]';
		$options['renderType'] = 'flexFormElementContainer';
		$containerContentResult = $this->nodeFactory->create($options)->render();

		$html = array();
		$html[] = '<div id="' . $flexFormFieldIdentifierPrefix . '" class="t3-form-field-container-flexsections t3-flex-section">';
		$html[] = 	'<input class="t3-flex-control t3-flex-control-action" type="hidden" name="' . htmlspecialchars($actionFieldName) . '" value="" />';
		$html[] = 	'<div class="t3-form-field-header-flexsection t3-flex-section-header">';
		$html[] = 		'<div class="pull-left">';
		$html[] = 			'<a href="#" class="t3-flex-control-toggle-button">' . $toggleIcons . '</a>';
		$html[] = 			'<span class="t3-record-title">' . $flexFormContainerTitle . '</span>';
		$html[] = 		'</div>';
		$html[] = 		implode(LF, $moveAndDeleteContent);
		$html[] = 	'</div>';
		$html[] = 	'<div class="t3-form-field-record-flexsection t3-flex-section-content"' . ($flexFormContainerElementCollapsed ? ' style="display:none;"' : '') . '>';
		$html[] = 		$containerContentResult['html'];
		$html[] = 	'</div>';
		$html[] = 	'<input';
		$html[] = 		'class="t3-flex-control t3-flex-control-toggle"';
		$html[] = 		'id="' . $flexFormFieldIdentifierPrefix . '-toggleClosed"';
		$html[] = 		'type="hidden"';
		$html[] = 		'name="' . htmlspecialchars($toggleFieldName) . '"';
		$html[] = 		'value="' . ($flexFormContainerElementCollapsed ? '1' : '0') . '"';
		$html[] = 	'/>';
		$html[] = '</div>';

		$containerContentResult['html'] = '';
		$resultArray = $this->initializeResultArray();
		$resultArray['html'] = implode(LF, $html);
		$resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $containerContentResult);

		return $resultArray;
	}

	/**
	 * @return BackendUserAuthentication
	 */
	protected function getBackendUserAuthentication() {
		return $GLOBALS['BE_USER'];
	}

	/**
	 * @return LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}
