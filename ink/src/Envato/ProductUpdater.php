<?php

namespace Ink\Envato;

use Ink\Framework;
use Ink\Notices\AdminNotice;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}

/**
 * Class ProductUpdater
 * @package Ink\Envato
 * @see https://github.com/wp-kitten/envato-update-plugins
 * @see https://github.com/envato/envato-wordpress-toolkit
 *
 * This class extends the Envato's Envato_Protected_API class to allow buyers to update plugins (from Code Canyon), feature which is not available by default. This allows plugin and theme developers to provide an easier way for their users to update their products using the WordPress updates screen. This class is a revised version of https://github.com/wp-kitten/envato-update-plugins
 */
class ProductUpdater extends Envato_Protected_API
{
	const NONCE_ACTION = 'ink-product-updater-save-settings';
	const NONCE_NAME = 'ink-product-updater-security';
	/**
	 * The name of the option storing the security settings
	 * @var string
	 */
	const OPTION_NAME = 'ink-product-updater-options';


	/**
	 * Stores the name of the framework's instance
	 * @var string
	 */
	private static $_fwInstanceName = '';

	/**
	 * Holds the list of the class' default options
	 * @var array
	 */
	private static $_defaultOptions = [
		'user_name' => '',
		'api_key' => '',
	];


	//<editor-fold desc="ENVATO API">

	/**
	 * Class constructor. Sets error messages if any. Registers the 'pre_set_site_transient_update_plugins' filter.
	 *
	 * @param string $user_name The buyer's Username
	 * @param string $api_key The buyer's API Key can be accessed on the marketplaces via My Account -> My Settings -> API Key
	 * @param string $fwInstanceName The name of the framework instance
	 */
	public function __construct( $user_name = '', $api_key = '', $fwInstanceName = '' )
	{
		$options = self::__getOptions();
		$user_name = ( empty( $user_name ) ? $options['user_name'] : '' );
		$api_key = ( empty( $api_key ) ? $options['api_key'] : '' );

		//#! Prevent parent class from filling the error log with notices if any of the vars is empty
		if ( ! empty( $user_name ) && ! empty( $api_key ) ) {
			parent::__construct( $user_name, $api_key );
		}
		self::$_fwInstanceName = $fwInstanceName;
		if ( ! empty( self::$_fwInstanceName ) ) {
			add_action( 'admin_menu', [ get_class(), 'wpAdminMenu' ], 99 );
			add_action( 'admin_init', [ get_class(), 'saveSettings' ] );
		}
	}

	/**
	 * Set up the filter for plugins in order to include Envato plugins
	 */
	public function onAdminInit()
	{
		// Setup parent class with the correct credentials, if we have them
		if ( ! empty( $this->user_name ) && ! empty( $this->api_key ) ) {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'checkPluginUpdates' ), 100 );
		}
	}

	/**
	 * Update the plugins list to include the Envato plugins that require update. Triggered by the
	 * pre_set_site_transient_update_plugins filter.
	 *
	 * @param $plugins
	 * @return mixed
	 */
	public function checkPluginUpdates( $plugins )
	{
		if ( empty( $plugins ) || ! isset( $plugins->checked ) ) {
			return $plugins; // No plugins
		}
		$wpPlugins = $plugins->checked;
		if ( ! is_array( $wpPlugins ) || empty( $wpPlugins ) ) {
			return $plugins; // No plugins
		}
		// Get user's plugins list from Envato
		$envatoPlugins = $this->wp_list_plugins();
		if ( empty( $envatoPlugins ) ) {
			return $plugins; // No plugins from Envato Marketplace found
		}
		// Check for errors
		$errors = $this->api_errors();
		if ( ! empty( $errors ) ) {
			AdminNotice::add( sprintf( __( '[Ink][Envato][ProductUpdater] Error: %s', 'ink-fw' ), var_export( $errors, 1 ) ), AdminNotice::TYPE_WARNING, false );
			return $plugins;
		}

		$fw = Framework::getInstance( 'ink' );
		$pluginsDir = $fw->getConfig( 'ink-content-dir' ) . 'plugins/';


		$plugins = (array)$plugins;
		// Loop over the plugins and see which needs update
		foreach ( $wpPlugins as $path => $version ) {
			$pluginData = get_plugin_data( $pluginsDir . $path );
			$wpPluginName = isset( $pluginData['Name'] ) ? $pluginData['Name'] : '';
			$wpPluginVersion = isset( $pluginData['Version'] ) ? $pluginData['Version'] : null;
			if ( empty( $wpPluginName ) || is_null( $wpPluginVersion ) ) {
				continue;
			}
			// Check plugin in Envato plugins
			foreach ( $envatoPlugins as $i => $pluginObj ) {
				// We have a match
				if ( isset( $pluginObj->plugin_name ) && $pluginObj->plugin_name == $wpPluginName ) {
					// Check plugin to see if it needs to be updated
					$v = isset( $pluginObj->version ) ? $pluginObj->version : null;
					if ( ! is_null( $v ) ) {
						// Needs update - prepare entry
						if ( version_compare( $v, $wpPluginVersion, '>' ) ) {
							// Get the update zip file
							$update_zip = $this->wp_download( $pluginObj->item_id );
							if ( ! $update_zip || empty( $update_zip ) ) {
								// Error ?
								$errors = $this->api_errors();
								if ( ! empty( $errors ) ) {
									AdminNotice::add( sprintf( __( '[Ink][Envato][ProductUpdater] Error: %s', 'ink-fw' ), var_export( $errors, 1 ) ), AdminNotice::TYPE_WARNING, false );
								}
								break; // No need to go any further
							}
							if ( ! isset( $pluginData['PluginURI'] ) || empty( $pluginData['PluginURI'] ) ) {
								continue;
							}
							// Add plugin to WordPress' list
							$plugins['response'][$path] = (object)array(
								'id' => $pluginObj->item_id,
								'slug' => str_replace( ' ', '-', trim( $pluginObj->plugin_name ) ),
								'plugin' => $path,
								'new_version' => $v,
								'upgrade_notice' => null,
								'url' => $pluginData['PluginURI'],
								'package' => $update_zip
							);
						}
					}
				}
			}
		}
		return (object)$plugins;
	}

	/**
	 * Retrieve user's list of plugins from Envato
	 *
	 * @param bool $allow_cache Whether or not to allow caching of the result
	 * @param int $cacheTimeout The number of seconds the transient will be stored in the database
	 * @return array
	 */
	protected function wp_list_plugins( $allow_cache = true, $cacheTimeout = 300 )
	{
		return $this->private_user_data(
			'wp-list-plugins',
			$this->user_name,
			'',
			$allow_cache,
			$cacheTimeout
		);
	}
	//</editor-fold desc="ENVATO API">

	//<editor-fold desc="WORDPRESS API">

	/**
	 * Retrieve the user's credentials so we cna retrieve data on his behalf
	 * @return array
	 */
	public static function getUserCredentials()
	{
		return self::__getOptions();
	}

	/**
	 * Triggered when the plugin is deactivated
	 */
	public function onDeactivate()
	{
		// Remove plugins filter
		if ( has_filter( 'pre_set_site_transient_update_plugins', array( $this, 'checkPluginUpdates' ) ) ) {
			remove_filter( 'pre_set_site_transient_update_plugins', array( $this, 'checkPluginUpdates' ) );
		}
	}

	/**
	 * Add the menu page to the framework's main menu
	 */
	final public static function wpAdminMenu()
	{
		if ( ! empty( self::$_fwInstanceName ) ) {
			$title = apply_filters( 'ink-framework/product-updater/menu-title', esc_html__( 'Ink Product Updater', 'ink-fw' ) );
			add_submenu_page( 'options-general.php', $title, $title, 'manage_options', 'ink_framework_product_updater', function () {
				$options = self::__getOptions();
				?>
				<div class="wrap ink-wrap">
					<header>
						<h1><?php echo apply_filters('ink-framework/product-updater/page-title', esc_html__( 'Ink Framework - Envato Product Updater', 'ink-fw' ) ); ?></h1>
					</header>

					<section class="ink-wrap-section">
						<p><?php esc_html_e( "Provide your Envato user name and api key to be able to update your purchases from Envato Marketplace right from your WordPress updates page.", 'ink-fw' ); ?></p>
						<p><?php esc_html_e( "You can generate an API Key on your account settings page on Envato Marketplace.", 'ink-fw' ); ?></p>
					</section>

					<section class="ink-wrap-section">
						<form method="post">
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row">
											<label for="user_name"><?php esc_html_e( 'Envato user name', 'ink-fw' ); ?></label>
										</th>
										<td>
											<input type="text" id="user_name" name="user_name" class="regular-text" value="<?php echo sanitize_text_field( $options['user_name'] ); ?>"/>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="api_key"><?php esc_html_e( 'Envato api key', 'ink-fw' ); ?></label>
										</th>
										<td>
											<input type="text" id="api_key" name="api_key" class="regular-text" value="<?php echo sanitize_text_field( $options['api_key'] ); ?>"/>
										</td>
									</tr>

								</tbody>
							</table>
							<p class="submit">
								<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save settings', 'ink-fw' ); ?>">
							</p>
							<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
						</form>
					</section>
				</div>
				<?php
			} );
		}
	}

	/**
	 * Validate & save settings
	 */
	final public static function saveSettings()
	{
		if ( 'POST' == strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			if ( isset( $_POST[self::NONCE_NAME] ) && wp_verify_nonce( $_POST[self::NONCE_NAME], self::NONCE_ACTION ) ) {
				$options = self::__getOptions();
				$options['user_name'] = ( isset( $_POST['user_name'] ) ? sanitize_text_field( $_POST['user_name'] ) : '' );
				$options['api_key'] = ( isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '' );
				self::__saveOptions( $options );
				AdminNotice::add( esc_html__( 'Settings saved.', 'ink-fw' ), AdminNotice::TYPE_SUCCESS, false );
			}
		}
	}

	/**
	 * Retrieve the security options
	 * @return array
	 */
	private static function __getOptions()
	{
		$options = get_option( self::OPTION_NAME, [] );
		return array_merge( self::$_defaultOptions, $options );
	}

	/**
	 * Save options
	 * @param array $options
	 */
	private static function __saveOptions( $options )
	{
		update_option( self::OPTION_NAME, $options );
	}

	//</editor-fold desc="WORDPRESS API">
}
