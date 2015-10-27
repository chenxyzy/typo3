<?php
namespace TYPO3\CMS\Frontend\ContentObject;

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

/**
 * Contains IMG_RESOURCE class object.
 */
class ImageResourceContentObject extends AbstractContentObject {

	/**
	 * Rendering the cObject, IMG_RESOURCE
	 *
	 * @param array $conf Array of TypoScript properties
	 * @return string Output
	 */
	public function render($conf = array()) {
		$GLOBALS['TSFE']->lastImgResourceInfo = $this->cObj->getImgResource($conf['file'], $conf['file.']);
		if ($GLOBALS['TSFE']->lastImgResourceInfo) {
			$urlPrefix = '';
			if (parse_url($GLOBALS['TSFE']->lastImgResourceInfo[3], PHP_URL_HOST) === NULL) {
				$urlPrefix = $GLOBALS['TSFE']->absRefPrefix;
			}
			$imageResource = $urlPrefix . $GLOBALS['TSFE']->lastImgResourceInfo[3];
			$theValue = isset($conf['stdWrap.']) ? $this->cObj->stdWrap($imageResource, $conf['stdWrap.']) : $imageResource;
		} else {
			$theValue = '';
		}

		return $theValue;
	}

}