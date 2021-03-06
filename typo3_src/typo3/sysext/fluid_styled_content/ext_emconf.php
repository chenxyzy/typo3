<?php
$EM_CONF[$_EXTKEY] = array(
	'title' => 'Fluid Styled Content',
	'description' => 'A set of common content elements based on Fluid for Frontend output.',
	'category' => 'fe',
	'state' => 'experimental',
	'author' => 'TYPO3 Core Team',
	'author_email' => 'info@typo3.org',
	'version' => '7.5.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '7.5.0-7.5.99'
		),
		'conflicts' => array(
			'css_styled_content' => ''
		),
		'suggests' => array(),
	),
);
