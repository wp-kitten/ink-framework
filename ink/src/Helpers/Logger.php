<?php

namespace Ink\Helpers;

if ( ! defined( 'INK_FRAMEWORK' ) ) {
	exit();
}

/**
 * Class Logger
 * @package Ink\Helpers
 *
 * Utility class to be used for logging.
 */
class Logger
{
	const DEBUG = 0;
	const INFO = 1;
	const WARNING = 2;
	const SYSTEM = 3;
	const ERROR = 4;
	const SEVERE = 5;
	const CRITICAL = 6;
	const FATAL = 7;

	/**
	 * Holds the instances of this class
	 * @var array
	 */
	private static $_instances = [];

	/**
	 * Internal cache of the logging levels
	 * @see \Ink\Helpers\Logger::getLevels()
	 * @var array
	 */
	private $_levels = [];

	/**
	 * Internal cache of the minimum logging level
	 * @see \Ink\Helpers\Logger::setMinLogLevel()
	 * @var int
	 */
	private $_loggingLevel = 0;

	/**
	 * Stores the path to the log file (if writable, otherwise the default ini setting error_log)
	 * @var string
	 */
	private $_logFilePath = '';

	/**
	 * Holds the reference to the instance of the WP_Filesystem_Base
	 * @var null|\WP_Filesystem_Base
	 */
	private $_fs = null;

	/**
	 * Logger constructor.
	 */
	private function __construct()
	{
		if ( ! $this->_fs || ! is_object( $this->_fs ) ) {
			$this->_fs = new InkFileSystem();
		}
	}

	/**
	 * Retrieve the reference to the instance of this class
	 * @param string $name The name that will be assigned to this instance
	 * @return \Ink\Helpers\Logger
	 */
	final public static function getInstance( $name )
	{
		if ( ! isset( self::$_instances[$name] ) ) {
			self::$_instances[$name] = new self;
		}
		return self::$_instances[$name];
	}


	/**
	 * Set the path to the log file. If omitted, the existent value of ini_get('error_log') will be used.
	 * @param string $logFilePath The path to the log file
	 * @return \Ink\Helpers\Logger
	 */
	public function setLogFilePath( $logFilePath = '' )
	{
		if ( ! empty( $logFilePath ) ) {
			if ( ! empty( $this->_fs->errors ) ) {
				$this->_logFilePath = $logFilePath;
			}
			else {
				$this->_logFilePath = \ini_get( 'error_log' );
			}
			//#! Cleanup
			$fs = null;
		}
		return $this;
	}

	/**
	 * Set the minimum logging level. All values higher than the specified one will be logged.
	 * @param int $level
	 * @return \Ink\Helpers\Logger
	 */
	public function setMinLogLevel( $level = self::SYSTEM )
	{
		$this->_loggingLevel = $level;
		return $this;
	}

	/**
	 * Write into the log file
	 * @param string $text
	 * @param null|mixed $data
	 * @param int $level
	 * @return \Ink\Helpers\Logger
	 */
	public function write( $text, $data = null, $level = 0 )
	{
		if ( empty( $level ) ) {
			$level = $this->getLoggingLevel();
		}

		if ( ! $this->__canLogLevel( $level ) ) {
			return $this;
		}

		$df = get_option( 'date_format' );
		$tf = get_option( 'time_format' ) . ' T';
		$dateTime = date( $df . ' ' . $tf );

		$text = PHP_EOL . '[' . $dateTime . '] [Ink][Logger][' . $this->translateLevel( $level ) . '] ' . rtrim( $text, '.' );
		if ( ! is_null( $data ) ) {
			if ( is_scalar( $data ) ) {
				$text .= ': ' . $data;
			}
			else {
				$text .= ': ' . var_export( $data, 1 );
			}
		}
		else {
			$text .= '.';
		}

		if ( ! $this->_fs ) {
			//#! Fallback to system settings
			\error_log( $text );
		}
		else {
			$this->_fs->append( $this->_logFilePath, $text, 0777 );
		}

		return $this;
	}

	/**
	 * Translate the specified $level into a human readable format
	 * @param int $level
	 * @return string
	 */
	public function translateLevel( $level )
	{
		if ( ! empty( $this->_levels ) ) {
			if ( isset( $this->_levels[$level] ) ) {
				return $this->_levels[$level];
			}
		}
		$this->getLevels();
		return ( isset( $this->_levels[$level] ) ? $this->_levels[$level] : 'N/A' );
	}

	/**
	 * Retrieve and cache the logging levels
	 * @return array
	 */
	public function getLevels()
	{
		if ( ! empty( $this->_levels ) ) {
			return $this->_levels;
		}
		$this->_levels = [
			self::DEBUG => 'DEBUG',
			self::INFO => 'INFO',
			self::WARNING => 'WARNING',
			self::SYSTEM => 'SYSTEM',
			self::ERROR => 'ERROR',
			self::SEVERE => 'SEVERE',
			self::CRITICAL => 'CRITICAL',
			self::FATAL => 'FATAL',
		];
		return $this->_levels;
	}

	/**
	 * Retrieve the currently setup logging level
	 * @return int
	 */
	public function getLoggingLevel()
	{
		return $this->_loggingLevel;
	}

	/**
	 * Check to see whether or not the specified logging level is higher or equal the minimum accepted logging level.
	 * @param int $level
	 * @return bool
	 */
	private function __canLogLevel( $level )
	{
		return ( $level >= $this->getLoggingLevel() );
	}

}
