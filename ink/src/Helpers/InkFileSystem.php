<?php

namespace Ink\Helpers;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}

require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );

/**
 * Class InkFileSystem
 * @package Ink\Helpers
 *
 * Helper class that extends the WP_Filesystem_Direct's put_contents method in order to append to the file instead of always overriding it. Primarily used by Logger
 */
class InkFileSystem extends \WP_Filesystem_Direct
{
	/**
	 * InkFileSystem constructor.
	 * @param array $arg Not used
	 */
	public function __construct( $arg = [] )
	{
		parent::__construct( $arg );
	}

	/**
	 * Extends the base class' put_contents method in order to be able to append instead of always overriding it.
	 *
	 * @param string $file
	 * @param string $contents
	 * @param bool $mode
	 * @see http://php.net/manual/ro/function.fopen.php
	 * @see wp-admin/includes/class-wp-filesystem-direct.php
	 * @return bool
	 */
	public function append( $file, $contents, $mode = false )
	{
		$fp = @fopen( $file, 'a+' );
		if ( ! $fp ) {
			return false;
		}

		mbstring_binary_safe_encoding();

		$data_length = strlen( $contents );
		$bytes_written = fwrite( $fp, $contents );

		reset_mbstring_encoding();

		fclose( $fp );

		if ( $data_length !== $bytes_written ) {
			return false;
		}

		$this->chmod( $file, $mode );
		return true;
	}
}
