<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://cargus.ro/
 * @since             1.0.0
 * @package           Cargus
 *
 * @wordpress-plugin
 * Plugin Name:       Cargus
 * Plugin URI:        http://woocommerce.demo.cargus.ro/
 * Description:       Metoda de livrare Cargus pentru WooCommerce.
 * Version:           1.6.0
 * Author:            Cargus
 * Author URI:        https://cargus.ro/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cargus
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'CARGUS_VERSION', '1.6.0' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cargus.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/class-cargus-debug.php';
/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cargus() {

	$plugin = new Cargus();
	$plugin->run();

	// Hook to admin menu to add custom tools page
	add_action( 'admin_menu', 'cargus_add_tools_page' );

	// Handle file delete request
	add_action( 'admin_init', 'cargus_handle_delete_log' );
	add_action( 'wp_ajax_cargus_download_log', 'cargus_download_log' );
	add_action( 'wp_ajax_cargus_delete_log', 'cargus_delete_log' );

	// Load test runner if tests directory exists
	if ( file_exists( plugin_dir_path( __FILE__ ) . 'tests/run-tests.php' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'tests/run-tests.php';
	}

	function cargus_download_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 'Unauthorized', array( 'response' => 403 ) );
		}

		$file_path = WP_CONTENT_DIR . '/cargus_debug.log';

		if ( ! file_exists( $file_path ) ) {
			wp_die( 'Log file not found', 'Error', array( 'response' => 404 ) );
		}

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="cargus_debug.log"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		readfile( $file_path );
		exit;
	}

	function cargus_delete_log() {
		// Check if the user has the necessary capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 'Unauthorized', array( 'response' => 403 ) );
		}

		// Verify nonce for security
		check_admin_referer( 'cargus_delete_log', 'cargus_delete_log_nonce' );

		// Path to the cargus_debug.log file
		$file_path = WP_CONTENT_DIR . '/cargus_debug.log';

		// Check if the file exists and delete it
		if ( file_exists( $file_path ) ) {
			if ( unlink( $file_path ) ) {
				wp_redirect( admin_url( 'admin.php?page=cargus_tools&message=log_deleted' ) );
			} else {
				wp_die( 'Failed to delete the log file', 'Error', array( 'response' => 500 ) );
			}
		} else {
			wp_die( 'Log file not found', 'Error', array( 'response' => 404 ) );
		}
		exit;
	}

	add_action( 'wp_ajax_cargus_delete_specific_file', 'cargus_delete_specific_file' );
	// function to delete multiple files
	add_action( 'wp_ajax_cargus_delete_multiple_files', 'cargus_delete_multiple_files' );

	function cargus_delete_specific_file() {

		$debugger = CargusDebug::get_instance();
		// Check if the user has the necessary capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			wp_die();
		}

		// Verify nonce for security
		check_admin_referer( 'cargus_delete_file', 'cargus_delete_file_nonce' );

		// Get the file name from the request
		$file_name = isset( $_POST['file_name'] ) ? sanitize_text_field( $_POST['file_name'] ) : '';

		$debugger->log( 'file_name', $_POST['file_name'] );
		$debugger->log( 'file_name', $file_name );

		// Path to the file in the /admin/locations/ directory
		$file_path = plugin_dir_path( __DIR__ ) . 'cargus/admin/locations/' . $file_name;

		// Check if the file exists and delete it
		if ( file_exists( $file_path ) ) {
			if ( unlink( $file_path ) ) {
				wp_send_json_success( array( 'message' => 'File successfully deleted.' ) );
			} else {
				wp_send_json_error( array( 'message' => 'Failed to delete the file.' ) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'File not found.' ) );
		}
		wp_die();
	}

	function cargus_delete_multiple_files() {
		$debugger = CargusDebug::get_instance();

		// Check if the user has the necessary capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			wp_die();
		}

		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'], 'cargus_delete_multiple_files' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			wp_die();
		}

		// Get the file names from the request
		$file_names = isset( $_POST['file_names'] ) ? $_POST['file_names'] : array();

		if ( empty( $file_names ) || ! is_array( $file_names ) ) {
			wp_send_json_error( array( 'message' => 'No files selected for deletion.' ) );
			wp_die();
		}

		$deleted_files  = array();
		$failed_files   = array();
		$excluded_files = array( 'cttd.json', 'countries.json', 'counties.json', 'pudo_locations.json' );

		foreach ( $file_names as $file_name ) {
			$file_name = sanitize_text_field( $file_name );

			// Skip excluded files
			if ( in_array( $file_name, $excluded_files ) ) {
				continue;
			}

			// Path to the file in the /admin/locations/ directory
			$file_path = plugin_dir_path( __DIR__ ) . 'cargus/admin/locations/' . $file_name;

			// Check if the file exists and delete it
			if ( file_exists( $file_path ) ) {
				if ( unlink( $file_path ) ) {
					$deleted_files[] = $file_name;
					$debugger->log( 'deleted_file', $file_name );
				} else {
					$failed_files[] = $file_name;
					$debugger->log( 'failed_to_delete', $file_name );
				}
			} else {
				$failed_files[] = $file_name . ' (not found)';
			}
		}

		$message = '';
		if ( ! empty( $deleted_files ) ) {
			$message .= 'Successfully deleted: ' . implode( ', ', $deleted_files );
		}
		if ( ! empty( $failed_files ) ) {
			if ( ! empty( $message ) ) {
				$message .= '\n';
			}
			$message .= 'Failed to delete: ' . implode( ', ', $failed_files );
		}

		if ( ! empty( $deleted_files ) ) {
			wp_send_json_success(
				array(
					'message'       => $message,
					'deleted_count' => count( $deleted_files ),
					'failed_count'  => count( $failed_files ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => $message ) );
		}
		wp_die();
	}

	function cargus_tools_page() {
		// Directory containing the files to display and delete
		$directory = plugin_dir_path( __DIR__ ) . '/cargus/admin/locations/';

		// List of files to exclude
		$exclude_files = array( 'cttd.json', 'countries.json', 'counties.json', 'pudo_locations.json' );

		// Check if the directory exists
		if ( is_dir( $directory ) ) {
			// Get all files in the directory
			$files = array_diff( scandir( $directory ), array( '..', '.' ) );
		} else {
			$files = array(); // Set to empty array if directory doesn't exist
		}

		?>
		<div class="wrap">
			<h1>Cargus Debug Tool</h1>
			<p>Delete specific files from /admin/locations/ directory (excluding cttd.json, countries.json, counties.json):</p>

			<?php
			// Check if there are any files in the directory
			if ( $files ) {
				?>
				<div id="cargus-file-manager">
					<div style="margin-bottom: 15px;">
						<label>
							<input type="checkbox" id="select-all-files"> Select All
						</label>
						<button type="button" id="delete-selected-files" class="button button-primary" style="margin-left: 10px;" disabled>Delete Selected Files</button>
					</div>

					<div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9; max-height: 300px; overflow-y: auto;">
						<?php
						foreach ( $files as $file ) {
							// Skip excluded files
							if ( in_array( $file, $exclude_files ) ) {
								continue;
							}
							?>
							<div style="margin-bottom: 5px;">
								<label>
									<input type="checkbox" class="file-checkbox" value="<?php echo esc_attr( $file ); ?>">
									<?php echo esc_html( $file ); ?>
								</label>
							</div>
							<!-- Form for Deleting Each File -->
							<!-- <form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php?action=cargus_delete_specific_file' ) ); ?>">
								<?php wp_nonce_field( 'cargus_delete_file', 'cargus_delete_file_nonce' ); ?>
								<input type="hidden" name="file_name" value="<?php echo esc_attr( $file ); ?>">
								<input type="submit" class="button button-secondary" value="<?php echo esc_html( $file ); ?>">
							</form> -->
							<?php
						}
						?>
					</div>
				</div>
				<?php
			} else {
				echo '<p>No files found in the directory.</p>';
			}
			?>
	
			<h2>Manage Debug Log</h2>
	
			<!-- Download Button for cargus_debug.log -->
			<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=cargus_download_log' ) ); ?>" class="button">Download Debug Log (cargus_debug.log)</a>
	
			<!-- Delete Button for cargus_debug.log -->
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php?action=cargus_delete_log' ) ); ?>">
				<?php wp_nonce_field( 'cargus_delete_log', 'cargus_delete_log_nonce' ); ?>
				<input type="submit" class="button button-secondary" value="Delete Debug Log (cargus_debug.log)">
			</form>
	
			<!-- Display success message if a file is deleted -->
			<?php if ( isset( $_GET['message'] ) ) : ?>
				<?php if ( $_GET['message'] === 'file_deleted' ) : ?>
					<div class="updated notice is-dismissible">
						<p>File successfully deleted.</p>
					</div>
				<?php elseif ( $_GET['message'] === 'log_deleted' ) : ?>
					<div class="updated notice is-dismissible">
						<p>Debug log successfully deleted.</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	function cargus_add_tools_page() {
		add_management_page(
			'Cargus Debug Tool', // Page title
			'Cargus Debug Tool', // Menu title
			'manage_options',    // Capability
			'cargus-debug-tool', // Menu slug
			'cargus_tools_page'  // Callback function
		);
	}

	function cargus_handle_delete_log() {
		// Check if the delete request is sent
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'cargus_delete_log' ) {
			// Check the nonce for security
			if ( ! isset( $_POST['cargus_delete_log_nonce'] ) || ! wp_verify_nonce( $_POST['cargus_delete_log_nonce'], 'cargus_delete_log' ) ) {
				wp_die( 'Nonce verification failed', 'Error', array( 'response' => 403 ) );
			}

			// Check if user has the necessary capability
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Unauthorized', 'Unauthorized', array( 'response' => 403 ) );
			}

			// Path to the log file
			$log_file = WP_CONTENT_DIR . '/cargus_debug.log';

			if ( file_exists( $log_file ) ) {
				// Attempt to delete the file
				if ( unlink( $log_file ) ) {
					// Redirect with success message
					wp_redirect( add_query_arg( 'cargus_log_deleted', 'true', wp_get_referer() ) );
					exit;
				} else {
					wp_die( 'Failed to delete the log file', 'Error', array( 'response' => 500 ) );
				}
			} else {
				wp_die( 'Log file not found', 'Error', array( 'response' => 404 ) );
			}
		}
	}

	// Display a success message if the log file was deleted
	add_action( 'admin_notices', 'cargus_admin_notices' );

	function cargus_admin_notices() {
		if ( isset( $_GET['cargus_log_deleted'] ) && $_GET['cargus_log_deleted'] === 'true' ) {
			echo '<div class="notice notice-success is-dismissible"><p>Debug log file has been deleted.</p></div>';
		}
	}

	/**
	 * Clean up corrupted cache files on plugin activation/update
	 */
	function cargus_cleanup_corrupted_cache_files() {
		$cache_dir = plugin_dir_path( __FILE__ ) . 'admin/locations/';

		if ( ! is_dir( $cache_dir ) ) {
			return;
		}

		// Files to exclude from cleanup
		$exclude_files = array( 'cttd.json', 'countries.json', 'counties.json', 'pudo_locations.json' );

		// Get all JSON files
		$files         = glob( $cache_dir . '*.json' );
		$cleaned_files = array();

		foreach ( $files as $file_path ) {
			$file_name = basename( $file_path );

			// Skip excluded files
			if ( in_array( $file_name, $exclude_files ) ) {
				continue;
			}

			$content = file_get_contents( $file_path );

			// Check if file contains the error message
			if ( $content === '"Failed to authenticate!"' ||
				$content === '\"Failed to authenticate!\"' ||
				strpos( $content, 'Failed to authenticate!' ) !== false ) {

				// Delete corrupted file
				if ( unlink( $file_path ) ) {
					$cleaned_files[] = $file_name;
				}
			}

			// Also check for very small files
			elseif ( strlen( $content ) <= 10 ) {
				if ( unlink( $file_path ) ) {
					$cleaned_files[] = $file_name;
				}
			}
		}

		// Log cleanup results
		if ( ! empty( $cleaned_files ) ) {
			error_log( 'Cargus Plugin: Cleaned up ' . count( $cleaned_files ) . ' corrupted cache files: ' . implode( ', ', $cleaned_files ) );
		}
	}

	// Also run on plugin update (version check)
	function cargus_check_version_and_cleanup() {
		$current_version = get_option( 'cargus_plugin_version', '0.0.0' );
		$new_version     = CARGUS_VERSION;

		if ( version_compare( $current_version, $new_version, '<' ) ) {
			// This is an update, run cleanup
			cargus_cleanup_corrupted_cache_files();

			// Update version number
			update_option( 'cargus_plugin_version', $new_version );
		}
	}
	add_action( 'admin_init', 'cargus_check_version_and_cleanup' );
}

run_cargus();

// Run cleanup on plugin activation (must be outside of any class/function)
register_activation_hook( __FILE__, 'cargus_cleanup_corrupted_cache_files' );
