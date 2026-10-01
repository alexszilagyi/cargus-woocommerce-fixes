<?php
define( 'CACHE_DIR', plugin_dir_path( __FILE__ ) . 'locations/' );
define( 'FILE_THRESHOLD_SECONDS', 7 * 24 * 3600 );  // 7 days
/**
 * The functionality of the plugin responsible for storing and refreshing the addresses cache.
 *
 * It creates and checks the plugin addresses cache files.
 *
 * @package Cargus
 * @subpackage Cargus/public
 * @author Cargus <contact@cargus.ro>
 */
class Cargus_Cache {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		clearstatcache();

		$this->load_dependencies();
		// check if cache dir is usable.
		// create dir.
		if ( ! is_dir( CACHE_DIR ) && ! mkdir( CACHE_DIR, 0775 ) ) {
			$msg = __CLASS__ . '::' . __FUNCTION__ . ' Unable to create cache dir: ' . CACHE_DIR;
			$this->write_log( $msg );
		}

		if ( ! is_writable( CACHE_DIR ) ) {
			$msg = __CLASS__ . '::' . __FUNCTION__ . ' Unable to write in cache dir: ' . CACHE_DIR;
			$this->write_log( $msg );
		}
	}

	/**
	 * Include the cargus shipping method class.
	 *
	 * @since    1.0.0
	 */
	public function load_dependencies() {

		/**
		 * The class responsible for creating the Cargus api methods.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-cargus-api.php';

		/**
		 * The class responsible for creating the Cargus log messages.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-cargus-debug.php';
	}

	/**
	 * Write error logs in the WordPress error log.
	 *
	 * @param String $log The error log.
	 */
	private function write_log( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}

	/**
	 * Get specific cached file.
	 *
	 * @param string $filename The file name.
	 * @param bool   $also_expired If the file experd or not.
	 */
	public function get_cached_file( $filename, $also_expired = false ) {
		// Initialize the debugger.
		$debugger = CargusDebug::get_instance();

		clearstatcache();
		$file = CACHE_DIR . $filename . '.json';

		// Check if file is present in cache dir.
		if ( ! file_exists( $file ) ) {
			$debugger->debug( "File does not exist: $file" );
			return false;
		}

		// Check if expired or updating.
		$elapsed = time() - filemtime( $file );
		if ( $elapsed >= FILE_THRESHOLD_SECONDS && ! $also_expired ) {
			$debugger->debug( "File expired: $file, elapsed time: $elapsed seconds" );
			return false;
		}

		// Get data.
		$data = file_get_contents( $file );

		if ( false === $data ) {
			$debugger->debug( "Error reading file: $file" );
			return false;
		}

		// Check if the file is empty or contains insufficient data.
		if ( strlen( $data ) <= 2 || 'Failed to authenticate!' === $data ) {
			$debugger->debug( "File was empty or too short: $file, data=$data" );

			// Attempt to delete the corrupted file.
			if ( ! unlink( $file ) ) {
				$debugger->debug( "Unable to delete file: $file" );
			}

			return false;
		}

		// Check if the JSON is valid.
		$json_data = json_decode( $data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$debugger->debug( "Corrupted JSON in file: $file, error: " . json_last_error_msg() );
			$debugger->debug( "Attempting to remove the corrupted file: $file" );
			// Attempt to delete the corrupted file.
			if ( ! unlink( $file ) ) {
				$debugger->debug( "Unable to unlink corrupted file: $file" );
			}

			return false;
		}

		$debugger->debug( "Successfully retrieved valid JSON data from: $file" );

		// Return valid data.
		return $data;
	}


	/**
	 * Write specific cache file.
	 *
	 * @param string $filename The file name.
	 * @param string $json The json to be written in to the file.
	 */
	public function write_cache_file( $filename, $json ) {
		clearstatcache();

		$file = CACHE_DIR . $filename . '.json';

		// Write the file..
		$fp = @fopen( $file, 'w' );

		if ( false === $fp ) {
			$msg = __CLASS__ . '::' . __FUNCTION__ . " Unable to write file: $file";
			$this->write_log( $msg );

			return false;
		}

		$status = true;

		if ( false === fwrite( $fp, $json ) ) {
			$msg = __CLASS__ . '::' . __FUNCTION__ . " Writing to file $file failed, data=$json";
			$this->write_log( $msg );

			$status = false;
		}

		fclose( $fp );

		return $status;
	}

	/**
	 * Get the cargus streets.
	 *
	 * @param string $city The city id.
	 */
	public function get_streets( $city = null ) {
		if ( is_null( $city ) ) {
			return '[]';
		}

		// get file from cache.
		$json = $this->get_cached_file( 'str' . $city );

		if ( false === $json ) {
			// file not found in cache.

			// get fresh data.
			$cargus = Cargus_Api::get_instance();
			$data = $cargus->get_streets( $city );

			// VALIDATE: Don't save error messages to cache
			if ( $data !== "Failed to authenticate!" && !empty($data) && is_array($data) ) {
				$json = wp_json_encode( $data );
				// save data to cache.
				$this->write_cache_file( 'str' . $city, $json );
			} else {
				// API failed - try expired cache
				$json = $this->get_cached_file( 'str' . $city, true );
				if ( false === $json ) {
					return wp_json_encode( array() );
				}
			}
		}

		// return json data.
		return $json;
	}

	/**
	 * Get the cargus counties.
	 */
	public function get_counties() {
		// get file from cache.
		$json = $this->get_cached_file( 'counties' );

		if ( false === $json ) {
			// file not found in cache.

			// get fresh data.
			$cargus = Cargus_Api::get_instance();
			$data   = $cargus->get_counties();

			// VALIDATE: Don't save error messages to cache
			if ( $data !== "Failed to authenticate!" && !empty($data) && is_array($data) ) {
				$json = wp_json_encode( $data );
				// save data to cache.
				$this->write_cache_file( 'counties', $json );
			} else {
				// API failed - try expired cache
				$json = $this->get_cached_file( 'counties', true );
				if ( false === $json ) {
					return array(); // Return empty array for counties
				}
			}
		}

		// return data.
		return json_decode( $json, true );
	}

	/**
	 * Get the cargus localities.
	 *
	 * @param string $county_id The city id.
	 */
	public function get_localities( $county_id = null ) {
		if ( is_null( $county_id ) ) {
			return array();
		}
		// get file from cache.
		$json = $this->get_cached_file( 'localities' . $county_id );

		if ( false === $json ) {
			// file not found in cache.

			// get fresh data.
			$cargus = Cargus_Api::get_instance();
			$data   = $cargus->get_localities( $county_id );
			
			// VALIDATE: Don't save error messages to cache
			if ( $data !== "Failed to authenticate!" && !empty($data) && is_array($data) ) {
				$json = wp_json_encode( $data );
				// save data to cache.
				$this->write_cache_file( 'localities' . $county_id, $json );
			} else {
				// API failed - try expired cache
				$json = $this->get_cached_file( 'localities' . $county_id, true );
				if ( false === $json ) {
					return wp_json_encode( array() );
				}
			}
		}
		// return json data.
		return $json;
	}

	/**
	 * Get the cargus localities for Bulgaria
	 */
	public function get_localities_bulgaria() {
		// Add debugger.
		$debugger = CargusDebug::get_instance();

		// Get file from cache.
		$json = $this->get_cached_file( 'bulgaria_localities' );

		if ( false === $json ) {
			// Cache miss - get fresh data from the API.
			$cargus_intl = Cargus_Api::get_instance();
			$data        = $cargus_intl->get_localities_bulgaria();

			// VALIDATE: Don't save error messages to cache
			if ( $data !== "Failed to authenticate!" && !empty($data) && is_array($data) ) {
				// Encode the data with unescaped Unicode characters.
				$json = json_encode( $data, JSON_UNESCAPED_UNICODE );
				$debugger->debug( 'get_localities_bulgaria json: ' . $json );

				// Save the JSON data to cache.
				$this->write_cache_file( 'bulgaria_localities', $json );
			} else {
				// API failed - try expired cache
				$json = $this->get_cached_file( 'bulgaria_localities', true );
				if ( false === $json ) {
					$debugger->debug( 'get_localities_bulgaria: No cache available, returning empty JSON' );
					return json_encode( array(), JSON_UNESCAPED_UNICODE );
				}
			}
		}

		// Return the cached or fresh JSON data.
		return $json;
	}

	/**
	 * Get the cargus zipcode for Bulgaria
	 *
	 * @param string $city_name The city id.
	 */
	public function get_zipcode_bulgaria( $city_name ) {
		// Get cached data.
		$debugger = CargusDebug::get_instance();
		$debugger->log( 'caching mechanism: city_name', $city_name );
		$json = $this->get_cached_file( 'bulgaria_localities' );

		if ( false === $json ) {
			// If cache is empty, fetch fresh data.
			$counties_json = file_get_contents( plugin_dir_path( __DIR__ ) . 'admin/locations/bulgaria_localities.json' );
			$this->write_cache_file( 'bulgaria_localities', $counties_json );
			$counties = json_decode( $counties_json, true );
		} else {
			// If cache exists, decode cached data.
			$counties = json_decode( $json, true );
		}

		// Search for the zip code of the given city.
		foreach ( $counties as $county ) {
			if ( trim( addslashes( sanitize_text_field( $city_name ) ) ) === $county['name'] ) {
				return $county['zipCode'];
			}
		}
		return false;
	}
}
