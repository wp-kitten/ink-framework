<?php

namespace Ink\Helpers;

use Ink\Notices\UserNotice;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}

/**
 * Class Downloader
 * @package Ink\Helpers
 *
 * Utility class to download files
 */
class Downloader
{
	/**
	 * Retrieve the path to the downloaded file
	 * @param string $filePath
	 * @param string $saveFileName Optional, the file name plus extension to save the file as
	 * @param array $wpRequestArgs The list of arguments to override the default WP request settings
	 * @see \WP_Http::request()
	 * @return string The path to the downloaded file
	 */
	public static function get( $filePath, $saveFileName = '', $wpRequestArgs = [] )
	{
		$uploadsDir = wp_upload_dir();
		$saveDir = trailingslashit( wp_normalize_path( $uploadsDir['basedir'] ) ) . 'ink-fw';

		wp_mkdir_p( $saveDir );

		$saveDirUri = trailingslashit( $uploadsDir['baseurl'] ) . 'ink-fw';
		return self::__downloadFile( $filePath, $saveDir, $saveDirUri, $saveFileName, $wpRequestArgs );
	}

	/**
	 * Download a file to wp-content/uploads/ink-framework directory
	 * @param $filePath
	 * @param $saveDir
	 * @param $saveDirUri
	 * @param string $saveFileName Optional, the file name plus extension to save the file as
	 * @param array $wpRequestArgs
	 * @return array|bool Array on success
	 */
	private static function __downloadFile( $filePath, $saveDir, $saveDirUri, $saveFileName, $wpRequestArgs = [] )
	{
		//#! Retrieve from path
		if ( empty( $saveFileName ) ) {
			$fileInfo = new \SplFileInfo( $filePath );
			$saveFileName = $fileInfo->getFilename();
		}

		$saveFilePath = untrailingslashit( $saveDir ) . '/' . $saveFileName;
		$request = wp_remote_get( $filePath, $wpRequestArgs );
		if ( is_wp_error( $request ) ) {
			$m = sprintf(
				esc_html__( '%s %s', 'ink-fw' ),
				'[Ink Framework][Downloader][Get]',
				$request->get_error_message()
			);
			UserNotice::add( $m, UserNotice::TYPE_WARNING );
			return false;
		}
		$body = wp_remote_retrieve_body( $request );
		if ( empty( $body ) ) {
			$m = sprintf(
				esc_html__( '%s The file is either not accessible or empty.', 'ink-fw' ),
				'[Ink Framework][Downloader][Get]'
			);
			UserNotice::add( $m, UserNotice::TYPE_WARNING );
			return false;
		}
		$fs = Util::getFileSystem();
		$fs->put_contents( $saveFilePath, $body );
		if ( $fs->is_file( $saveFilePath ) ) {
			return [
				'path' => $saveFilePath,
				'url' => trailingslashit( $saveDirUri ) . $saveFileName
			];
		}
		return false;
	}

}
