<?php
$EM_CONF[$_EXTKEY] = array(
	'title' => 'Help>TYPO3 Manual',
	'description' => 'Shows TYPO3 inline user manual.',
	'category' => 'module',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'author' => 'Kasper Skaarhoj',
	'author_email' => 'kasperYYYY@typo3.com',
	'author_company' => 'Curby Soft Multimedia',
	'version' => '7.5.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '7.5.0-7.5.99',
		),
		'conflicts' => array(),
		'suggests' => array(),
	),
);
