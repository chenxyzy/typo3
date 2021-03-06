<?php
namespace TYPO3\CMS\Core\Resource\Rendering;

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

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;

/**
 * Class VideoTagRenderer
 */
class VideoTagRenderer implements FileRendererInterface {

	/**
	 * Mime types that can be used in the HTML Video tag
	 *
	 * @var array
	 */
	protected $possibleMimeTypes = array('video/mp4', 'video/webm', 'video/ogg', 'application/ogg');

	/**
	 * Returns the priority of the renderer
	 * This way it is possible to define/overrule a renderer
	 * for a specific file type/context.
	 * For example create a video renderer for a certain storage/driver type.
	 * Should be between 1 and 100, 100 is more important than 1
	 *
	 * @return int
	 */
	public function getPriority() {
		return 1;
	}

	/**
	 * Check if given File(Reference) can be rendered
	 *
	 * @param FileInterface $file File or FileReference to render
	 * @return bool
	 */
	public function canRender(FileInterface $file) {
		return in_array($file->getMimeType(), $this->possibleMimeTypes, TRUE);
	}

	/**
	 * Render for given File(Reference) HTML output
	 *
	 * @param FileInterface $file
	 * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
	 * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
	 * @param array $options controls = TRUE/FALSE (default TRUE), autoplay = TRUE/FALSE (default FALSE), loop = TRUE/FALSE (default FALSE)
	 * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
	 * @return string
	 */
	public function render(FileInterface $file, $width, $height, array $options = array(), $usedPathsRelativeToCurrentScript = FALSE) {

		// If autoplay isn't set manually check if $file is a FileReference take autoplay from there
		if (!isset($options['autoplay']) && $file instanceof FileReference) {
			$autoplay = $file->getProperty('autoplay');
			if ($autoplay !== NULL) {
				$options['autoplay'] = $autoplay;
			}
		}

		$additionalAttributes = array();
		if (!isset($options['controls']) || !empty($options['controls'])) {
			$additionalAttributes[] = 'controls';
		}
		if (!empty($options['autoplay'])) {
			$additionalAttributes[] = 'autoplay';
		}
		if (!empty($options['loop'])) {
			$additionalAttributes[] = 'loop';
		}

		return sprintf(
			'<video width="%d" height="%d"%s><source src="%s" type="%s"></video>',
			(int)$width,
			(int)$height,
			empty($additionalAttributes) ? '' : ' ' . implode(' ', $additionalAttributes),
			htmlspecialchars($file->getPublicUrl($usedPathsRelativeToCurrentScript)),
			$file->getMimeType()
		);
	}

}
