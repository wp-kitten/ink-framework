<?php if ( ! defined( 'ABSPATH' ) ) {
	exit( '[Ink Framework] Direct access is not allowed' );
}

/*
 * Bounce if already loaded
 */
if ( defined( 'INK_FRAMEWORK' ) ) {
	return;
}

define( 'INK_FRAMEWORK', true );

/*
 * Autoloader
 */
require_once( dirname( __FILE__ ) . '/autoloader.php' );
$loader = new \Ink\Psr4AutoloaderClass();
$loader->register();
$loader->addNamespace( 'Ink', dirname( __FILE__ ) . '/ink/src' );

/**
 * Instantiate & configure the framework
 */
\Ink\Framework::getInstance( 'ink' )->init( [

	//#! [REQUIRED] Provide the system path to the framework directory
	'ink-dir' => '',
	//#! [REQUIRED] Provide the uri to the framework directory
	'ink-uri' => '',
	//#! [REQUIRED] Provide the path to the WP_CONTENT directory
	'ink-content-dir' => '',

	//#! [OPTIONAL] Configure logging
	'logging' => [
		'enable-logging' => true,
		'instance-name' => 'ink',
		'log-file-name' => 'ink-debug.log',
		'min-logging-level' => \Ink\Helpers\Logger::SYSTEM,
	],
] );

