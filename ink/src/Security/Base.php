<?php

namespace Ink\Security;

use Ink\Framework;
use Ink\Helpers;
use Ink\Notices\AdminNotice;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}

/**
 * Class Base
 * @package Ink\Helpers
 *
 * Helper class proving utility methods to increase your website's security
 */
class Base
{
	const NONCE_ACTION = 'ink-security-save-settings';
	const NONCE_NAME = 'ink-security';

	/**
	 * Internal var used for directory permissions check
	 */
	const __NA = 'N/A';

	/**
	 * The name of the option storing the security settings
	 * @var string
	 */
	const OPTION_NAME = 'ink-security-options';

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
		'remove_version_from_url' => false,
		'remove_wp_meta_tags' => false,
		'remove_wlw_meta_tag' => false,
		'remove_wp_shortlink' => false,
		'remove_wp_rss_feeds' => false,
		'remove_wp_comments_feeds' => false,
		'remove_wp_prev_next_links' => false,
		'remove_xfn_tags' => false,
		'add_no_index_tag' => false,

		'disable_error_reporting' => false,
		'disable_core_update_notices' => false,
		'check_wp_dir_permissions' => false,
		'empty_readme_root' => false,
		'hide_wp_footer_version' => false,
	];

	/**
	 * Holds the partial name of the transient that stores the directory listing's output. Cache refreshes every 8h.
	 * @var string
	 */
	private static $_dirPermsCacheOptName = '_ink_framework_security_cache';

	/**
	 * Initialize the class
	 *
	 * @param string $fwInstanceName The name of the framework instance
	 */
	public static function init( $fwInstanceName )
	{
		$self = get_class();
		self::$_fwInstanceName = $fwInstanceName;
		add_action( 'wp', [ $self, 'applySecuritySettings' ], 99 );
		add_action( 'admin_menu', [ $self, 'wpAdminMenu' ], 99 );
		add_action( 'admin_init', [ $self, 'saveSettings' ] );

		$options = self::__getOptions();
		$checkDirPermissions = ( isset( $options['check_wp_dir_permissions'] ) && $options['check_wp_dir_permissions'] );
		if ( $checkDirPermissions ) {
			add_action( 'admin_init', [ $self, 'scanDirPermissions' ] );
			add_action( 'wp_dashboard_setup', function () {
				$widgetTitle = apply_filters( 'ink-framework/security/dash-widget/title', esc_html__( '[Ink Framework] Security Status', 'ink-fw' ) );
				wp_add_dashboard_widget( 'ink_fw_security_dash_widget', $widgetTitle, [ get_class(), 'renderDashboardWidget' ] );
			} );
		}

		if ( isset( $options['empty_readme_root'] ) && $options['empty_readme_root'] ) {
			self::__emptyReadmeRoot();
		}
		if ( isset( $options['hide_wp_footer_version'] ) && $options['hide_wp_footer_version'] ) {
			add_filter( 'update_footer', [ $self, '__hideWpFooterVersion' ], 900 );
		}
	}


	/**
	 * Scan known directories for permissions. Caches data for 8h
	 * @hooked to "admin_init" if the "check_wp_dir_permissions" option is enabled
	 */
	public static function scanDirPermissions()
	{
		//#! Check if cache exists
		$transName = self::getInstanceName() . self::$_dirPermsCacheOptName;
		$transData = get_transient( $transName );
		if ( ! empty( $transData ) ) {
			return;
		}

		//#! Check root directory
		$rootDir = realpath( wp_normalize_path( ABSPATH ) );
		$wpAdmin = trailingslashit( $rootDir ) . 'wp-admin';
		$wpIncludes = trailingslashit( $rootDir ) . 'wp-includes';
		$wpContent = Framework::getInstance( self::getInstanceName() )->getConfig( 'ink-content-dir' );
		$wpContentDirs = glob( $wpContent . '*', GLOB_ONLYDIR );


		$data = [];

		$permissions = self::__getFilePermissions( $rootDir );
		if ( $permissions != self::__NA ) {
			$data[$rootDir] = $permissions;
		}
		$permissions = self::__getFilePermissions( $wpAdmin );
		if ( $permissions != self::__NA ) {
			$data[$wpAdmin] = $permissions;
		}
		$permissions = self::__getFilePermissions( $wpIncludes );
		if ( $permissions != self::__NA ) {
			$data[$wpIncludes] = $permissions;
		}
		$permissions = self::__getFilePermissions( $wpContent );
		if ( $permissions != self::__NA ) {
			$data[$wpContent] = $permissions;
		}

		if ( ! empty( $wpContentDirs ) ) {
			foreach ( $wpContentDirs as $path ) {
				$permissions = self::__getFilePermissions( $path );
				if ( $permissions != self::__NA ) {
					$data[$path] = $permissions;
				}
			}
		}
		if ( ! empty( $data ) ) {
			set_transient( $transName, $data, 8 * 60 * 60 );
		}
	}

	/**
	 * Render the content of the dashboard widget
	 */
	public static function renderDashboardWidget()
	{
		$transName = self::getInstanceName() . self::$_dirPermsCacheOptName;
		$transData = get_transient( $transName );
		echo '<div class="wrap">';
		if ( ! empty( $transData ) ) {
			echo '<div class="ink-dash-wrap-content"><p>' . esc_html__( 'There is no data to display yet.', 'ink-fw' ) . '</p></div>';
		}
		else {
			//#! Make sure the data is array and we're not looping over an invalid value, like a string
			if ( ! is_scalar( $transData ) && is_array( $transData ) ) {
				?>
				<table class="wp-list-table widefat striped posts">
					<thead>
						<th scope="col" class="manage-column column-primary">
							<strong><?php esc_html_e( 'File path', 'ink-fw' ); ?></strong></th>
						<th scope="col" class="manage-column column-author">
							<strong><?php esc_html_e( 'Permissions', 'ink-fw' ); ?></strong></th>
						<th scope="col" class="manage-column column-author">
							<strong><?php esc_html_e( 'Recommended', 'ink-fw' ); ?></strong></th>
					</thead>
					<tbody>
						<?php
						foreach ( $transData as $path => $permissions ) {
							echo '<tr class="">';
							echo '<td class="column-title column-primary">' . esc_html( $path ) . '</td>';
							echo '<td class="author column-author">' . esc_html( $permissions ) . '</td>';
							echo '<td class="author column-author">0775</td>';
							echo '</tr>';
						}
						?>
					</tbody>
				</table>
				<?php
			}
		}
		echo '</div>';
	}

	/**
	 * Apply security settings based on the selected options
	 */
	public static function applySecuritySettings()
	{
		$options = self::__getOptions();

		if ( $options['remove_version_from_url'] ) {
			self::removeVersionFromLinks();
		}

		if ( $options['remove_wp_meta_tags'] ) {
			self::removeWpMetaTags();
		}

		if ( $options['disable_error_reporting'] ) {
			self::disableErrorReporting();
		}

		if ( $options['disable_core_update_notices'] ) {
			self::disableCoreNotices();
		}

		if ( $options['remove_wlw_meta_tag'] ) {
			self::removeWlwLink();
		}

		if ( $options['remove_wp_shortlink'] ) {
			self::removeShortlink();
		}

		if ( $options['remove_wp_rss_feeds'] ) {
			self::removeRssFeeds();
		}

		if ( $options['remove_wp_comments_feeds'] ) {
			self::removeCommentsFeed();
		}

		if ( $options['remove_wp_prev_next_links'] ) {
			self::removePrevNextLinks();
		}

		if ( $options['add_no_index_tag'] ) {
			self::addNoIndex();
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
				$options['remove_version_from_url'] = isset( $_POST['remove_version_from_url'] );
				$options['remove_wp_meta_tags'] = isset( $_POST['remove_wp_meta_tags'] );
				$options['remove_wlw_meta_tag'] = isset( $_POST['remove_wlw_meta_tag'] );
				$options['remove_wp_shortlink'] = isset( $_POST['remove_wp_shortlink'] );
				$options['remove_wp_rss_feeds'] = isset( $_POST['remove_wp_rss_feeds'] );
				$options['remove_wp_comments_feeds'] = isset( $_POST['remove_wp_comments_feeds'] );
				$options['remove_wp_prev_next_links'] = isset( $_POST['remove_wp_prev_next_links'] );
				$options['add_no_index_tag'] = isset( $_POST['add_no_index_tag'] );

				$options['disable_error_reporting'] = isset( $_POST['disable_error_reporting'] );
				$options['disable_core_update_notices'] = isset( $_POST['disable_core_update_notices'] );
				$options['check_wp_dir_permissions'] = isset( $_POST['check_wp_dir_permissions'] );
				$options['empty_readme_root'] = isset( $_POST['empty_readme_root'] );
				$options['hide_wp_footer_version'] = isset( $_POST['hide_wp_footer_version'] );
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
		$options = get_option( self::OPTION_NAME );
		if ( empty( $options ) || is_scalar( $options ) || ! is_array( $options ) ) {
			$options = [];
		}
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

	/**
	 * Add the menu page to the framework's main menu
	 */
	final public static function wpAdminMenu()
	{
		if ( ! empty( self::$_fwInstanceName ) ) {
			$title = apply_filters( 'ink-framework/security/menu-title', esc_html__( 'Ink Security', 'ink-fw' ) );
			add_submenu_page( 'options-general.php', $title, $title, 'manage_options', 'ink_framework_security', function () {
				$options = self::__getOptions();
				?>
				<div class="wrap ink-wrap">
					<header>
						<h1><?php echo apply_filters( 'ink-framework/security/page-title', esc_html__( 'Ink Framework - Security', 'ink-fw' ) ); ?></h1>
					</header>

					<section class="ink-wrap-section">
						<p><?php esc_html_e( "Enhance your website's security by enabling the following options.", 'ink-fw' ); ?></p>
						<p class="description"><?php esc_html_e( 'The settings apply to all users but administrators.', 'ink-fw' ); ?></p>
					</section>

					<section class="ink-wrap-section">
						<form method="post">
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row">
											<label><?php esc_html_e( 'Security settings', 'ink-fw' ); ?></label>
										</th>
										<td>
											<label><input type="checkbox" name="remove_version_from_url" value="1" <?php checked( $options['remove_version_from_url'], '1' ); ?>>
												<span><?php esc_html_e( 'Remove versions from links', 'ink-fw' ); ?></span></label>
											<br/>
											<label><input type="checkbox" name="remove_wp_meta_tags" value="1" <?php checked( $options['remove_wp_meta_tags'], '1' ); ?>>
												<span><?php esc_html_e( 'Remove wp meta tags', 'ink-fw' ); ?></span></label>
											<br/>
											<label><input type="checkbox" name="remove_wlw_meta_tag" value="1" <?php checked( $options['remove_wlw_meta_tag'], '1' ); ?>>
												<span><?php esc_html_e( 'Remove Windows Live Writer meta tag', 'ink-fw' ); ?></span></label>
											<br/>
											<span class="description"><?php _e(
													sprintf(
														__( 'See what <a href="%s" target="_blank">Windows Live Writer</a> is.', 'ink-fw' ),
														'https://en.wordpress.com/windows-live-writer/'
													) ); ?></span>
											<br/>
											<label><input type="checkbox" name="remove_wp_shortlink" value="1" <?php checked( $options['remove_wp_shortlink'], '1' ); ?>>
												<span><?php esc_html_e( 'Remove shortlink from header', 'ink-fw' ); ?></span></label>
											<br/>
											<label><input type="checkbox" name="remove_wp_rss_feeds" value="1" <?php checked( $options['remove_wp_rss_feeds'], '1' ); ?>>
												<span><?php esc_html_e( 'Remove RSS feeds', 'ink-fw' ); ?></span></label>
											<br/>
											<label><input type="checkbox" name="remove_wp_comments_feeds" value="1" <?php checked( $options['remove_wp_comments_feeds'], '1' ); ?>>
												<span><?php esc_html_e( 'Remove comments feed', 'ink-fw' ); ?></span></label>
											<br/>
											<label><input type="checkbox" name="remove_wp_prev_next_links" value="1" <?php checked( $options['remove_wp_prev_next_links'], '1' ); ?>>
												<span><?php esc_html_e( 'Remove Previous and Next links', 'ink-fw' ); ?></span></label>
											<br/>
											<label><input type="checkbox" name="add_no_index_tag" value="1" <?php checked( $options['add_no_index_tag'], '1' ); ?>>
												<span><?php esc_html_e( 'Add no-index meta tag to Low Value Pages (archives, search, 404, etc)', 'ink-fw' ); ?></span></label>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Core options', 'ink-fw' ); ?></th>
										<td>
											<label><input type="checkbox" name="disable_error_reporting" value="1" <?php checked( $options['disable_error_reporting'], '1' ); ?>>
												<span><?php esc_html_e( 'Disable error reporting (db errors included)', 'ink-fw' ); ?></span></label>
											<br/>
											<label><input type="checkbox" name="disable_core_update_notices" value="1" <?php checked( $options['disable_core_update_notices'], '1' ); ?>>
												<span><?php esc_html_e( 'Disable core update notices', 'ink-fw' ); ?></span></label>
											<br/>
											<label><input type="checkbox" name="check_wp_dir_permissions" value="1" <?php checked( $options['check_wp_dir_permissions'], '1' ); ?>>
												<span><?php esc_html_e( 'Check WordPress default directories for permissions', 'ink-fw' ); ?></span></label>

											<br/>
											<label><input type="checkbox" name="empty_readme_root" value="1" <?php checked( $options['empty_readme_root'], '1' ); ?>>
												<span><?php esc_html_e( 'Empty readme.html from root', 'ink-fw' ); ?></span></label>
											<br>
											<small class="description"><?php esc_html_e( 'This file can provide hackers the version of your WordPress installation, therefore it is important to either delete this file or make it inaccessible for your visitors.', 'ink-fw' ); ?></small>

											<br/>
											<label><input type="checkbox" name="hide_wp_footer_version" value="1" <?php checked( $options['hide_wp_footer_version'], '1' ); ?>>
												<span><?php esc_html_e( 'Hide WP version from footer', 'ink-fw' ); ?></span></label>
											<br>
											<small class="description"><?php esc_html_e( 'Hiding the WordPress version in the admin footer from everyone is a good security policy because if a hacker knows which version of WordPress a website is running, it can make it easier for him to target a known WordPress security issue..', 'ink-fw' ); ?></small>

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
	 * Retrieve the name of the framework's instance
	 * @return string
	 */
	final public static function getInstanceName()
	{
		return self::$_fwInstanceName;
	}

	/**
	 * Remove the version parameter from urls for everyone but administrators
	 */
	final public static function removeVersionFromLinks()
	{
		if ( ! Helpers\Util::isAdministrator() ) {
			add_filter( 'script_loader_src', [ get_class(), 'removeWpVersionFromLinks' ], 99, 1 );
			add_filter( 'style_loader_src', [ get_class(), 'removeWpVersionFromLinks' ], 99, 1 );
		}
	}

	/**
	 * @internal
	 *
	 * Removes the query string parameter from the given link
	 * @param string $src The source link to alter
	 * @return string
	 */
	final public static function removeWpVersionFromLinks( $src )
	{
		if ( ! empty( $src ) ) {
			$parts = explode( '?', $src );
			$src = ( isset( $parts[0] ) ? $parts[0] : $src );
		}
		return $src;
	}

	/**
	 * @internal
	 *
	 * Remove WordPress' meta tags information from the website's header
	 */
	final public static function removeWpMetaTags()
	{
		if ( Helpers\Util::isAdministrator() ) {
			return;
		}
		foreach ( [ 'html', 'xhtml', 'atom', 'rss2', 'rdf', 'comment' ] as $type ) {
			add_filter( "get_the_generator_" . $type, [ get_class(), '_filter_generator' ], 90, 2 );
		}
		// eliminate version for wordpress >= 2.4
		remove_filter( 'wp_head', 'wp_generator' );
		$actions = [ 'rss2_head', 'commentsrss2_head', 'rss_head', 'rdf_header', 'atom_head', 'comments_atom_head', 'opml_head', 'app_head' ];
		foreach ( $actions as $action ) {
			remove_action( $action, 'the_generator' );
		}
	}

	/**
	 * @internal
	 *
	 * Remove wp meta generators
	 *
	 * @param string $gen
	 * @param string $type
	 * @return string
	 */
	final public static function _filter_generator( $gen, $type )
	{
		switch ( $type ) {
			case 'html':
				$gen = '<meta name="generator" content="WordPress">';
				break;
			case 'xhtml':
				$gen = '<meta name="generator" content="WordPress" />';
				break;
			case 'atom':
				$gen = '<generator uri="http://wordpress.org/">WordPress</generator>';
				break;
			case 'rss2':
				$gen = '<generator>http://wordpress.org/?v=</generator>';
				break;
			case 'rdf':
				$gen = '<admin:generatorAgent rdf:resource="http://wordpress.org/?v=" />';
				break;
			case 'comment':
				$gen = '<!-- generator="WordPress" -->';
				break;
		}
		return $gen;
	}

	/**
	 * @internal
	 *
	 * Turn off error reporting (database and PHP)
	 */
	final public static function disableErrorReporting()
	{
		if ( Helpers\Util::isAdministrator() ) {
			return;
		}
		@error_reporting( 0 );
		@ini_set( 'display_errors', 'Off' );
		@ini_set( 'display_startup_errors', 0 );
		global $wpdb;
		$wpdb->hide_errors();
		$wpdb->suppress_errors();

		//#! Hook to this action to add more
		do_action( 'ink-framework/security/disable-error-reporting' );
	}

	/**
	 * @internal
	 *
	 * Disable core update notifications from back-end for everyone but administrators
	 */
	final public static function disableCoreNotices()
	{
		if ( Helpers\Util::isAdministrator() ) {
			return;
		}
		add_action( 'admin_init', function () {
			remove_action( 'admin_notices', 'maintenance_nag' );
		} );
		add_action( 'admin_init', function () {
			remove_action( 'admin_notices', 'update_nag', 3 );
		} );
		add_action( 'admin_init', function () {
			remove_action( 'admin_init', '_maybe_update_core' );
		} );
		add_action( 'init', function () {
			remove_action( 'init', 'wp_version_check' );
		} );
		add_filter( 'pre_option_update_core', '__return_null' );
		remove_action( 'wp_version_check', 'wp_version_check' );
		remove_action( 'admin_init', '_maybe_update_core' );
		add_filter( 'pre_transient_update_core', '__return_null' );
		add_filter( 'pre_site_transient_update_core', '__return_null' );

		//#! Hook to this action to add more
		do_action( 'ink-framework/security/disable-core-notices' );
	}

	final public static function removeWlwLink()
	{
		if ( ! Helpers\Util::isAdministrator() ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}
	}

	final public static function removeShortlink()
	{
		if ( ! Helpers\Util::isAdministrator() ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		}
	}

	final public static function removeRssFeeds()
	{
		if ( ! Helpers\Util::isAdministrator() ) {
			remove_action( 'wp_head', 'feed_links', 2 );
		}
	}

	final public static function removeCommentsFeed()
	{
		if ( ! Helpers\Util::isAdministrator() ) {
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}

	final public static function removePrevNextLinks()
	{
		if ( ! is_single() && ! Helpers\Util::isAdministrator() ) {
			remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
		}
	}

	final public static function addNoIndex()
	{
		add_action( 'wp_head', function () {
			$paged = intval( get_query_var( 'paged' ) );
			$metaTag = '<meta name="robots" content="noindex,follow"/>';
			if ( ( is_archive() || is_search() || is_404() ) && ! Helpers\Util::isAdministrator() ) {
				echo $metaTag;
			}
			elseif ( ( is_home() || is_front_page() ) && $paged >= 2 ) {
				echo $metaTag;
			}
		}, 80 );
	}

	final public static function __hideWpFooterVersion( $v )
	{
		if ( Helpers\Util::isAdministrator() ) {
			return $v;
		}
		return ' ';
	}

	/**
	 * Retrieve he permissions for the specified file/directory. This function requires the "fileperms" functions to be callable.
	 * @param string $filePath
	 * @return string
	 */
	private static function __getFilePermissions( $filePath )
	{
		if ( ! function_exists( 'fileperms' ) ) {
			return self::__NA;
		}
		if ( ! file_exists( $filePath ) ) {
			return self::__NA;
		}
		clearstatcache();
		return substr( sprintf( "%o", fileperms( $filePath ) ), -4 );
	}

	/**
	 * Remove the content of the ABSPATH/readme.html file
	 */
	private static function __emptyReadmeRoot()
	{
		$fs = Helpers\Util::getFileSystem();
		if ( $fs ) {
			$filePath = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'readme.html';
			if ( $fs->is_file( $filePath ) ) {
				if ( $fs->is_writable( $filePath ) ) {
					$fs->put_contents( $filePath, '', 0644 );
				}
			}
		}
	}

}
