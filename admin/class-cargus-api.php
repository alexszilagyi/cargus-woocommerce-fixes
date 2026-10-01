<?php
/**
 * The api calls integrations functionality of the plugin.
 *
 * @link       https://cargus.ro/
 * @since      1.0.0
 *
 * @package    Cargus
 * @subpackage Cargus/admin
 */

if ( ! class_exists( 'Cargus_Api' ) ) {
    /**
     * The api calls integrations functionality of the plugin.
     *
     * Defines the plugin api calls method.
     *
     * @package    Cargus
     * @subpackage Cargus/admin
     * @author     Cargus <contact@cargus.ro>
     */
    #[AllowDynamicProperties]
    class Cargus_Api {

        /**
         * The api key of this plugin.
         *
         * @since    1.0.0
         * @access   private
         * @var      string    $key    The api key of this plugin.
         */
        private $key;

        /**
         * The api url of this plugin.
         *
         * @since    1.0.0
         * @access   private
         * @var      string    $url    The api url of this plugin.
         */
        private $url;

        /**
         * The export api url of this plugin.
         *
         * @since    1.5.0
         * @access   private
         * @var      string    $url    The api url of this plugin.
         */
        private $export_url;

        /**
         * The api username of this plugin.
         *
         * @since    1.0.0
         * @access   private
         * @var      string    $username    The api username of this plugin.
         */
        private $username;

        /**
         * The api password of this plugin.
         *
         * @since    1.0.0
         * @access   private
         * @var      string    $password    The api password of this plugin.
         */
        private $password;

        /**
         * The api login token.
         *
         * @since    1.0.0
         * @access   private
         * @var      string    $password    The api password of this plugin.
         */
        public $token;

        /**
         * The api login export token.
         *
         * @since    2.0
         * @access   private
         * @var      string    $password    The api password of this plugin.
         */
        public $export_token;

        /**
         * The single instance of the class.
         *
         * @since    1.0.0
         * @access   private
         * @var      Cargus_Api    $instance    The single instance of the class.
         */
        private static $instance = null;

        /**
         * Track if session has been closed to avoid multiple session operations.
         *
         * @since    1.5.2
         * @access   private
         * @var      bool    $session_closed    Whether the session has been closed.
         */
        private static $session_closed = false;

        /**
         * Set the api key and url of the plugin.
         *
         * @since 1.0.0
         * @param string $url       The api url of this plugin.
         * @param string $key       The api key of this plugin.
         * @param string $export    Check if the api login is for the export api.
         */
        private function __construct( $url = '', $key = '', $export = false ) {

            require_once 'class-cargus-debug.php';

            $woocommerce_cargus_settings        = get_option( 'woocommerce_cargus_settings' );
            $woocommerce_cargus_export_settings = get_option( 'woocommerce_cargus_export_settings' );

            $this->url        = ( '' !== $url ) ? $url : ( $woocommerce_cargus_settings['webservice'] ?? '' );
            $this->key        = ( '' !== $key ) ? $key : ( $woocommerce_cargus_settings['apikey'] ?? '' );
            $this->export_url = $woocommerce_cargus_export_settings['webservice'] ?? '';
        }

        /**
         * Ensure session is active for operations.
         *
         * @since 1.5.2
         * @return bool Whether session is available for use.
         */
        private static function ensure_session_active() {
            if ( session_status() === PHP_SESSION_NONE ) {
                if ( headers_sent() ) {
                    return false;
                }
                session_start();
                self::$session_closed = false;
                return true;
            }
            return session_status() === PHP_SESSION_ACTIVE;
        }

        /**
         * Close session safely to avoid REST API/loopback interference.
         *
         * @since 1.5.2
         */
        private static function close_session_safely() {
            if ( session_status() === PHP_SESSION_ACTIVE && ! self::$session_closed ) {
                    session_write_close();
                    self::$session_closed = true;
            }
        }

        /**
         * Get the single instance of the class.
         *
         * @since 1.0.0
         * @param string $url       The api url of this plugin.
         * @param string $key       The api key of this plugin.
         * @param bool   $export    Whether to use the export API settings.
         * @return Cargus_Api       The single instance of the Cargus_Api class.
         */
        public static function get_instance( $url = '', $key = '' ) {
            // Ensure session is available for operations
            if ( ! self::ensure_session_active() ) {
                // If session cannot be started, return instance without session operations.
                // Explicit credentials (settings save) must still replace the instance.
                if ( self::$instance === null || ( $url !== '' && $key !== '' ) ) {
                    self::$instance = new self( $url, $key );
                }
                return self::$instance;
            }

            if ( self::$instance === null ) {
                if ( isset( $_SESSION['cargus_api_instance'] ) ) {
                    self::$instance = unserialize( $_SESSION['cargus_api_instance'] );
                } else {
                    self::$instance                  = new self( $url, $key );
                    $_SESSION['cargus_api_instance'] = serialize( self::$instance );
                }
            } elseif ( $url !== '' && $key !== '' ) {
                self::$instance                  = new self( $url, $key );
                $_SESSION['cargus_api_instance'] = serialize( self::$instance );
            }

            // Close the session to avoid REST API/loopback interference
            self::close_session_safely();

            return self::$instance;
        }

        /**
         * Get a property from the cargus_api_instance session.
         *
         * @param string $property The property to retrieve.
         * @return mixed The property value or null if not set.
         */
        private function get_instance_property( string $property ) {
            // Without a session (headers already sent, CLI, cron, REST) the
            // values on this instance are the ones loaded from the settings.
            // Returning null here made get_api_key() empty and switched
            // Ship & Go off for that request and skipped the token refresh cron.
            if ( ! self::ensure_session_active() ) {
                return $this->$property ?? null;
            }

            if ( isset( $_SESSION['cargus_api_instance'] ) ) {
                $instance = unserialize( $_SESSION['cargus_api_instance'] );
                $value    = $instance->$property ?? null;
                // Close the session to avoid REST API/loopback interference
                self::close_session_safely();
                return $value;
            }

            self::close_session_safely();
            return $this->$property ?? null;
        }

        /**
         * Get the api key.
         */
        public function get_api_key() {
            return $this->get_instance_property( 'key' );
        }

        /**
         * Get the api url.
         */
        public function get_url() {
            return $this->get_instance_property( 'url' );
        }

        /**
         * Get the api export url.
         */
        public function get_export_url() {
            return $this->get_instance_property( 'export_url' );
        }

        /**
         * Set the export url.
         *
         * @since 1.5.0
         * @param string $new_export_url The api export url of this plugin.
         */
        public function set_export_url( $new_export_url ) {
            if ( ! empty( $new_export_url ) && filter_var( $new_export_url, FILTER_VALIDATE_URL ) ) {
                $this->export_url = $new_export_url;
                // Save the export url in the class instance.
                if ( self::ensure_session_active() && isset( $_SESSION['cargus_api_instance'] ) ) {
                    $instance                        = unserialize( $_SESSION['cargus_api_instance'] );
                    $instance->export_url            = $this->export_url;
                    $_SESSION['cargus_api_instance'] = serialize( $instance );
                    // Close the session to avoid REST API/loopback interference
                    self::close_session_safely();
                }
            } else {
                throw new InvalidArgumentException( 'Invalid URL provided.' );
            }
        }


    /**
     * Automatically refresh the token when authentication fails.
     * 
     * When an API call fails due to authentication (401 or "Failed to authenticate!"),
     * this automatically generates a new token so the operation can succeed.
     *
     * @since  1.0.0
     * @param  bool   $is_export  whether to refresh the export token or regular token.
     * @return string|false       The new token on success, false on failure.
     */
    private function auto_refresh_token( $is_export = false ) {
        $debugger = CargusDebug::get_instance();
        $debugger->info( 'Authentication failed - attempting automatic token refresh...' );

        $settings_key = $is_export ? 'woocommerce_cargus_export_settings' : 'woocommerce_cargus_settings';
        $settings = get_option( $settings_key );

        if ( empty( $settings['username'] ) || empty( $settings['password'] ) ) {
            $debugger->error( 'Cannot refresh token - username or password not found in settings' );
            return false;
        }

        $fields = array(
            'UserName' => $settings['username'],
            'Password' => $settings['password'],
        );

        try {
            $new_token = $this->generate_token( $fields, $is_export );
            
            if ( $new_token && ! is_object( $new_token ) && ! is_array( $new_token ) && 'error' !== $new_token ) {
                $debugger->info( 'Token automatically refreshed successfully!' );
                return $new_token;
            } else {
                $debugger->error( 'Failed to automatically refresh token: ' . wp_json_encode( $new_token ) );
                return false;
            }
        } catch ( Exception $e ) {
            $debugger->error( 'Exception during automatic token refresh: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if the API response indicates an authentication failure.
     * 
     * Detects various forms of authentication errors from the Cargus API.
     *
     * @since  1.0.0
     * @param  mixed  $response       The API response to check.
     * @param  int    $response_code  The HTTP response code.
     * @return bool                   True if authentication failed, false otherwise.
     */
    private function is_auth_failure( $response, $response_code ) {
        if ( 401 === (int) $response_code || '401' === $response_code ) {
            return true;
        }
        if ( is_string( $response ) && 'Failed to authenticate!' === $response ) {
            return true;
        }
        if ( is_string( $response ) && '"Failed to authenticate!"' === $response ) {
            return true;
        }

        if ( is_object( $response ) ) {
            if ( property_exists( $response, 'statusCode' ) && 401 === $response->statusCode ) {
                return true;
            }
            if ( property_exists( $response, 'message' ) && strpos( $response->message, 'authenticate' ) !== false ) {
                return true;
            }
        }

        if ( is_array( $response ) ) {
            if ( isset( $response['statusCode'] ) && 401 === $response['statusCode'] ) {
                return true;
            }
            if ( isset( $response['message'] ) && strpos( $response['message'], 'authenticate' ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call a specific api method.
     *
     * This method automatically refreshes expired tokens and retries the request.
     *
     * @since  1.0.0
     * @param  string $function       The name of the api method.
     * @param  string $method         The method of the api method.
     * @param  string $parameters     The paramethers that are being sent.
     * @param  bool   $token          The login token.
     * @param  bool   $retry_on_auth_failure whether to retry the request if authentication fails.
     * @throws Exception Exception message.
     */
    public function call_method( $function, $method, $parameters = '', $token = null, $retry_on_auth_failure = true ) {
            $url = $this->url . '/' . $function;
            $key = $this->key;

            $debugger = CargusDebug::get_instance();

            $debugger->debug( 'Calling Cargus API method: ' . $function );
            $debugger->debug( 'API URL: ' . $url );
            $debugger->debug( 'API Method: ' . $method );

            $args = array();
            if ( 'LoginUser' === $function ) {
                $args = array(
                    'headers' => array(
                        'Ocp-Apim-Subscription-Key' => $key,
                        'Ocp-Apim-Trace'            => true,
                        'Content-Type'              => 'application/json',
                        'ContentLength'             => strlen( wp_json_encode( $parameters ) ),
                    ),
                );
            } else {
                $args = array(
                    'headers' => array(
                        'Ocp-Apim-Subscription-Key' => $key,
                        'Ocp-Apim-Trace'            => true,
                        'Authorization'             => 'Bearer ' . $token,
                        'Content-Type'              => 'application/json',
                    ),
                );

                if ( 'Awbs/WithgetAwb' === $function && 'POST' === $method ) {
                    $args['headers']['Path'] = 'WP' . substr( $this->get_cargus_get_woocommerce_version(), 0, 3 );
                }
            }

            switch ( $method ) {
                case 'POST':
                    if ( ! empty( $parameters ) ) {
                        $args['body'] = wp_json_encode( $parameters );
                    }

                    $response = wp_remote_post( $url, $args );
                    break;

                case 'DELETE':
                    if ( ! empty( $parameters ) ) {
                        $args['body'] = wp_json_encode( $parameters );
                    }
                    $args['method'] = 'DELETE';
                    $response       = wp_remote_request( $url, $args );
                    break;

                case 'PUT':
                    if ( ! empty( $parameters ) ) {
                        $args['body'] = wp_json_encode( $parameters );
                    }
                    $args['method'] = 'PUT';
                    $response       = wp_remote_request( $url, $args );
                    break;

                case 'GET':
                    $query_url = $url;
                    if ( ! empty( $parameters ) ) {
                        $query_url = $url . '?' . http_build_query( $parameters );
                    }
                    $response = wp_remote_get( $query_url, $args );
                    break;

                default:
                    throw new Exception( 'Unknown method used' );
            }

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            $body = json_decode( $response['body'] );

            $debugger->debug( 'API Response Code: ' . $code );
            $debugger->debug( '---------------------------------' );

            // Auto-retry with fresh token if authentication fails.
            
            if ( $retry_on_auth_failure && 'LoginUser' !== $function && $this->is_auth_failure( $body, $code ) ) {
                $debugger->warning( 'Authentication failure detected (HTTP ' . $code . ') - attempting auto token refresh and retry...' );
                
                $new_token = $this->auto_refresh_token( false );
                
                if ( $new_token ) {
                    $debugger->info( 'Retrying API call with fresh token: ' . $function );
                    return $this->call_method( $function, $method, $parameters, $new_token, false );
                } else {
                    $debugger->error( 'Auto token refresh failed - returning authentication error' );
                }
            }

            if ( '200' === $code ) {
                if ( is_array( $body ) && isset( $body['message'] ) ) {
                    return $body['message'];
                } else {
                    return $body;
                }
            } elseif ( '500' === $code ) {
                return array(
                    'statusCode' => 500,
                    'message'    => $body,
                );
            } else {
                return $body;
            }
        }
    }

    /**
     * Call a specific api method for international.
     *
     * This method automatically refreshes expired tokens and retries the request.
     *
     * @since  1.0.0
     * @param  string $function       The name of the api method.
     * @param  string $method         The method of the api method.
     * @param  string $parameters     The paramethers that are being sent.
     * @param  bool   $token          The login token.
     * @param  bool   $retry_on_auth_failure whether to retry the request if authentication fails.
     * @throws Exception Exception message.
     */
    public function call_method_intl( $function, $method, $parameters = '', $token = null, $retry_on_auth_failure = true ) {

            $woocommerce_cargus_export_settings = get_option( 'woocommerce_cargus_export_settings' );
            $debugger                           = CargusDebug::get_instance();

            if ( isset( $woocommerce_cargus_export_settings['webservice'] ) && isset( $woocommerce_cargus_export_settings['apikey'] ) ) {
                $url = $woocommerce_cargus_export_settings['webservice'];
                $key = $woocommerce_cargus_export_settings['apikey'];
                $url = $url . '/' . $function;
            } else {
                $debugger->error( 'Missing webservice or apikey in WooCommerce Cargus export settings' );
                return;
            }

            $debugger = CargusDebug::get_instance();

            $debugger->debug( 'International API URL: ' . $url );
            $debugger->debug( 'Calling Cargus API url: ' . $url );
            $debugger->debug( 'Calling Cargus API method: ' . $function );
            $debugger->debug( 'API URL: ' . $url );
            $debugger->debug( 'API Method: ' . $method );

            $args = array();

            if ( 'LoginUser' === $function ) {
                $args = array(
                    'headers' => array(
                        'Ocp-Apim-Subscription-Key' => $key,
                        'Ocp-Apim-Trace'            => true,
                        'Content-Type'              => 'application/json',
                        'ContentLength'             => strlen( wp_json_encode( $parameters ) ),
                    ),
                );
            } else {
                $args = array(
                    'headers' => array(
                        'Ocp-Apim-Subscription-Key' => $key,
                        'Ocp-Apim-Trace'            => true,
                        'Authorization'             => 'Bearer ' . $token,
                        'Content-Type'              => 'application/json',
                    ),
                );

                if ( 'Awbs/WithgetAwb' === $function && 'POST' === $method ) {
                    $args['headers']['Path'] = 'WP' . substr( $this->get_cargus_get_woocommerce_version(), 0, 3 );
                }
            }

            switch ( $method ) {
                case 'POST':
                    if ( ! empty( $parameters ) ) {
                        $args['body'] = wp_json_encode( $parameters );
                    }

                    $response = wp_remote_post( $url, $args );
                    break;

                case 'DELETE':
                    if ( ! empty( $parameters ) ) {
                        $args['body'] = wp_json_encode( $parameters );
                    }
                    $args['method'] = 'DELETE';
                    $response       = wp_remote_request( $url, $args );
                    break;

                case 'PUT':
                    if ( ! empty( $parameters ) ) {
                        $args['body'] = wp_json_encode( $parameters );
                    }
                    $args['method'] = 'PUT';
                    $response       = wp_remote_request( $url, $args );
                    break;

                case 'GET':
                    $query_url = $url;
                    if ( ! empty( $parameters ) ) {
                        $query_url = $url . '?' . http_build_query( $parameters );
                    }
                    $response = wp_remote_get( $query_url, $args );
                    break;

                default:
                    throw new Exception( 'Unknown method used' );
            }

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            $body = json_decode( $response['body'] );
            $debugger->debug( 'API Response Code: ' . $code );
            $debugger->debug( '---------------------------------' );

            // Auto-retry with fresh token if authentication fails.
            
            if ( $retry_on_auth_failure && 'LoginUser' !== $function && $this->is_auth_failure( $body, $code ) ) {
                $debugger->warning( 'International API authentication failure detected (HTTP ' . $code . ') - attempting auto token refresh and retry...' );
                
                $new_token = $this->auto_refresh_token( true );
                
                if ( $new_token ) {
                    $debugger->info( 'Retrying international API call with fresh token: ' . $function );
                    return $this->call_method_intl( $function, $method, $parameters, $new_token, false );
                } else {
                    $debugger->error( 'Auto token refresh failed for international API - returning authentication error' );
                }
            }

            if ( 200 === $code ) {
                if ( is_array( $body ) && isset( $body['message'] ) ) {
                    return $body['message'];
                } else {
                    return $body;
                }
            } elseif ( 500 === $code ) {
                return array(
                    'statusCode' => 500,
                    'message'    => $body,
                );
            } else {
                return $body;
            }
        }
    }

        /**
         * Get the installed woocommerce version.
         *
         * @since    1.0.0
         */
        private function get_cargus_get_woocommerce_version() {
            // If get_plugins() isn't available, require it.
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            // Create the plugins folder and file variables.
            $plugin_folder = get_plugins( '/woocommerce' );
            $plugin_file   = 'woocommerce.php';

            // If the plugin version number is set, return it.
            if ( isset( $plugin_folder[ $plugin_file ]['Version'] ) ) {
                return $plugin_folder[ $plugin_file ]['Version'];
            } else {
                // Otherwise return null.
                return null;
            }
        }

        /**
         * Call cargus Login endpoint.
         *
         * @param array $fields   The API login token.
         * @param bool  $is_export Whether to generate an export token.
         *
         * @return string The generated token.
         * @throws Exception
         */
        public function generate_token( array $fields = array(), bool $is_export = false ) {
            $debugger = CargusDebug::get_instance();

            // Determine the appropriate token option key and method.
            $token_option_key = $is_export ? 'cargus_export_login_token' : 'cargus_login_token';
            $token            = $is_export ? $this->call_method_intl( 'LoginUser', 'POST', $fields ) : $this->call_method( 'LoginUser', 'POST', $fields );

            if ( 'error' !== $token && ! is_object( $token ) && ! is_array( $token ) ) {
                if ( $is_export ) {
                    $this->export_token = $token;
                } else {
                    $this->token = $token;
                }

                // Save the token in the wp options.
                update_option( $token_option_key, $token, false );

                // Save the token in the class instance.
                if ( self::ensure_session_active() && isset( $_SESSION['cargus_api_instance'] ) ) {
                    $instance = unserialize( $_SESSION['cargus_api_instance'] );
                    if ( $is_export ) {
                        $instance->export_token = $token;
                    } else {
                        $instance->token = $token;
                    }
                    $_SESSION['cargus_api_instance'] = serialize( $instance );
                    // Close the session to avoid REST API/loopback interference
                    self::close_session_safely();
                }

                $debugger->debug( 'New ' . ( $is_export ? 'export ' : '' ) . 'token generated and saved to option: ' . $token_option_key );
            } else {
                $debugger->error( 'Error generating new ' . ( $is_export ? 'export ' : '' ) . 'token: ' . wp_json_encode( $token ) );
            }

            return $is_export ? $this->export_token : $this->token;
        }

        /**
         * Helper function to fetch and cache data from the API.
         *
         * @param string $endpoint The API endpoint to call.
         * @param string $transient_key The key for caching the data.
         * @return mixed The JSON response from the API.
         */
        private function fetch_data( $endpoint, $transient_key, $new_data = false ) {
            $debugger        = CargusDebug::get_instance();
            $json            = get_transient( $transient_key );
            $now             = time();
            $next_cron       = wp_next_scheduled( 'cargus_refresh_login_token_action' );
            $expiration_time = $next_cron ? max( $next_cron - $now, 60 ) + 60 : strtotime( 'tomorrow midnight' ) - $now;

            // Check if data needs to be fetched from the API.
            if ( empty( $json ) || 'Failed to authenticate!' === $json || $now > ( get_option( 'last_cargus_refresh_login_token', 0 ) + $expiration_time ) || $new_data ) {
                $debugger->debug( "Fetching new data for $endpoint." );
                $json = $this->call_method( $endpoint, 'GET', array(), get_option( 'cargus_login_token' ) );

                // VALIDATE: Don't cache authentication errors or invalid responses
                if ( ! empty( $json ) && $json !== "Failed to authenticate!" && is_array( $json ) ) {
                    set_transient( $transient_key, $json, $expiration_time );
                    update_option( 'last_cargus_refresh_login_token', $now );
                    $debugger->debug( "Data fetched and cached for $endpoint." );
                } else {
                    // API failed or returned error - try to use existing cached data even if expired
                    $debugger->debug( "API call failed for $endpoint, attempting to use existing cached data." );

                    // Get existing transient data
                    $existing_data = get_transient( $transient_key );
                    if ( $existing_data && $existing_data !== "Failed to authenticate!" && is_array( $existing_data ) ) {
                        $json = $existing_data;
                        $debugger->debug( "Using existing cached data for $endpoint as fallback." );
                    } else {
                        // No valid cached data available, return empty array
                        $json = array();
                        $debugger->debug( "No valid cached data available for $endpoint, returning empty array." );
                    }
                }
            } else {
                $debugger->debug( "Cached data is still valid for $endpoint." );
            }

            $debugger->debug( "$endpoint JSON: " . wp_json_encode( $json ) );
            return $json;
        }

        /**
         * Fetch pickup locations from the API.
         *
         * @return mixed The JSON response containing pickup locations.
         */
        public function get_pickup_locations( $new_data = false ) {
            return $this->fetch_data( 'PickupLocations', 'cached_pickup_locations', $new_data );
        }

        /**
         * Fetch price tables from the API.
         *
         * @return mixed The JSON response containing price tables.
         */
        public function get_price_tables( $new_data = false ) {
            return $this->fetch_data( 'PriceTables', 'cached_price_tables', $new_data );
        }

        /**
         * Call cargus shipping calculation api endpoint.
         *
         * @param array $fields The shipping calculation fields.
         */
        public function get_shipping_calulation( $fields ) {
            $json = array();
            $json = $this->call_method( 'ShippingCalculation', 'POST', $fields, get_option( 'cargus_login_token' ) );
            return $json;
        }

        /**
         * Call cargus shipping calculation api endpoint for export.
         *
         * @param array $fields The shipping calculation fields.
         *
         * @throws Exception
         */
        public function get_export_shipping_calculation( $fields ) {
            return $this->call_method( 'pricing/calculate', 'POST', $fields, get_option( 'cargus_login_token' ) );
        }


        /**
         * Call cargus pudo points api endpoint.
         */
        public function get_pudo_points() {
            $json = array();
            $json = $this->call_method( 'PudoPoints', 'GET', array(), get_option( 'cargus_login_token' ) );

            return $json;
        }

        /**
         * Call cargus Streets api endpoint.
         *
         * @param int $locality_id The locality id.
         */
        public function get_streets( $locality_id ) {
            // get streets.
            if ( is_null( $locality_id ) ) {
                return array();
            }

            $json = array();
            // get api json.
            // Streets?locality_id=97243.
            $json = $this->call_method( 'Streets?localityId=' . $locality_id, 'GET', array(), get_option( 'cargus_login_token' ) );

            if ( ! $json || 'Failed to authenticate!' === $json ) {
                // get old cache if exists.
                $cache = new Cargus_Cache();

                $temp = $cache->get_cached_file( 'str' . $locality_id, true );
                if ( false !== $temp ) {
                    // return the old data.
                    $json = json_decode( $temp, true );
                }
            }

            return $json;
        }

        /**
         * Call cargus localities api endpoint.
         *
         * @param int $county_id The conty id.
         */
        public function get_localities( $county_id ) {
            // get streets.
            if ( is_null( $county_id ) ) {
                return array();
            }

            $json = array();
            // get api json.
            // Localities?countryId=1&countyId=97243.
            $json = $this->call_method( 'Localities?countryId=1&countyId=' . $county_id, 'GET', array(), get_option( 'cargus_login_token' ) );

            if ( ! $json || 'Failed to authenticate!' === $json ) {
                // get old cache if exists.
                $cache = new Cargus_Cache();

                $temp = $cache->get_cached_file( 'localities' . $county_id, true );
                if ( false !== $temp ) {
                    // return the old data.
                    $json = json_decode( $temp, true );
                }
            }

            return $json;
        }

        /**
         * Call cargus counties api endpoint.
         */
        public function get_counties() {
            $json = array();

            // get api json.
            // Counties?countryId=1.
            $json = $this->call_method( 'Counties?countryId=1', 'GET', array(), get_option( 'cargus_login_token' ) );
            if ( ! $json || 'Failed to authenticate!' == $json ) {
                // get old cache if exists.
                $cache = new Cargus_Cache();
                $temp  = $cache->get_cached_file( 'counties', true );
                if ( false !== $temp ) {
                    // return the old data.
                    $json = json_decode( $temp, true );
                }
            }

            return $json;
        }

        /**
         * Call cargus counties api endpoint.
         */
        public function get_countries() {
            $json = array();

            // get api json.
            // Counties?countryId=1.
            $json = $this->call_method( 'intl-countries', 'GET', array(), get_option( 'cargus_login_token' ) );
            if ( ! $json || 'Failed to authenticate!' === $json ) {
                // get old cache if exists.
                $cache = new Cargus_Cache();
                $temp  = $cache->get_cached_file( 'countries', true );
                if ( false !== $temp ) {
                    // return the old data.
                    $json = json_decode( $temp, true );
                }
            }

            return $json;
        }

        /**
         * Call cargus pickup locations api endpoint.
         */
        public function get_shipment_status( $awb ) {
            $json = $this->call_method( 'AwbStatus/GetAwbSyncStatusByBarCode?barCode=' . $awb, 'GET', array(), get_option( 'cargus_login_token' ) );

            return $json;
        }

        /**
         * Call cargus localities api endpoint for Bulgaria.
         */
        public function get_localities_bulgaria() {

            $json = array();
            // get api json.
            // Localities?countryId=1&countyId=97243.
            $debugger = CargusDebug::get_instance();

            $json = $this->call_method_intl( 'cities?countryId=2', 'GET', array(), $this->token, true );
            $debugger->debug( 'Localities Bulgaria JSON: ' . wp_json_encode( $json ) );

            if ( ! $json || 'Failed to authenticate!' === $json ) {
                // get old cache if exists.
                $cache = new Cargus_Cache();

                $temp = $cache->get_cached_file( 'localities_bulgaria', true );
                if ( false !== $temp ) {
                    // return the old data.
                    $json = json_decode( $temp, true );
                }
            }

            return $json;
        }
    }
}
