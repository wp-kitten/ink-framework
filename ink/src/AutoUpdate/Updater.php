<?php

namespace Ink\AutoUpdate;

use Ink\Framework;
use Ink\Helpers\Util;
use Ink\Notices\AdminNotice;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}

/**
 * Class Updater
 * @package Ink\AutoUpdate
 *
 * Utility class that authors can use to automatically update their plugins and themes.
 * The endpoint MUST return the download url for the update archive.
 * The update archive MUST contain a directory which MUST be named exactly as the product
 * The directory can either contain a full product or individual files (patches)
 */
class Updater
{
	const NONCE_NAME = 'ink_fw_updater_nonce';
	const NONCE_ACTION = 'ink_fw_updater_check';

	const TYPE_PLUGIN = 'x000a';
	const TYPE_THEME = 'x000b';

	/*
	 * The name of the option that will store the updates check
	 */
	const UPDATE_CHECK_NAME = 'ink-framework-updates-check';
	/*
	 * The name of the option that will notify if an update is already in progress
	 */
	const UPDATE_PROCESS_NAME = 'ink-framework-update-process';
	/*
	 * The name of the option that will cache the updates
	 */
	const UPDATES_OPTION_NAME = 'ink-framework-updates';

	private static $_options = [
		/*
		 * Whether or not to automatically apply the update
		 * @optional
		 */
		'auto-update' => false,
		/*
		 * The interval, in hours, between updates check
		 * @optional
		 */
		'update-check-interval' => 12,
		/*
		 * The URL to the endpoint to get the response from
		 * @required
		 */
		'endpoint' => '',
		/*
		 * The system path to the plugin/theme file. If the product is a plugin, then the path is to the plugin's file, otherwise to the theme's stylesheet
		 * @required
		 */
		'product-file-path' => '',
		/*
		 * The name of the product to check for updates
		 * @required
		 */
		'product-name' => '',
		/*
		 * The type of the product to check for updates: plugin or theme
		 * @required
		 */
		'product-type' => '',
	];


	/**
	 * Initialize the class
	 * @param array $options
	 */
	public static function init( $options = [] )
	{
		if ( ! empty( $options ) ) {
			self::$_options = array_merge( self::$_options, $options );
		}

		$hasErrors = false;

		if ( empty( self::$_options['endpoint'] ) ) {
			AdminNotice::add( esc_html__( '[Ink-Framework][AutoUpdater][init] Error: Invalid Configuration. Please setup the endpoint.', 'ink-fw' ) );
			$hasErrors = true;
		}
		if ( empty( self::$_options['product-file-path'] ) ) {
			AdminNotice::add( esc_html__( '[Ink-Framework][AutoUpdater][init] Error: Invalid Configuration. Please specify the path to the plugin file.', 'ink-fw' ) );
			$hasErrors = true;
		}
		if ( empty( self::$_options['product-name'] ) ) {
			AdminNotice::add( esc_html__( '[Ink-Framework][AutoUpdater][init] Error: Invalid Configuration. Please specify the product name to get updates for.', 'ink-fw' ) );
			$hasErrors = true;
		}
		if ( empty( self::$_options['product-type'] ) ) {
			AdminNotice::add( esc_html__( '[Ink-Framework][AutoUpdater][init] Error: Invalid Configuration. Please specify the product type.', 'ink-fw' ) );
			$hasErrors = true;
		}

		if ( ! in_array( self::$_options['product-type'], [ self::TYPE_PLUGIN, self::TYPE_THEME ] ) ) {
			AdminNotice::add( esc_html__( '[Ink-Framework][AutoUpdater][init] Error: Invalid Configuration. Please specify a valid product type.', 'ink-fw' ) );
			$hasErrors = true;
		}

		if ( $hasErrors ) {
			return;
		}

		//#! Ensure we have a valid interval and is set to at least 4 hours
		$updateInterval = intval( self::$_options['update-check-interval'] );
		if ( empty( $updateInterval ) || $updateInterval < 4 ) {
			self::$_options['update-check-interval'] = 12;
		}

		self::$_options['product-file-path'] = wp_normalize_path( self::$_options['product-file-path'] );

		$self = get_class();

		//#! Setup the maintenance mode for unauthenticated users & display the update availability in the notice ("auto-update" => false)
		add_action( 'current_screen', [ $self, 'checkForUpdates' ] );
		//#! This is for "auto-update" => false when the user chooses to update
		add_action( 'admin_init', [ $self, 'checkApplyUpdate' ] );
	}

	/**
	 * Check for updates. If $options['auto-update'] is set to false, then an admin notice will be displayed. Otherwise, the update will be automatically applied.
	 * @hooked to "admin_notices"
	 */
	public static function checkForUpdates()
	{
		if ( ! self::__canCheck() ) {
			return;
		}

		//#! Get the updates
		$updates = self::__getUpdates();
		if ( empty( $updates ) ) {
			//#! Save state and try again later
			set_site_transient( self::UPDATE_CHECK_NAME, true, self::$_options['update-check-interval'] );
			return;
		}

		$productInfo = self::__getProductInfo();
		//#! Check to see if there is an update for this version of the plugin
		if ( ! isset( $updates[$productInfo['Version']] ) ) {
			//#! No updates for this version
			//#! Save state and try again later
			set_site_transient( self::UPDATE_CHECK_NAME, true, self::$_options['update-check-interval'] );
			return;
		}

		//#! Check to see whether or not we've applied the latest update
		$versionUpdate = $updates[$productInfo['Version']]['version'];
		if ( ! empty( $productInfo['Patch'] ) && version_compare( $versionUpdate, $productInfo['Patch'], '<=' ) ) {
			//#! No updates for this version
			//#! Save state and try again later
			set_site_transient( self::UPDATE_CHECK_NAME, true, self::$_options['update-check-interval'] );
			return;
		}

		//#! If the auto-update option is enabled, enable maintenance mode and apply the update
		if ( self::$_options['auto-update'] ) {
			//#! Apply the update
			self::__downloadApplyUpdate( $updates[$productInfo['Version']]['url'], $versionUpdate );
		}
		//#! Otherwise, notify the user there is an update available (if not in process of updating)
		else {
			if ( is_admin() ) {
				$screen = get_current_screen();

				//#! Hide the notice on dash
				if ( $screen->id == "dashboard" ) {
					return;
				}

				//#! Show the notice on any other page
				$updateUrl = wp_nonce_url(
					add_query_arg( [ 'ink-framework-updater' => 'apply-update' ] ),
					self::NONCE_ACTION,
					self::NONCE_NAME
				);
				AdminNotice::add(
					sprintf(
						__( 'An update (<strong>v%s</strong>) is available for <strong>%s</strong>. <a href="%s">Apply the update</a>.', 'ink-fw' ),
						$versionUpdate,
						self::$_options['product-name'],
						$updateUrl
					),
					AdminNotice::TYPE_INFO,
					false
				);
			}
			return;
		}
	}

	/**
	 * Check to see if we have the keys present in the URL and if they are, then check and apply the update
	 */
	public static function checkApplyUpdate()
	{
		if ( isset( $_REQUEST['ink-framework-updater'] ) && ( 'apply-update' == $_REQUEST['ink-framework-updater'] ) ) {
			if ( isset( $_REQUEST[self::NONCE_NAME] ) && wp_verify_nonce( $_REQUEST[self::NONCE_NAME], self::NONCE_ACTION ) ) {
				if ( ! self::__canCheck() ) {
					return;
				}
				self::__downloadApplyUpdate();
			}
		}
	}

	/**
	 * Download and apply the update
	 *
	 * @param null|string $updateZipUrl The link to the update zip archive
	 * @param null|string $updateVersionAvailable The version to update to
	 */
	private static function __downloadApplyUpdate( $updateZipUrl = null, $updateVersionAvailable = null )
	{
		//#! Enable maintenance mode
		add_action( 'get_header', [ get_class(), 'enableMaintenanceMode' ] );

		$hasArgs = true;
		/*
		 * Since this method is called from two different contexts, check the args
		 */
		if ( empty( $updateZipUrl ) || empty( $updateVersionAvailable ) ) {
			$updates = self::__getUpdates();
			if ( empty( $updates ) ) {
				//#! Nothing to do here
				return;
			}
			$productInfo = self::__getProductInfo();
			$updateVersionAvailable = $updates[$productInfo['Version']]['version'];
			$updateZipUrl = $updates[$productInfo['Version']]['url'];
			$hasArgs = false;
		}

		//#! Notify that we're already in the process of updating
		set_site_transient( self::UPDATE_PROCESS_NAME, 'prepare download patch' );
		$response = wp_remote_get( $updateZipUrl );
		if ( is_wp_error( $response ) ) {
			AdminNotice::add( '[Ink-Framework][Updater][Apply update] ' . esc_html__( 'Error:', 'ink-fw' ) . ' ' . $response->get_error_message(), AdminNotice::TYPE_ERROR );
			set_site_transient( self::UPDATE_CHECK_NAME, HOUR_IN_SECONDS * self::$_options['update-check-interval'] );
			delete_site_transient( self::UPDATE_PROCESS_NAME );
			delete_site_transient( self::UPDATES_OPTION_NAME );
			return;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			AdminNotice::add( '[Ink-Framework][Updater][Apply update] ' . esc_html__( 'Error: Empty response from server', 'ink-fw' ), AdminNotice::TYPE_ERROR );
			set_site_transient( self::UPDATE_CHECK_NAME, HOUR_IN_SECONDS * self::$_options['update-check-interval'] );
			delete_site_transient( self::UPDATE_PROCESS_NAME );
			delete_site_transient( self::UPDATES_OPTION_NAME );
			return;
		}

		//#! Download and apply the update
		$wp_filesystem = Util::getFileSystem();
		$uploadsDir = wp_upload_dir();
		$saveDir = trailingslashit( $uploadsDir['basedir'] ) . 'ink-fw';
		$archivePath = trailingslashit( $saveDir ) . basename( $updateZipUrl );
		wp_mkdir_p( $saveDir );
		$wp_filesystem->put_contents( $archivePath, $body );
		$result = null;
		if ( $wp_filesystem->is_file( $archivePath ) ) {
			$fw = Framework::getInstance( 'ink' );
			//#! Get the path to the wp-content directory
			$extractDir = $fw->getConfig( 'ink-content-dir' );

			//#! Detect the extract directory
			if ( self::$_options['product-type'] == self::TYPE_PLUGIN ) {
				$extractDir .= 'plugins/';
			}
			else {
				$extractDir .= 'themes/';
			}

			$result = unzip_file( $archivePath, $extractDir );
			if ( is_wp_error( $result ) ) {
				AdminNotice::add( '[Ink-Framework][Updater][Apply update] ' . esc_html__( 'Error extracting the archive:', 'ink-fw' ) . ' ' . $result->get_error_message(), AdminNotice::TYPE_ERROR );
				set_site_transient( self::UPDATE_CHECK_NAME, HOUR_IN_SECONDS * self::$_options['update-check-interval'] );
				delete_site_transient( self::UPDATE_PROCESS_NAME );
				delete_site_transient( self::UPDATES_OPTION_NAME );
			}
		}
		else {
			AdminNotice::add( '[Ink-Framework][Updater][Apply update] ' . esc_html__( 'Error downloading the archive.', 'ink-fw' ), AdminNotice::TYPE_ERROR );
			return;
		}

		//#! Cleanup
		$wp_filesystem->delete( $archivePath );
		$wp_filesystem->delete( $saveDir, true );

		//#! Update transients
		delete_site_transient( self::UPDATE_PROCESS_NAME );
		delete_site_transient( self::UPDATES_OPTION_NAME );
		set_site_transient( self::UPDATE_CHECK_NAME, HOUR_IN_SECONDS * self::$_options['update-check-interval'] );
		AdminNotice::add(
			sprintf(
				__( '<strong>%s</strong>: Update <strong>v%s</strong> successfully applied.', 'ink-fw' ),
				self::$_options['product-name'],
				$updateVersionAvailable
			),
			AdminNotice::TYPE_INFO
		);

		if ( ! $hasArgs && ! headers_sent() ) {
			wp_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Check the current request and display the maintenance mode message for those users that aren't allowed to access the backend
	 */
	public static function enableMaintenanceMode()
	{
		if ( ! current_user_can( 'manage_options' ) || ! is_user_logged_in() ) {
			$message = sprintf(
				__( '<h1 style="color:#840b0b">%s is under Maintenance</h1><br />We are performing a scheduled maintenance. We will be back online shortly!', 'ink-fw' ),
				get_bloginfo( 'name' )
			);
			wp_die( $message );
		}
	}


	/**
	 * Retrieve the updates from the endpoint
	 * @return array
	 */
	private static function __getUpdates()
	{
		//#! Check cache
		$data = get_site_transient( self::UPDATES_OPTION_NAME );
		if ( ! empty( $data ) ) {
			if ( ! is_scalar( $data ) && is_array( $data ) ) {
				return $data;
			}
		}

		//#! Get data from server
		$response = wp_remote_get( self::$_options['endpoint'] );
		if ( is_wp_error( $response ) ) {
			AdminNotice::add(
				sprintf( __( '[Ink-Framework][Updater][Get updates] Error: %s', 'ink-fw' ), $response->get_error_message() ),
				AdminNotice::TYPE_ERROR,
				false
			);
			return [];
		}
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			AdminNotice::add( __( '[Ink-Framework][Updater][Get updates] Error: Empty response from server.', 'ink-fw' ), AdminNotice::TYPE_ERROR, false );
			return [];
		}
		$updates = json_decode( $body, true );
		if ( is_scalar( $updates ) || ! is_array( $updates ) ) {
			return [];
		}

		//#! Cache
		set_site_transient( self::UPDATES_OPTION_NAME, $updates );
		return $updates;
	}

	/**
	 * Check to see whether or not we can ping the endpoint to check for new updates
	 * @return bool
	 */
	private static function __canCheck()
	{
		$inProcess = get_site_transient( self::UPDATE_PROCESS_NAME );

		//#! Check to see whether or not we are already in a process of updating
		if ( ! empty( $inProcess ) ) {
			return false;
		}

		//#! If we've already check for updates
		$transientData = get_site_transient( self::UPDATE_CHECK_NAME );
		return empty( $transientData );
	}

	/**
	 * Retrieve the entries we're interested in from the plugin's comment
	 * @return array Ex: ('Version' => 1.0.0, 'Patch' => 1.0.1)
	 */
	private static function __getProductInfo()
	{
		return get_file_data( self::$_options['product-file-path'], [ 'Version' => 'Version', 'Patch' => 'Patch' ] );
	}
}
