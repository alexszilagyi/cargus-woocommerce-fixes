<?php
/**
 * Add woocommerce cargus shipping method.
 *
 * @link       https://cargus.ro/
 * @since      1.0.0
 *
 * @package    Cargus
 * @subpackage Cargus/admin
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! class_exists( 'Cargus_Export_Shipping_Method' ) ) {
	/**
	 * Add woocommerce cargus shipping method.
	 *
	 * @link       https://cargus.ro/
	 * @since      1.0.0
	 *
	 * @package    Cargus
	 * @subpackage Cargus/admin
	 */
	#[AllowDynamicProperties]
	class Cargus_Export_Shipping_Method extends WC_Shipping_Method {

		/**
		 * The api integration part.
		 *
		 * @since    1.0.0
		 * @access   public
		 * @var      string    $api    The cargus api object.
		 */
		public $api;

		/**
		 * Initialize the class and set its properties.
		 *
		 * @param Int $instance_id The woocommerce shipping method instance id.
		 * @since    1.0.0
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cargus_export';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'Livrare Cargus Export', 'cargus' );
			$this->method_description = __( 'Livrare la domiciliu in afara tarii Cargus.', 'cargus' );
			$this->supports           = array(
				'shipping-zones',
				'settings',
			);

			$this->load_dependencies();
			$this->init();

			$this->title                    = isset( $this->settings['title'] ) ? $this->settings['title'] : null;
			$this->webservice               = isset( $this->settings['webservice'] ) ? $this->settings['webservice'] : null;
			$this->apikey                   = isset( $this->settings['apikey'] ) ? $this->settings['apikey'] : null;
			$this->username                 = isset( $this->settings['username'] ) ? $this->settings['username'] : null;
			$this->password                 = isset( $this->settings['password'] ) ? $this->settings['password'] : null;
			$this->poland_fixed             = isset( $this->settings['poland_fixed'] ) ? $this->settings['poland_fixed'] : null;
			$this->bulgaria_fixed           = isset( $this->settings['bulgaria_fixed'] ) ? $this->settings['bulgaria_fixed'] : null;
			$this->nomenclator_intl         = isset( $this->settings['intl_nomenclator'] ) ? $this->settings['intl_nomenclator'] : null;
			$this->greece_fixed             = isset( $this->settings['greece_fixed'] ) ? $this->settings['greece_fixed'] : null;
			$this->greece_shipping_cost_tax = isset( $this->settings['greece_shipping_cost_tax'] ) ? $this->settings['greece_shipping_cost_tax'] : null;
			$this->order_status_create_awb  = isset( $this->settings['order_status_create_awb'] ) ? $this->settings['order_status_create_awb'] : null;
			$this->order_status_remove_awb  = isset( $this->settings['order_status_remove_awb'] ) ? $this->settings['order_status_remove_awb'] : null;

			$cargus_export_login_token = get_option( 'cargus_export_login_token' );
			if ( $cargus_export_login_token && ! is_object( $cargus_export_login_token ) && ! is_array( $cargus_export_login_token ) && '' !== $cargus_export_login_token
				&& property_exists( $this, 'apikey' ) && '' !== $this->apikey
			) {
				$this->token = $cargus_export_login_token;
				$this->init_extra_fields();
			}
		}

		/**
		 * Include the cargus dependencies.
		 *
		 * @since    1.0.0
		 */
		public function load_dependencies() {

			/**
			 * The class responsible for making the php api call.
			 */
			require_once plugin_dir_path( __FILE__ ) . 'class-cargus-api.php';

			/**
			 * The class responsible for caching.
			 */
			require_once plugin_dir_path( __FILE__ ) . 'class-cargus-cache.php';
		}

		/**
		 * Initialize the shipping fields and settings.
		 *
		 * @since    1.0.0
		 */
		public function init() {
			$this->init_form_fields();
			$this->init_settings();

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Process the admin options.
		 *
		 *  @since    1.0.0
		 */
		public function process_admin_options() {

			$post_data = $this->get_post_data();
			if ( ! empty( $post_data['woocommerce_cargus_export_webservice'] ) && ! empty( $post_data['woocommerce_cargus_export_apikey'] ) ) {

				$this->api = Cargus_Api::get_instance();
				$this->api->set_export_url( $post_data['woocommerce_cargus_export_webservice'] );

				$fields = array(
					'UserName' => $post_data['woocommerce_cargus_export_username'],
					'Password' => $post_data['woocommerce_cargus_export_password'],
				);

				$this->token = $this->api->generate_token( $fields, true );

				if ( $this->token && '' !== $this->token && ! is_object( $this->token ) && ! is_array( $this->token ) ) {
					update_option( 'cargus_export_login_token', $this->token, false );
					$this->init_extra_fields();
				} elseif ( is_array( $this->token ) && ( isset( $this->token['Error'] ) || ( isset( $this->token['statusCode'] ) && 500 === $this->token['statusCode'] ) ) ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_notice_username_password' ) );
					update_option( 'cargus_export_login_token', false, false );
				} elseif ( is_object( $this->token ) && ( property_exists( $this->token, 'Error' ) || ( property_exists( $this->token, 'statusCode' ) && 500 === $this->token->statusCode ) ) ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_notice_username_password' ) );
					update_option( 'cargus_export_login_token', false, false );
				} elseif ( is_array( $this->token ) && isset( $this->token['statusCode'] ) && 401 === $this->token['statusCode'] ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_notice_apikey' ) );
					update_option( 'cargus_export_login_token', false, false );
				} elseif ( is_object( $this->token ) && property_exists( $this->token, 'statusCode' ) && 401 === $this->token->statusCode ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_notice_apikey' ) );
					update_option( 'cargus_export_login_token', false, false );
				}
			}

			parent::process_admin_options();
		}

		/**
		 * Initialize the admin form fields.
		 *
		 *  @since    1.0.0
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'title'      => array(
					'title'   => __( 'Titlu', 'cargus' ),
					'type'    => 'text',
					'default' => __( 'Livrare la domiciliu in afara tarii Cargus', 'cargus' ),
				),
				'webservice' => array(
					'title'   => __( 'URL Webservice', 'cargus' ),
					'type'    => 'text',
					'default' => __( 'https://urgentcargusapitest.azure-api.net/v4/api', 'cargus' ),
				),
				'apikey'     => array(
					'title' => __( 'API Key', 'cargus' ),
					'type'  => 'text',
				),
				'username'   => array(
					'title'       => __( 'Nume utilizator', 'cargus' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Username cont platforma WebExpress.', 'cargus' ),
				),
				'password'   => array(
					'title'       => __( 'Parola', 'cargus' ),
					'type'        => 'password',
					'desc_tip'    => true,
					'description' => __( 'Parola cont platforma WebExpress.', 'cargus' ),
				),
			);
		}

		/**
		 * Initialize the admin extra form fields.
		 *
		 *  @since    1.0.0
		 */
		public function init_extra_fields() {
			$extra_fields = array(
				'intl_nomenclator'                     => array(
					'title'   => __( 'Nomenclator', 'cargus' ),
					'label'   => __( 'Activ', 'cargus' ),
					'type'    => 'checkbox',
					'default' => 'yes',
				),
				'bulgaria_fixed'                       => array(
					'title'       => __( 'Cost fix transport Bulgaria', 'cargus' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Setare pret fix platit de client pentru costul de transportal al comenzilor care pleaca spre Bulgaria. Pretul va fi setat moneda nationala: BGN.', 'cargus' ),
					'default'     => '10',
				),
				'greece_fixed'                         => array(
					'title'       => __( 'Cost fix transport Grecia', 'cargus' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'Setare pret fix platit de client pentru costul de transportal al comenzilor care pleaca spre Grecia.  Pretul va fi setat moneda nationala: EUR.', 'cargus' ),
					'default'     => '10',
				),
			);

			$this->form_fields += apply_filters( 'cargus_shipping_method_extra_fields', $extra_fields );
		}

		/**
		 * Calculate the shipping cost.
		 *
		 * @since    1.0.0
		 * @param Array $package The shipping package data array.
		 */
		public function calculate_shipping( $package = array() ) {
			$calculated_cost = $this->get_shipping_cost( $this, $package );

			if ( ! is_null( $calculated_cost ) ) {
				if ( 0 === $calculated_cost ) {
					$this->title .= apply_filters( 'cargus_export_shipping_method_title_free', ' - Gratuit' );
				}

				$rate = array(
					'id'       => $this->id,
					'label'    => $this->title,
					'cost'     => $calculated_cost,
					'calc_tax' => 'per_order',
				);

				$this->add_rate( $rate );
			}
		}

		/**
		 * Calculates the shipping cost for a given package based on Cargus options and WooCommerce settings.
		 *
		 * This function determines the shipping cost by evaluating various factors such as payment method,
		 * free shipping coupons, total cart value, and destination country. It supports weight-based and
		 * fixed shipping costs for specific countries.
		 *
		 * @param object $cargus_options An object containing Cargus-specific options, including API keys and shipping settings.
		 * @param array  $package        An associative array representing the package details, including contents and destination.
		 *
		 * @return float|null The calculated shipping cost or null if an error occurs.
		 */
		private function get_shipping_cost( $cargus_options, $package ) {
			try {

				$cargus                    = Cargus_Api::get_instance();
				$cargus_export_login_token = get_option( 'cargus_export_login_token' );
				if ( $cargus_export_login_token && ! is_object( $cargus_export_login_token ) && ! is_array( $cargus_export_login_token ) &&
					property_exists( $cargus_options, 'apikey' ) && '' !== $cargus_options->apikey
				) {
					// Get Payemnt method.
					//phpcs:disable
					$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
					if ( isset( $_POST ) && isset( $_POST['payment_method'] ) && isset( $available_payment_gateways[ $_POST['payment_method'] ] ) ) {
						$current_payment_gateway = $available_payment_gateways[ $_POST['payment_method'] ];
					} elseif ( isset( WC()->session->chosen_payment_method ) && isset( $available_payment_gateways[ WC()->session->chosen_payment_method ] ) ) {
						$current_payment_gateway = $available_payment_gateways[ WC()->session->chosen_payment_method ];
					} elseif ( isset( $available_payment_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
						$current_payment_gateway = $available_payment_gateways[ get_option( 'woocommerce_default_gateway' ) ];
					} else {
						$current_payment_gateway = current( $available_payment_gateways );
					}
					// Check free shipping coupon.
					//phpcs:enable
					$coupons = WC()->cart->get_coupons();
					if ( $coupons ) {
						foreach ( $coupons as $code => $coupon ) {
							if ( $coupon->is_valid() && $coupon->get_free_shipping() ) {
								return 0;
							}
						}
					}

					// Get total.
					$total = WC()->cart->cart_contents_total + array_sum( WC()->cart->get_cart_contents_taxes() );

					// Get ramburs.
					$ramburs = $total;
					if ( 'cod' !== $current_payment_gateway->id ) {
						$ramburs = 0;
					}

					// Get weight.
					$weight = 0.0;
					// Get the dimension unit set in Woocommerce.
					$dimension_unit = get_option( 'woocommerce_dimension_unit' );
					$max_volume     = $max_width = $max_length = $max_height = $rate = 0;
					// Calculate the rate to be applied for cm.
					if ( $dimension_unit == 'mm' ) {
						$rate = 0.1;
					} elseif ( $dimension_unit == 'cm' ) {
						$rate = 1;
					} elseif ( $dimension_unit == 'm' ) {
						$rate = 100;
					}

					foreach ( $package['contents'] as $item_id => $values ) {
						$_product = $values['data'];
						if ( $_product->get_weight() === '0' || $_product->get_weight() === '' ) {
							$product_weight = 0.1 * floatval( $values['quantity'] );
						} else {
							$product_weight = floatval( wc_get_weight( $_product->get_weight(), 'kg', get_option( 'woocommerce_weight_unit' ) ) ) * floatval( $values['quantity'] );
						}
						$weight += $product_weight;
					}

					$weight = ceil( $weight );
					if ( $weight < 1 ) {
						$weight = 1;
					}

					// UC check fixed.
					$shipping_cost = 0;
					if ( 'BG' === $package['destination']['country'] ) {
						if ( '' !== $cargus_options->bulgaria_fixed && is_numeric( $cargus_options->bulgaria_fixed ) ) {
							$shipping_cost = $cargus_options->bulgaria_fixed;
						}
					} elseif ( 'PL' === $package['destination']['country'] ) {
						if ( '' !== $cargus_options->poland_fixed && is_numeric( $cargus_options->poland_fixed ) ) {
							$shipping_cost = $cargus_options->poland_fixed;
						}
					} elseif ( 'GR' === $package['destination']['country'] ) {
						if ( '' !== $cargus_options->greece_fixed && is_numeric( $cargus_options->greece_fixed ) ) {
							$shipping_cost = $cargus_options->greece_fixed;
						}
					}

					$discount_percent = apply_filters( 'cargus_export_add_shipping_discount', 0 );

					if ( 0 !== $shipping_cost && is_int( $discount_percent ) ) {
						$shipping_cost -= ( $discount_percent / 100 * $shipping_cost );
						return $shipping_cost;
					}
				}
			} catch ( Exception $ex ) {
				return null;
			}
		}

		/**
		 * Admin notice for invalid username or password.
		 *
		 *  @since    1.0.0
		 */
		public function cargus_admin_notice_username_password() {
			echo wp_kses_post( '<div class="notice notice-error"><p>' . __( 'Username sau parolă greșită. Reâncarcă pagina și încearcă iar.', 'cargus' ) . '</p></div>' );
		}

		/**
		 * Admin notice for invalid apikey.
		 *
		 *  @since    1.0.0
		 */
		public function cargus_admin_notice_apikey() {
			echo wp_kses_post( '<div class="notice notice-error"><p>' . __( 'Acces refuzat din cauza cheii de api invalidă. Asigurați-vă că furnizați o cheie validă pentru un abonament activ. Reâncarcă pagina și încearcă iar.', 'cargus' ) . '</p></div>' );
		}

		/**
		 * Return admin options as a html string.
		 *
		 * @return string
		 */
		public function get_admin_options_html() {
			if ( $this->instance_id ) {
				$settings_html = '<table class="form-table">' . $this->generate_settings_html( $this->get_instance_form_fields(), false ) . '</table>'; // WPCS: XSS ok.
			} else {
				$settings_html = '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>'; // WPCS: XSS ok.
			}

			return '<div class="wc-shipping-zone-method-fields">' . $settings_html . '</div>';
		}
	}
}
