<?php
namespace TYPO3\CMS\Core\Tests\Unit\Resource\Rendering;

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

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\YouTubeHelper;
use TYPO3\CMS\Core\Resource\Rendering\YouTubeRenderer;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class YouTubeRendererTest
 */
class YouTubeRendererTest extends UnitTestCase {

	/**
	 * @var YouTubeRenderer|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected $subject;

	/**
	 * Set up the test
	 */
	protected function setUp() {
		parent::setUp();
		GeneralUtility::flushInternalRuntimeCaches();
		$_SERVER['HTTP_HOST'] = 'test.server.org';

		/** @var YouTubeHelper|\PHPUnit_Framework_MockObject_MockObject $youTubeHelper */
		$youTubeHelper = $this->getAccessibleMock(YouTubeHelper::class, array('getOnlineMediaId'), array('youtube'));
		$youTubeHelper->expects($this->any())->method('getOnlineMediaId')->will($this->returnValue('7331'));

		$this->subject = $this->getAccessibleMock(YouTubeRenderer::class, array('getOnlineMediaHelper'), array());
		$this->subject ->expects($this->any())->method('getOnlineMediaHelper')->will($this->returnValue($youTubeHelper));
	}

	/**
	 * @test
	 */
	public function getPriorityReturnsCorrectValue() {
		$this->assertSame(1, $this->subject->getPriority());
	}

	/**
	 * @test
	 */
	public function canRenderReturnsTrueOnCorrectFile() {
		/** @var File|\PHPUnit_Framework_MockObject_MockObject $fileResourceMock1 */
		$fileResourceMock1 = $this->getMock(File::class, array(), array(), '', FALSE);
		$fileResourceMock1->expects($this->any())->method('getMimeType')->will($this->returnValue('video/youtube'));
		/** @var File|\PHPUnit_Framework_MockObject_MockObject $fileResourceMock2 */
		$fileResourceMock2 = $this->getMock(File::class, array(), array(), '', FALSE);
		$fileResourceMock2->expects($this->any())->method('getMimeType')->will($this->returnValue('video/unknown'));
		$fileResourceMock2->expects($this->any())->method('getExtension')->will($this->returnValue('youtube'));

		$this->assertTrue($this->subject->canRender($fileResourceMock1));
		$this->assertTrue($this->subject->canRender($fileResourceMock2));
	}

	/**
	 * @test
	 */
	public function canRenderReturnsFalseOnCorrectFile() {
		/** @var File|\PHPUnit_Framework_MockObject_MockObject $fileResourceMock */
		$fileResourceMock = $this->getMock(File::class, array(), array(), '', FALSE);
		$fileResourceMock->expects($this->any())->method('getMimeType')->will($this->returnValue('video/vimeo'));

		$this->assertFalse($this->subject->canRender($fileResourceMock));
	}

	/**
	 * @test
	 */
	public function renderOutputIsCorrect() {
		/** @var File|\PHPUnit_Framework_MockObject_MockObject $fileResourceMock */
		$fileResourceMock = $this->getMock(File::class, array(), array(), '', FALSE);

		$this->assertSame(
			'<div class="video-container"><iframe src="//www.youtube.com/embed/7331?autohide=1&amp;controls=2&amp;enablejsapi=1&amp;origin=test.server.org&amp;showinfo=0" width="300" height="200" allowfullscreen></iframe></div>',
			$this->subject->render($fileResourceMock, '300m', '200')
		);
	}

	/**
	 * @test
	 */
	public function renderOutputWithLoopIsCorrect() {
		/** @var File|\PHPUnit_Framework_MockObject_MockObject $fileResourceMock */
		$fileResourceMock = $this->getMock(File::class, array(), array(), '', FALSE);

		$this->assertSame(
			'<div class="video-container"><iframe src="//www.youtube.com/embed/7331?autohide=1&amp;controls=2&amp;loop=1&amp;enablejsapi=1&amp;origin=test.server.org&amp;showinfo=0" width="300" height="200" allowfullscreen></iframe></div>',
			$this->subject->render($fileResourceMock, '300m', '200', array('loop' => 1))
		);
	}

	/**
	 * @test
	 */
	public function renderOutputWithAutoplayIsCorrect() {
		/** @var File|\PHPUnit_Framework_MockObject_MockObject $fileResourceMock */
		$fileResourceMock = $this->getMock(File::class, array(), array(), '', FALSE);

		$this->assertSame(
			'<div class="video-container"><iframe src="//www.youtube.com/embed/7331?autohide=1&amp;controls=2&amp;autoplay=1&amp;enablejsapi=1&amp;origin=test.server.org&amp;showinfo=0" width="300" height="200" allowfullscreen></iframe></div>',
			$this->subject->render($fileResourceMock, '300m', '200', array('autoplay' => 1))
		);
	}

	/**
	 * @test
	 */
	public function renderOutputWithAutoplayAndWithoutControllsIsCorrect() {
		/** @var File|\PHPUnit_Framework_MockObject_MockObject $fileResourceMock */
		$fileResourceMock = $this->getMock(File::class, array(), array(), '', FALSE);

		$this->assertSame(
			'<div class="video-container"><iframe src="//www.youtube.com/embed/7331?autohide=1&amp;autoplay=1&amp;enablejsapi=1&amp;origin=test.server.org&amp;showinfo=0" width="300" height="200" allowfullscreen></iframe></div>',
			$this->subject->render($fileResourceMock, '300m', '200', array('controls' => 0, 'autoplay' => 1))
		);
	}

}
