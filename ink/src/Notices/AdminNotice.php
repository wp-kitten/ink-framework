<?php

namespace Ink\Notices;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}


/**
 * Class AdminNotice
 * @package Ink\Notices
 *
 * Utility class to setup [persistent] admin or individual user notices
 */
class AdminNotice extends Notice
{
	const TS_ADMIN = 'ink_admin_notices';
	const NONCE_ACTION = 'ink_delete_notices_admin';
	const NONCE_NAME = 'ink_notices_admin_nonce';

	/**
	 * Stores the notices to display if not persistent
	 * @var array
	 */
	private static $_notices = [];


	/**
	 * Render non persistent notices
	 */
	public static function renderNoticesNonPersistent()
	{
		$notices = get_transient( 'ink_framework_np_admin_notices' );
		if ( ! empty( $notices ) ) {
			foreach ( $notices as $hash => $notice ) {
				$text = $notice['text'];
				$type = $notice['type'];
				echo '<div class="ink-notice notice notice-' . esc_attr( $type ) . '">';
				echo '<p>' . $text . '</p>';
				echo '</div>';
			}
		}
		delete_transient( 'ink_framework_np_admin_notices' );
	}

	/**
	 * Render notices
	 * @hooked to 'admin_notices'
	 * @see \Ink\Notices\UserNotice::__construct()
	 */
	public static function renderNotices()
	{
		$notices = self::getNotices();
		if ( ! empty( $notices ) ) {
			foreach ( $notices as $hash => $notice ) {
				$text = $notice['text'];
				$type = $notice['type'];

				echo '<div class="ink-notice notice notice-' . esc_attr( $type ) . ' ink-is-dismissible">';

				echo '<p>' . $text;
				echo '<a href="#" class="ink-notice-dismiss js-ink-notice-dismiss" data-hash="' . $hash . '" data-type="admin">';
				echo '<span class="screen-reader-text">' . esc_html__( 'Dismiss', 'ink-fw' ) . '</span>';
				echo '</a>';
				echo '</p>';

				echo '</div>';
			}
		}
	}

	/**
	 * Inspect the url and see if this is a valid request to delete notices
	 * @hooked to 'admin_init'
	 */
	public static function checkDeleteRequest()
	{
		if ( isset( $_REQUEST[self::NONCE_NAME] ) && wp_verify_nonce( $_REQUEST[self::NONCE_NAME], self::NONCE_ACTION ) ) {
			if ( isset( $_REQUEST['ink_notice'] ) && ! empty( $_REQUEST['ink_notice'] ) ) {
				if ( isset( $_REQUEST['type'] ) && ( 'admin' == $_REQUEST['type'] ) ) {
					self::__deleteNotice( $_REQUEST['ink_notice'] );
					wp_send_json_success();
				}
			}
		}
	}

	/**
	 * Add a notice
	 * @param string $text
	 * @param string $type
	 * @param bool $persistent
	 */
	public static function add( $text, $type = self::TYPE_ERROR, $persistent = true )
	{
		self::__save( $text, $type, $persistent );
	}

	/**
	 * Retrieve all notices for the current type
	 * @return array
	 */
	public static function getNotices()
	{
		$data = get_transient( self::TS_ADMIN );

		//#! Ensure valid result type
		if ( empty( $data ) || ( is_scalar( $data ) && ! is_array( $data ) ) ) {
			$data = [];
		}
		return $data;
	}

	/**
	 * Save the notice
	 * @param string $text
	 * @param string $type The type of the notice (success, info, warning, etc)
	 * @param bool|false $persistent
	 */
	private static function __save( $text, $type, $persistent = true )
	{
		if ( $persistent ) {
			$notices = self::getNotices();
			$hash = md5( $text );
			//#! Override existent to avoid duplicates
			$notices[$hash] = [
				'text' => $text,
				'type' => $type
			];
			set_transient( self::TS_ADMIN, $notices );
		}
		else {
			self::$_notices[] = [
				'text' => $text,
				'type' => $type
			];
			set_transient( 'ink_framework_np_admin_notices', self::$_notices );
		}
	}

	/**
	 * Remove the specified notice
	 * @param string $hash The hash of the notice to delete
	 */
	private static function __deleteNotice( $hash )
	{
		if ( empty( $hash ) ) {
			return;
		}

		$notices = self::getNotices();
		if ( empty( $notices ) ) {
			return;
		}

		$n = [];
		$update = false;
		foreach ( $notices as $_hash => $entry ) {
			if ( $_hash == $hash ) {
				$update = true;
				continue;
			}
			$n[$_hash] = $entry;
		}
		if ( $update ) {
			set_transient( self::TS_ADMIN, $n );
		}
	}
}
