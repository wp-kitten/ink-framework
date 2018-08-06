<?php

namespace Ink\Helpers;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}


/**
 * Class Util
 * @package Ink\Helpers
 *
 * Helper class providing various utility methods.
 */
class Util
{
	/**
	 * Check to see whether or not the current user is the site/network administrator
	 * @param int $userID
	 * @return bool
	 */
	public static function isAdministrator( $userID = 0 )
	{
		if ( ! \is_user_logged_in() ) {
			return false;
		}
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			require_once( ABSPATH . 'wp-includes/pluggable.php' );
		}
		if ( ! empty( $userID ) ) {
			if ( ! function_exists( 'user_can' ) ) {
				require_once( ABSPATH . 'wp-includes/capabilities.php' );
			}
			return ( \user_can( $userID, 'delete_others_posts' ) || \user_can( $userID, 'delete_others_posts' ) );
		}
		return ( \current_user_can( 'manage_network' ) || \current_user_can( 'delete_others_posts' ) );
	}

	/**
	 * Retrieve the reference to the instance of the WP file system
	 * @return \Ink\Helpers\InkFileSystem
	 */
	public static function getFileSystem()
	{
		//#! Set the permission constants if not already set.
		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
		}
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
		}

		//#! Setup a new instance of WP_Filesystem_Direct and use it
		global $wp_filesystem;
		if ( ! ( $wp_filesystem instanceof \WP_Filesystem_Base ) ) {
			if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
				require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
			}
			$wp_filesystem = new \WP_Filesystem_Direct( [] );
		}
		return $wp_filesystem;
	}

	/**
	 * Create a random number
	 * @return double
	 */
	public static function makeSeed()
	{
		list( $usec, $sec ) = explode( ' ', microtime() );
		return (float)$sec + ( (float)$usec * 100000 );
	}

}
