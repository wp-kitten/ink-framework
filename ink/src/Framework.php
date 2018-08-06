<?php

namespace Ink;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit;
}

use Ink\Helpers\Logger;
use Ink\Helpers\Util;
use Ink\Notices\AdminNotice;
use Ink\Notices\UserNotice;
use Ink\Security;


/**
 * Class Framework
 * @package Ink
 *
 * The framework's base class. Standard Singleton
 */
class Framework
{
	/**
	 * Stores the configuration of this instance
	 * @var array
	 */
	private $_options = [];

	/**
	 * Stores the instances of this class
	 * @var array
	 */
	private static $_instances = [];

	/**
	 * Holds the reference to the instance of \Ink\Helpers\Logger class
	 * @var null|\Ink\Helpers\Logger
	 */
	private $_logger = null;

	private static $_instanceName = '';

	/**
	 * Framework constructor.
	 */
	private function __construct()
	{
	}

	/**
	 * Retrieve the reference to the instance of this class
	 * @param string $name The name of the instance to retrieve
	 * @return \Ink\Framework
	 */
	final public static function getInstance( $name )
	{
		if ( ! isset( self::$_instances[$name] ) ) {
			self::$_instances[$name] = new self;
		}
		self::$_instanceName = $name;
		return self::$_instances[$name];
	}

	/**
	 * Instantiate and configure the framework
	 * @param array $options
	 * @return \Ink\Framework
	 */
	public function init( $options = [] )
	{
		$instance = self::$_instances[self::$_instanceName];
		$instance->_options = $options;

		if ( isset( $instance->_options['ink-dir'] ) && ! empty( $instance->_options['ink-dir'] ) ) {
			$instance->_options['ink-dir'] = trailingslashit( wp_normalize_path( $instance->_options['ink-dir'] ) );
		}
		if ( isset( $instance->_options['ink-uri'] ) && ! empty( $instance->_options['ink-uri'] ) ) {
			$instance->_options['ink-uri'] = trailingslashit( $instance->_options['ink-uri'] );
		}
		if ( isset( $instance->_options['ink-content-dir'] ) && ! empty( $instance->_options['ink-content-dir'] ) ) {
			$instance->_options['ink-content-dir'] = trailingslashit( wp_normalize_path( $instance->_options['ink-content-dir'] ) );
		}

		//#! Check settings
		add_action( "admin_notices", [ $this, 'notifyInvalidConfiguration' ], 0 );

		//#! Load the plugin's text domain
		if ( isset( $instance->_options['ink-dir'] ) && ! empty( $instance->_options['ink-dir'] ) ) {
			add_action( 'plugins_loaded', function () {
				$instance = self::$_instances[self::$_instanceName];
				$langFile = $instance->_options['ink-dir'] . 'languages/' . get_locale() . '.mo';
				if ( is_file( $langFile ) ) {
					load_textdomain( 'ink-fw', $langFile );
				}
			} );
		}

		/*
		 * Load resources
		 */
		add_action( 'admin_enqueue_scripts', function () {
			$instance = self::$_instances[self::$_instanceName];
			if ( isset( $instance->_options['ink-uri'] ) && ! empty( $instance->_options['ink-uri'] ) ) {
				wp_enqueue_style( 'ink-framework-styles', $instance->_options['ink-uri'] . 'assets/styles.css' );
				wp_enqueue_script( 'ink-notices', $instance->_options['ink-uri'] . 'assets/ink-notices.js', [ 'jquery' ] );
				wp_localize_script( 'ink-notices', 'InkFw', [
					'admin' => [
						'nonce_name' => AdminNotice::NONCE_NAME,
						'nonce' => wp_create_nonce( AdminNotice::NONCE_ACTION ),
					],
					'user' => [
						'nonce_name' => UserNotice::NONCE_NAME,
						'nonce' => wp_create_nonce( UserNotice::NONCE_ACTION ),
					],
				] );
			}
		}, 80000 );

		/*
		 * Setup logging
		 */
		if ( isset( $instance->_options['ink-content-dir'] ) && ! empty( $instance->_options['ink-content-dir'] ) ) {
			if ( isset( $instance->_options['logging'] ) && ! empty( $instance->_options['logging'] ) ) {
				if ( isset( $instance->_options['logging']['enable-logging'] ) && $instance->_options['logging']['enable-logging'] ) {
					$loggingLevel = ( isset( $instance->_options['logging']['min-logging-level'] ) ? $instance->_options['logging']['min-logging-level'] : Logger::SYSTEM );
					$instanceName = ( isset( $instance->_options['logging']['instance-name'] ) ? $instance->_options['logging']['instance-name'] : 'ink' );
					$logFileName = ( isset( $instance->_options['logging']['log-file-name'] ) ? $instance->_options['logging']['log-file-name'] : 'ink-debug.log' );

					$instance->_logger = Logger::getInstance( $instanceName );
					$instance->_logger
						->setLogFilePath( $instance->_options['ink-content-dir'] . $logFileName )
						->setMinLogLevel( $loggingLevel );
				}
			}
		}

		/*
		 * Setup listeners for notices
		 */
		add_action( 'admin_notices', [ '\\Ink\Notices\\UserNotice', 'renderNoticesNonPersistent' ] );
		add_action( 'admin_notices', [ '\\Ink\Notices\\UserNotice', 'renderNotices' ] );
		add_action( 'wp_ajax_ink_check_delete_notice', [ '\\Ink\Notices\\UserNotice', 'checkDeleteRequest' ] );

		add_action( 'admin_notices', [ '\\Ink\Notices\\AdminNotice', 'renderNoticesNonPersistent' ] );
		add_action( 'admin_notices', [ '\\Ink\Notices\\AdminNotice', 'renderNotices' ] );
		add_action( 'wp_ajax_ink_check_delete_notice', [ '\\Ink\Notices\\AdminNotice', 'checkDeleteRequest' ] );

		return $this;
	}

	/**
	 * Notify and disable the plugin if invalid configuration
	 */
	public function notifyInvalidConfiguration()
	{
		$errors = [];

		$fs = Util::getFileSystem();

		$instance = self::$_instances[self::$_instanceName];
		if ( ! isset( $instance->_options['ink-dir'] ) || empty( $instance->_options['ink-dir'] ) || ! $fs->is_dir( $instance->_options['ink-dir'] ) ) {
			array_push( $errors, esc_html__( '[Ink][Init] Invalid configuration. Please provide the system path to the ink framework directory.', 'ink-fw' ) );
		}
		if ( ! isset( $instance->_options['ink-uri'] ) || empty( $instance->_options['ink-uri'] ) ) {
			array_push( $errors, esc_html__( '[Ink][Init] Invalid configuration. Please provide the HTTP path to the ink framework directory.', 'ink-fw' ) );
		}
		if ( ! isset( $instance->_options['ink-content-dir'] ) || empty( $instance->_options['ink-content-dir'] ) || ! $fs->is_dir( $instance->_options['ink-content-dir'] ) ) {
			array_push( $errors, esc_html__( '[Ink][Init] Invalid configuration. Please provide the system path to the wp-content directory.', 'ink-fw' ) );
		}

		if ( ! empty( $errors ) ) {
			echo '<div class="notice notice-error">';
			foreach ( $errors as $error ) {
				echo "<p>{$error}</p>";
			}
			echo '</div>';
			deactivate_plugins( 'ink-framework/index.php' );
			unset( $_GET['activate'], $_GET['plugin_status'], $_GET['activate-multi'] );
			return;
		}
	}

	/**
	 * Retrieve the class' configuration
	 * @return array
	 */
	public function getConfigList()
	{
		$instance = self::$_instances[self::$_instanceName];
		return $instance->_options;
	}

	/**
	 * Retrieve the specified option from the class' configuration
	 * @param string $optionName
	 * @return mixed|string
	 */
	public function getConfig( $optionName )
	{
		$instance = self::$_instances[self::$_instanceName];
		return ( isset( $instance->_options[$optionName] ) ? $instance->_options[$optionName] : '' );
	}

	/**
	 * Retrieve the reference to the Logger class
	 * @return Logger|null
	 */
	public function getLogger()
	{
		$instance = self::$_instances[self::$_instanceName];
		return $instance->_logger;
	}

}
