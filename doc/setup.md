## How to use \Ink\Framework

The following setup is necessary:

Please note that if the framework is included in the root of the plugin or theme, the required fields can be left unchanged

```php
/**
 * Instantiate & configure the framework
 */
\Ink\Framework::getInstance( 'tester' )->init( [
	//#! [REQUIRED] Provide the system path to the framework directory
	'ink-dir' => trailingslashit( wp_normalize_path( plugin_dir_path( __FILE__ ) ) ) . 'ink-framework/',
	//#! [REQUIRED] Provide the uri to the framework directory
	'ink-uri' => plugin_dir_url( __FILE__ ) . 'ink-framework/',
	//#! [REQUIRED] Provide the path to the WP_CONTENT directory
	'ink-content-dir' => trailingslashit( wp_normalize_path( realpath( plugin_dir_path( __FILE__ ) . '../../' ) ) ),
	//#! [OPTIONAL] Configure logging
	'logging' => [
		'enable-logging' => true,
		'instance-name' => 'ink',
		'log-file-name' => 'tester-debug.log',
		'min-logging-level' => \Ink\Helpers\Logger::SYSTEM,
	],
] );
```






