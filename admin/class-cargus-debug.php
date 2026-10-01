<?php
/**
 * The functionality of the plugin responsible for writing error logs in the logfile.
 *
 * @package Cargus
 * @subpackage Cargus/admin
 * @author Cargus <contact@cargus.ro>
 */
class CargusDebug {
	// Define log levels.
	const LEVEL_ERROR   = 'ERROR';
	const LEVEL_WARNING = 'WARNING';
	const LEVEL_INFO    = 'INFO';
	const LEVEL_DEBUG   = 'DEBUG';

	// Path to log file in wp-content directory.
	private static $log_file = '';

	// Maximum log file size in bytes (e.g., 1MB).
	private static $max_log_size = 1048576; // 1 MB.

	// Single instance of the class.
	private static $instance = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since    1.5.0
	 */
	private function __construct() {
		// Set the log file path
		self::$log_file = WP_CONTENT_DIR . '/cargus_debug.log';
	}

	/**
	 * Get the single instance of the class.
	 *
	 * @since    1.5.0
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check if debugging is enabled.
	 *
	 * @return bool
	 */
	private function is_debugging_enabled() {
		$cargus_options = get_option( 'woocommerce_cargus_settings' );
		return isset( $cargus_options['debug_cargus'] ) && ( $cargus_options['debug_cargus'] == 'yes' || $cargus_options['debug_cargus'] == 1 );
	}

	/**
	 * Check if the log file exceeds the maximum size.
	 *
	 * @return bool
	 */
	private function is_log_file_too_large() {
		return file_exists( self::$log_file ) && filesize( self::$log_file ) >= self::$max_log_size;
	}

	/**
	 * Rotate the log file.
	 */
	private function rotate_log_file() {
		$timestamp    = date( 'Y-m-d_H-i-s' );
		$new_log_file = WP_CONTENT_DIR . '/cargus_debug_' . $timestamp . '.log';
		rename( self::$log_file, $new_log_file );
	}

	/**
	 * Log a message.
	 *
	 * @param string $message   Message to log.
	 * @param string $level     Log level (ERROR, WARNING, INFO, DEBUG).
	 */
	public function log( $message, $level = self::LEVEL_INFO ) {
		if ( $this->is_debugging_enabled() ) {
			if ( $this->is_log_file_too_large() ) {
				$this->rotate_log_file();
			}

			// Format log message with date and level
			$formatted_message = '[' . date( 'Y-m-d H:i:s' ) . "] [$level] $message" . PHP_EOL;

			// Write the message to the log file
			file_put_contents( self::$log_file, $formatted_message, FILE_APPEND );
		}
	}

	/**
	 * Log a error message.
	 *
	 * @param string $message   Message to log.
	 */
	public function error( $message ) {
		if ( $this->is_debugging_enabled() ) {
			$this->log( $message, self::LEVEL_ERROR );
		}
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message   Message to log.
	 */
	public function debug( $message ) {
		if ( $this->is_debugging_enabled() ) {
			$this->log( $message, self::LEVEL_DEBUG );
		}
	}

	/**
	 * Log a info message.
	 *
	 * @param string $message   Message to log.
	 */
	public function info( $message ) {
		if ( $this->is_debugging_enabled() ) {
			$this->log( $message, self::LEVEL_INFO );
		}
	}

	/**
	 * Log a info message.
	 *
	 * @param string $message   Message to log.
	 */
	public function warning( $message ) {
		if ( $this->is_debugging_enabled() ) {
			$this->log( $message, self::LEVEL_WARNING );
		}
	}
}
