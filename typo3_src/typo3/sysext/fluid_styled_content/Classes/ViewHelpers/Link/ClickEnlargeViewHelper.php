<?php
namespace TYPO3\CMS\FluidStyledContent\ViewHelpers\Link;

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

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * A view helper for creating a link for an image popup.
 *
 * = Example =
 *
 * <code title="enlarge image on click">
 * <ce:link.clickEnlarge image="{image}" configuration="{settings.images.popup}"><img src=""></ce:link.clickEnlarge>
 * </code>
 *
 * <output>
 * <a href="url" onclick="javascript" target="thePicture"><img src=""></a>
 * </output>
 */
class ClickEnlargeViewHelper extends AbstractViewHelper {

	/**
	 * Initialize ViewHelper arguments
	 *
	 * @return void
	 */
	public function initializeArguments() {
		$this->registerArgument('image', '', 'The original image file', TRUE);
		$this->registerArgument(
			'configuration',
			'mixed',
			'String, \TYPO3\CMS\Core\Resource\File or \TYPO3\CMS\Core\Resource\FileReference with link configuration',
			TRUE
		);
	}

	/**
	 * Render the view helper
	 *
	 * @return string
	 */
	public function render() {
		return self::renderStatic(
			$this->arguments,
			$this->buildRenderChildrenClosure(),
			$this->renderingContext
		);
	}

	/**
	 * @param array $arguments
	 * @param \Closure $renderChildrenClosure
	 * @param RenderingContextInterface $renderingContext
	 * @return string
	 */
	static public function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext) {
		$image = $arguments['image'];
		$configuration = $arguments['configuration'];
		$content = $renderChildrenClosure();
		$configuration['enable'] = TRUE;

		return self::getContentObjectRenderer()->imageLinkWrap($content, $image, $configuration);
	}

	/**
	 * @return ContentObjectRenderer
	 */
	static protected function getContentObjectRenderer() {
		return $GLOBALS['TSFE']->cObj;
	}
}
