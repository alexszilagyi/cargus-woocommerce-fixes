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
if ( ! class_exists( 'Cargus_Shipping_Method' ) ) {
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
	class Cargus_Shipping_Method extends WC_Shipping_Method {

		/**
		 * The api integration part.
		 *
		 * @since    1.0.0
		 * @access   public
		 * @var      string    $api    The cargus api object.
		 */
		public $api;

		// Constants for Multi-piece service configuration.
		private const MULTIPIECE_SERVICE_ID = '39';
		private const WEIGHT_THRESHOLD_1 = 31;
		private const WEIGHT_THRESHOLD_2 = 50;
		private const MAX_WEIGHT_1 = 465;
		private const MAX_WEIGHT_2 = 750;

		/**
		 * Initialize the class and set its properties.
		 *
		 * @param Int $instance_id The woocommerce shipping method instance id.
		 * @since    1.0.0
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'cargus';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'Livrare Cargus', 'cargus' );
			$this->method_description = __( 'Livrare la domiciliu Cargus.', 'cargus' );
			$this->supports           = array(
				'shipping-zones',
				'settings',
			);

			$this->load_dependencies();
			$this->init();

			$this->title                        = isset( $this->settings['title'] ) ? $this->settings['title'] : null;
			$this->webservice                   = isset( $this->settings['webservice'] ) ? $this->settings['webservice'] : null;
			$this->apikey                       = isset( $this->settings['apikey'] ) ? $this->settings['apikey'] : null;
			$this->username                     = isset( $this->settings['username'] ) ? $this->settings['username'] : null;
			$this->password                     = isset( $this->settings['password'] ) ? $this->settings['password'] : null;
			$this->pickup                       = isset( $this->settings['pickup'] ) ? $this->settings['pickup'] : null;
			$this->priceplan                    = isset( $this->settings['priceplan'] ) ? $this->settings['priceplan'] : null;
			$this->insurance                    = isset( $this->settings['insurance'] ) ? $this->settings['insurance'] : null;
			$this->open                         = isset( $this->settings['open'] ) ? $this->settings['open'] : null;
			$this->email_banner                 = isset( $this->settings['email-banner'] ) ? $this->settings['email-banner'] : null;
			$this->locations_select             = isset( $this->settings['locations-select'] ) ? $this->settings['locations-select'] : null;
			$this->street_select                = isset( $this->settings['street-select'] ) ? $this->settings['street-select'] : null;
			$this->return                       = isset( $this->settings['return-awb'] ) ? $this->settings['return-awb'] : null;
			$this->return_awb_print             = isset( $this->settings['return-awb-print'] ) ? $this->settings['return-awb-print'] : null;
			$this->return_awb_validity          = isset( $this->settings['awb-validity'] ) ? $this->settings['awb-validity'] : null;
			$this->repayment                    = isset( $this->settings['repayment'] ) ? $this->settings['repayment'] : null;
			$this->payer                        = isset( $this->settings['payer'] ) ? $this->settings['payer'] : null;
			$this->type                         = isset( $this->settings['type'] ) ? $this->settings['type'] : null;
			$this->free                         = isset( $this->settings['free'] ) ? $this->settings['free'] : null;
			$this->fixed                        = isset( $this->settings['fixed'] ) ? $this->settings['fixed'] : null;
			$this->buc_fixed                    = isset( $this->settings['buc_fixed'] ) ? $this->settings['buc_fixed'] : null;
			$this->weight_based_shipping_enable = isset( $this->settings['weight_based_shipping_enable'] ) ? $this->settings['weight_based_shipping_enable'] : null;
			$this->weight_based_shipping        = isset( $this->settings['weight_based_shipping'] ) ? $this->settings['weight_based_shipping'] : null;
			$this->shipping_cost_tax            = isset( $this->settings['shipping_cost_tax'] ) ? $this->settings['shipping_cost_tax'] : null;
			$this->height                       = isset( $this->settings['height'] ) ? $this->settings['height'] : null;
			$this->width                        = isset( $this->settings['width'] ) ? $this->settings['width'] : null;
			$this->length                       = isset( $this->settings['length'] ) ? $this->settings['length'] : null;
			$this->service_id                   = isset( $this->settings['service_id'] ) ? $this->settings['service_id'] : null;
			$this->parcel_contents              = isset( $this->settings['parcel_contents'] ) ? $this->settings['parcel_contents'] : null;
			$this->print_format                 = isset( $this->settings['print_format'] ) ? $this->settings['print_format'] : null;
			$this->print_twice                  = isset( $this->settings['print_twice'] ) ? $this->settings['print_twice'] : null;
			$this->order_status_create_awb      = isset( $this->settings['order_status_create_awb'] ) ? $this->settings['order_status_create_awb'] : null;
			$this->order_status_remove_awb      = isset( $this->settings['order_status_remove_awb'] ) ? $this->settings['order_status_remove_awb'] : null;
			$this->debug_cargus                 = isset( $this->settings['debug_cargus'] ) ? $this->settings['debug_cargus'] : null;

			$cargus_token = get_option( 'cargus_login_token' );
			if ( $cargus_token && ! is_object( $cargus_token ) && ! is_array( $cargus_token ) && '' !== $cargus_token
				&& property_exists( $this, 'apikey' ) && '' !== $this->apikey
			) {
				$this->token = $cargus_token;
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
			/**
			 * The class responsible for debugging.
			 */
			require_once plugin_dir_path( __FILE__ ) . 'class-cargus-debug.php';
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
			if ( ! empty( $post_data['woocommerce_cargus_webservice'] ) && ! empty( $post_data['woocommerce_cargus_apikey'] ) ) {
				$this->api = Cargus_Api::get_instance( $post_data['woocommerce_cargus_webservice'], $post_data['woocommerce_cargus_apikey'] );

				$fields = array(
					'UserName' => $post_data['woocommerce_cargus_username'],
					'Password' => $post_data['woocommerce_cargus_password'],
				);

				$this->token = $this->api->generate_token( $fields, false );

				if ( $this->token && '' !== $this->token && ! is_object( $this->token ) && ! is_array( $this->token ) ) {
					update_option( 'cargus_login_token', $this->token, false );
					$this->init_extra_fields();
				} elseif ( is_array( $this->token ) && ( isset( $this->token['Error'] ) || ( isset( $this->token['statusCode'] ) && 500 === $this->token['statusCode'] ) ) ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_notice_username_password' ) );
					update_option( 'cargus_login_token', false, false );
				} elseif ( is_object( $this->token ) && ( property_exists( $this->token, 'Error' ) || ( property_exists( $this->token, 'statusCode' ) && 500 === $this->token->statusCode ) ) ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_notice_username_password' ) );
					update_option( 'cargus_login_token', false, false );
				} elseif ( is_array( $this->token ) && isset( $this->token['statusCode'] ) && 401 === $this->token['statusCode'] ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_notice_apikey' ) );
					update_option( 'cargus_login_token', false, false );
				} elseif ( is_object( $this->token ) && property_exists( $this->token, 'statusCode' ) && 401 === $this->token->statusCode ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_notice_apikey' ) );
					update_option( 'cargus_login_token', false, false );
				}

				if ( ( isset( $post_data['woocommerce_cargus_height'] ) && '' === $post_data['woocommerce_cargus_height'] ) || ( isset( $post_data['woocommerce_cargus_width'] ) && '' === $post_data['woocommerce_cargus_width'] ) || ( isset( $post_data['woocommerce_cargus_length'] ) && '' === $post_data['woocommerce_cargus_length'] ) ) {
					add_action( 'admin_notices', array( $this, 'cargus_admin_dimensions_notice' ) );
				}
			}

			parent::process_admin_options();

			// Save custom repeater field.
			$custom_repeater_field = $this->get_field_key( 'weight_based_shipping' );
			if ( isset( $_POST[ $custom_repeater_field ] ) ) {
				$value = wc_clean( wp_unslash( serialize( $_POST[ $custom_repeater_field ] ) ) );
				update_option( $custom_repeater_field, $value );
			}

			// get the new cargus pickup locations and price tables.
			// $this->api is only set above when the webservice URL and API key
			// are both in the submitted form; saving the method with either
			// empty (or from a screen that does not post them) was a fatal
			// "Call to a member function get_pickup_locations() on null".
			if ( ! ( $this->api instanceof Cargus_Api ) ) {
				$this->api = Cargus_Api::get_instance();
			}

			if ( $this->api instanceof Cargus_Api ) {
				$this->api->get_pickup_locations( true );
				$this->api->get_price_tables( true );
			}
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
					'default' => __( 'Cargus Livrare la domiciliu', 'cargus' ),
				),
				'webservice' => array(
					'title'   => __( 'URL Webservice', 'cargus' ),
					'type'    => 'text',
					'default' => __( 'https://urgentcargus.azure-api.net/api', 'cargus' ),
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
			// obtine lista punctelor de ridicare.
			global $pagenow;
			if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) { //phpcs:ignore
				$this->api = Cargus_Api::get_instance();

				// obtine lista puncte de ridicare.
				$temp = $this->api->get_pickup_locations();

				$pickups = array();
				if ( is_array( $temp ) ) {
					foreach ( $temp as $location ) {
						$pickups[ $location->LocationId ] = $location->Name; //phpcs:ignore
					}
				}

				if ( count( $pickups ) > 1 ) {
					unset( $pickups['0'] );
				}

				// obtine lista planurilor tarifare.
				$temp = $this->api->get_price_tables();

				$prices = array();
				if ( is_array( $temp ) ) {
					foreach ( $temp as $price ) {
						$prices[ $price->PriceTableId ] = empty( $price->Name ) ? $price->PriceTableId : $price->Name; //phpcs:ignore
					}
				}

				$extra_fields = array(
					'pickup'                       => array(
						'title'   => __( 'Punct de ridicare', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array( null => __( 'Alege punctul de ridicare', 'cargus' ) ) + $pickups,
					),
					'priceplan'                    => array(
						'title'   => __( 'Plan tarifar', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array( null => __( 'Alege planul tarifar', 'cargus' ) ) + $prices,
					),
					'insurance'                    => array(
						'title'   => __( 'Asigurare', 'cargus' ),
						'label'   => __( 'Activ', 'cargus' ),
						'type'    => 'checkbox',
						'default' => 'yes',
					),
					'open'                         => array(
						'title'   => __( 'Deschidere colet', 'cargus' ),
						'label'   => __( 'Activ', 'cargus' ),
						'type'    => 'checkbox',
						'default' => 'yes',
					),
					'repayment'                    => array(
						'title'   => __( 'Mod Incasare ramburs', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							'bank' => __( 'Cont colector', 'cargus' ),
							'cash' => __( 'Numerar', 'cargus' ),
						),
					),
					'payer'                        => array(
						'title'   => __( 'Platitor expeditie', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							'sender'    => __( 'Expeditor', 'cargus' ),
							'recipient' => __( 'Destinatar', 'cargus' ),
						),
					),
					'type'                         => array(
						'title'   => __( 'Tip expeditie', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							'parcel'   => __( 'Colet', 'cargus' ),
							'envelope' => __( 'Plic', 'cargus' ),
						),
					),
					'free'                         => array(
						'title'       => __( 'Plafon transport gratuit', 'cargus' ),
						'type'        => 'text',
						'desc_tip'    => true,
						'description' => __( 'Suma dupa care costul transportului de catre cumparator devine 0.', 'cargus' ),
					),
					'fixed'                        => array(
						'title'       => __( 'Cost fix transport', 'cargus' ),
						'type'        => 'text',
						'desc_tip'    => true,
						'description' => __( 'Setare pret fix platit de client pentru costul de transport.', 'cargus' ),
					),
					'shipping_cost_tax'            => array(
						'title'   => __( 'Tva inclus în costul de transport calculat', 'cargus' ),
						'label'   => __( 'Activ', 'cargus' ),
						'type'    => 'checkbox',
						'default' => 'no',
					),
					'buc_fixed'                    => array(
						'title'       => __( 'Cost fix transport București', 'cargus' ),
						'type'        => 'text',
						'desc_tip'    => true,
						'description' => __( 'Setare pret fix platit de client pentru costul de transport pe aria Bucuresti. Lasat necompletat va preluat pretul fix setat pe toata tara.', 'cargus' ),
					),
					'weight_based_shipping_enable' => array(
						'title'   => __( 'Activeaza costul de transport pe baza greutatii', 'cargus' ),
						'label'   => __( 'Activ', 'cargus' ),
						'type'    => 'checkbox',
						'default' => 'no',
					),
					'weight_based_shipping'        => array(
						'type'        => 'custom_repeater',
						'title'       => __( 'Cost fix transport in functie de greutate', 'cargus' ),
						'desc_tip'    => true,
						'description' => __( 'Setare a mai multe praguri de pret fix de transport in functie de greutate. Pentru fiecare prag se setaza greutatea minima si greutatea maxima.', 'cargus' ),
						'default'     => serialize(
							array(
								array(
									'min-weight' => '0',
									'max-weight' => '10',
									'price'      => '15',
								),
							),
						),
					),
					'height'                       => array(
						'title'       => __( 'Inaltime (cm)', 'cargus' ),
						'type'        => 'number',
						'default'     => 10,
						'desc_tip'    => true,
						'description' => __( 'Inaltime standard colet, se calculeaza automat si poate fi modificata pe fiecare comanda in parte.', 'cargus' ),
					),
					'width'                        => array(
						'title'       => __( 'Latime (cm)', 'cargus' ),
						'type'        => 'number',
						'default'     => 10,
						'desc_tip'    => true,
						'description' => __( 'Latime standard colet, se calculeaza automat si poate fi modificata pe fiecare comanda in parte.', 'cargus' ),
					),
					'length'                       => array(
						'title'       => __( 'Lungime (cm)', 'cargus' ),
						'type'        => 'number',
						'default'     => 10,
						'desc_tip'    => true,
						'description' => __( 'Lungime standard colet, se calculeaza automat si poate fi modificata pe fiecare comanda in parte.', 'cargus' ),
					),
					'service_id'                   => array(
						'title'   => __( 'Id Serviciu', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							'1'  => __( '1 Standard', 'cargus' ),
							'34' => __( '34 Economic Standard', 'cargus' ),
							'39' => __( '39 Multipiece', 'cargus' ),
						),
					),
					'email-banner'                 => array(
						'title'       => __( 'Email banner Cargus', 'cargus' ),
						'label'       => __( 'Activ', 'cargus' ),
						'type'        => 'checkbox',
						'default'     => 'no',
						'desc_tip'    => true,
						'description' => __( 'Adaugare banner cargus in email-urile trimise clientilor despre comanda.', 'cargus' ),
					),
					'locations-select'             => array(
						'title'       => __( 'Camp Oras dropdown', 'cargus' ),
						'description' => __( 'Setare nomenclator orase cargus pe campul de oras.', 'cargus' ),
						'label'       => __( 'Activ', 'cargus' ),
						'type'        => 'checkbox',
						'default'     => 'yes',
						'desc_tip'    => true,
					),
					'street-select'                => array(
						'title'       => __( 'Camp Strada si camp Numar', 'cargus' ),
						'label'       => __( 'Activ', 'cargus' ),
						'type'        => 'checkbox',
						'default'     => 'no',
						'desc_tip'    => true,
						'description' => __( 'Adauga doua noi campuri, unul pentru strada, care va contine nomenclatorul pentru strazi cargus, si unul pentru numar, si ascunde campul pentru adresa.', 'cargus' ),
					),
					'return-awb'                   => array(
						'title'   => __( 'Retur cumparator', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							'0' => __( 'Nu', 'cargus' ),
							'1' => __( 'Cod retur', 'cargus' ),
							'2' => __( 'Pre-tiparit', 'cargus' ),
						),
					),
					'awb-validity'                 => array(
						'title'             => __( 'Validitate AWB', 'cargus' ),
						'type'              => 'number',
						'default'           => 30,
						'custom_attributes' => array(
							'min' => 0,
							'max' => 180,
						),
					),
					'return-awb-print'             => array(
						'title'   => __( 'Printare AWB retur', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							'0' => __( 'Nu se va printa AWB-ul de retur', 'cargus' ),
							'1' => __( 'Se vor printa AWB tur + AWB retur standard', 'cargus' ),
							'2' => __( 'Se vor printa AWB tur + AWB retur cu instructiuni', 'cargus' ),
							'3' => __( 'Se vor printa doar AWB retur standard', 'cargus' ),
							'4' => __( 'Se vor printa doar AWB retur cu instructiuni', 'cargus' ),
						),
					),
					'parcel_contents'              => array(
						'title'       => __( 'Descriere conținut pachet', 'cargus' ),
						'type'        => 'select',
						'class'       => 'select_height',
						'options'     => array(
							'null'              => __( 'Blank', 'cargus' ),
							'order-id'          => __( 'Numar Comanda', 'cargus' ),
							'product-tile'      => __( 'Numar Comanda + Titlu produse', 'cargus' ),
							'product-sku'       => __( 'Numar Comanda + Sku produse', 'cargus' ),
							'product-title-sku' => __( 'Numar Comanda + Titlu produse + Sku', 'cargus' ),
						),
						'desc_tip'    => true,
						'description' => __( 'Descrierea continului coletului trecuta pe awb.', 'cargus' ),
					),
					'print_format'                 => array(
						'title'   => __( 'Format printare AWB', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							'0' => __( 'A4', 'cargus' ),
							'1' => __( 'Label 10x14', 'cargus' ),
						),
					),
					'print_twice'                  => array(
						'title'   => __( 'Printare AWB principal in dublu exemplar', 'cargus' ),
						'label'   => __( 'Activ', 'cargus' ),
						'type'    => 'checkbox',
						'default' => 'yes',
					),
					'order_status_create_awb'      => array(
						'title'   => __( 'Status comanda dupa genereare AWB', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							''                  => __( 'Nu schimba statusul comnezii', 'cargus' ),
							'wc-processing'     => __( 'In procesare', 'cargus' ),
							'wc-completed'      => __( 'Finalizata ', 'cargus' ),
							'wc-pending'        => __( 'Plată în așteptare', 'cargus' ),
							'wc-on-hold'        => __( 'În așteptare', 'cargus' ),
							'wc-cancelled'      => __( 'Anulată', 'cargus' ),
							'wc-refunded'       => __( 'Rambursată', 'cargus' ),
							'wc-failed'         => __( 'Eșuată', 'cargus' ),
							'wc-checkout-draft' => __( 'Ciornă', 'cargus' ),
						),
					),
					'order_status_remove_awb'      => array(
						'title'   => __( 'Status comanda dupa stergere AWB', 'cargus' ),
						'type'    => 'select',
						'class'   => 'select_height',
						'options' => array(
							''                  => __( 'Nu schimba statusul comnezii', 'cargus' ),
							'wc-processing'     => __( 'In procesare', 'cargus' ),
							'wc-completed'      => __( 'Finalizata ', 'cargus' ),
							'wc-pending'        => __( 'Plată în așteptare', 'cargus' ),
							'wc-on-hold'        => __( 'În așteptare', 'cargus' ),
							'wc-cancelled'      => __( 'Anulată', 'cargus' ),
							'wc-refunded'       => __( 'Rambursată', 'cargus' ),
							'wc-failed'         => __( 'Eșuată', 'cargus' ),
							'wc-checkout-draft' => __( 'Ciornă', 'cargus' ),
						),
					),
					'debug_cargus'                 => array(
						'title'   => __( 'Depanare modul', 'cargus' ),
						'label'   => __( 'Activ', 'cargus' ),
						'type'    => 'checkbox',
						'default' => 'yes',
					),
				);

				$this->form_fields += apply_filters( 'cargus_shipping_method_extra_fields', $extra_fields );
			}
		}

		/**
		 * Calculate the shipping cost.
		 *
		 * @since    1.0.0
		 * @param Array $package The shipping package data array.
		 */
		public function calculate_shipping( $package = array() ) {
			$debugger = CargusDebug::get_instance();
			// Attempt to calculate the shipping cost.
			$calculated_cost = $this->get_shipping_cost( $this, $package );

			// Check if the calculated cost is not null.
			if ( ! is_null( $calculated_cost ) ) {
				// Log the cost and title for debugging.

				$debugger->log( 'Shipping cost is not null. Calculated cost: ' . $calculated_cost );

				if ( 0 === $calculated_cost ) {
					// Append "free" to the title if the cost is zero.
					$this->title .= apply_filters( 'cargus_shipping_method_title_free', ' - Gratuit' );
					$debugger->log( 'Shipping cost is zero. Title updated to: ' . $this->title );
				}

				// Prepare the shipping rate data.
				$rate = array(
					'id'       => $this->id,
					'label'    => $this->title,
					'cost'     => $calculated_cost,
					'calc_tax' => 'per_order',
				);
				// Add the rate to the shipping method.
				$this->add_rate( $rate );
				$debugger->log( 'Shipping rate added: ' . $this->id );
			} else {
				$debugger->log( 'Shipping cost calculation returned null.' );
			}
		}


		/**
		 * Get the calculated the shipping cost.
		 *
		 * @param $cargus_options
		 * @param $package
		 *
		 * @return float|int|string|void|null
		 * @since    1.0.0
		 */
		private function get_shipping_cost( $cargus_options, $package ) {
			$debugger = CargusDebug::get_instance();
			try {
				$cargus       = Cargus_Api::get_instance();
				$cargus_token = get_option( 'cargus_login_token' );

				if ( $cargus_token && ! is_object( $cargus_token ) && ! is_array( $cargus_token ) &&
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
					// current() returns false when no gateway is available yet
					// (e.g. the cart is recalculated before gateways load).
					$current_gateway_id = is_object( $current_payment_gateway ) && isset( $current_payment_gateway->id ) ? $current_payment_gateway->id : '';
					$ramburs            = $total;
					if ( ! in_array( $current_gateway_id, array( 'cod', 'cargus_ship_and_go_payment' ), true ) ) {
						$ramburs = 0;
					}

					// UC check free.
					if ( ! empty( $cargus_options->free ) && $total >= $cargus_options->free ) {
						return 0;
					}

					// UC check fixed.
					$shipping_cost = 0;
					if ( '' !== $cargus_options->fixed && is_numeric( $cargus_options->fixed ) ) {
						$shipping_cost = $cargus_options->fixed;
					}

					// UC check buc fixed.
					if ( '' !== $cargus_options->buc_fixed && is_numeric( $cargus_options->buc_fixed ) && WC()->customer->get_shipping_state() === 'B' ) {
						$shipping_cost = $cargus_options->buc_fixed;
					}

					// Get weight.
					$weight = 0.0;
					foreach ( $package['contents'] as $item_id => $values ) {
						$_product = $values['data'];

						if ( $_product->get_weight() === '0' || $_product->get_weight() === '' ) {
							$product_weight = 0.1;
						} else {
							$product_weight = floatval( $_product->get_weight() );
						}

						$weight = $weight + ( $product_weight * floatval( $values['quantity'] ) );
					}

					$weight = ceil( wc_get_weight( $weight, 'kg', get_option( 'woocommerce_weight_unit' ) ) );
					if ( $weight < 1 ) {
						$weight = 1;
					}

					$shipping_cost_array = unserialize( get_option( 'woocommerce_cargus_weight_based_shipping' ) );
					if ( 'yes' === $cargus_options->weight_based_shipping_enable && ! empty( $shipping_cost_array ) ) {
						// order the shipping cost arry by weight max cap.
						$shipping_cost_array = usort(
							$shipping_cost_array,
							function ( $a, $b ) {
								return $a['min-weight'] <=> $b['min-weight'];
							}
						);
						foreach ( unserialize( get_option( 'woocommerce_cargus_weight_based_shipping' ) ) as $pair ) {
							if ( $weight >= $pair['min-weight'] && $weight <= $pair['max-weight'] ) {
								$shipping_cost = $pair['price'];
							}
						}
					}
					$discount_percent = apply_filters( 'cargus_add_shipping_discount', 0 );

					// if I already have a shipping cost from the fixed amounts retun this and exit function.
					if ( 0 !== $shipping_cost && is_int( $discount_percent ) ) {
						$shipping_cost -= ( $discount_percent / 100 * $shipping_cost );
						return $shipping_cost;
					}

					// UC punctul de ridicare default.
					$location = array();

					// obtine lista puncte de ridicare.
					$cargus_pickup_points = $cargus->get_pickup_locations();
					if ( is_null( $cargus_pickup_points ) || 'error' === $cargus_pickup_points ) {
						return null;
					}
					$debugger->log( 'The options from class cargus shipping method are: ' . print_r( $cargus_options, true ) );
					foreach ( $cargus_pickup_points as $pickup ) {
						if ( $pickup->LocationId == $cargus_options->pickup ) { //phpcs:ignore
							$location['locality_id'] = $pickup->LocalityId; //phpcs:ignore
						}
					}

					if ( empty( $location ) ) {
						return null;
					}

					$debugger->log( 'Location Locality Id: ' . print_r( $location['locality_id'], true ) );
					$debugger->log( 'declared value is: ' . $cargus_options->insurance );

					$cargus_settings = get_option( 'woocommerce_cargus_settings' );

					// UC shipping calculation.
					$fields = array(
						'FromLocalityId'         => $location['locality_id'],
						'ToLocalityId'           => 0,
						'FromCountyName'         => '',
						'FromLocalityName'       => '',
						'ToCountyName'           => trim( $package['destination']['state'] ),
						'ToLocalityName'         => trim( $package['destination']['state'] ) === 'B' ? 'Bucuresti' : trim( Cargus_Admin::cargus_normalize( $package['destination']['city'] ) ),
						'Parcels'                => 'envelope' === $cargus_options->type ? 0 : 1,
						'Envelopes'              => 'envelope' === $cargus_options->type ? 1 : 0,
						'TotalWeight'            => $weight,
						'DeclaredValue'          => 'yes' === $cargus_options->insurance ? $total : 0,
						'CashRepayment'          => ( null === $cargus_options->repayment ) ? 0 : ( 'bank' === $cargus_options->repayment ? 0 : $ramburs ),
						'BankRepayment'          => ( null === $cargus_options->repayment ) ? 0 : ( 'bank' === $cargus_options->repayment ? $ramburs : 0 ),
						'OtherRepayment'         => '',
						'PaymentInstrumentId'    => 0,
						'PaymentInstrumentValue' => 0,
						'OpenPackage'            => 'yes' === $cargus_options->open,
						'ShipmentPayer'          => 'recipient' === $cargus_options->payer ? 2 : 1,
						'SaturdayDelivery'       => false,
						'MorningDelivery'        => false,
						'ParcelCodes'            => array(
							array(
								'Type'   => 1,
								'Weight' => $weight,
								'Length' => $cargus_settings['length'],
								'Width'  => $cargus_settings['width'],
								'Height' => $cargus_settings['height'],
							),
						),
					);

					$service = $cargus_options->settings['service_id'] ?? null;

					if (self::MULTIPIECE_SERVICE_ID === $service) {
						if ($weight > self::WEIGHT_THRESHOLD_1 && $weight <= self::MAX_WEIGHT_1) {
							$fields['ParcelCodes'] = $this->calculate_parcel_codes($weight, self::WEIGHT_THRESHOLD_1, $cargus_settings);
						} elseif ($weight > self::MAX_WEIGHT_1 && $weight <= self::MAX_WEIGHT_2) {
							$fields['ParcelCodes'] = $this->calculate_parcel_codes($weight, self::WEIGHT_THRESHOLD_2, $cargus_settings);
						}
					}

					$fields['Parcels'] = count( $fields['ParcelCodes'] );

					$fields = Cargus_Admin::get_service_id( $service, $fields, $weight );

					$raw    = $cargus->get_shipping_calulation( $fields );
					$result = Cargus_Api::normalize_shipping_calculation( $raw );

					// Not a price (error list, Error object, no answer): no rate.
					// Previously an error *array* fell through, $result->Subtotal
					// read as null and the rate was offered as 0, i.e. free.
					if ( null === $result || ( property_exists( $result, 'Error' ) ) ) {
						$debugger->log( 'Cargus shipping calculation did not return a price: ' . Cargus_Api::describe_api_error( $raw ) );
						return null;
					}

					if ( 'yes' !== $cargus_options->shipping_cost_tax ) {
						$shipping_cost = $result->Subtotal; //phpcs:ignore
					} else {
						$shipping_cost = $result->GrandTotal; //phpcs:ignore
					}

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
		 * Calculate parcel codes for Multi-piece service
		 *
		 * @param float $total_weight Total weight to be distributed
		 * @param float $max_parcel_weight Maximum weight per parcel
		 * @param array $dimensions Parcel dimensions
		 * @return array Array of parcel codes
		 */
		private function calculate_parcel_codes($total_weight, $max_parcel_weight, $dimensions) {
			$parcels = array();
			$num_parcels = ceil($total_weight / $max_parcel_weight);
			
			for ($i = 0; $i < $num_parcels; $i++) {
				$remaining_weight = $total_weight - ($i * $max_parcel_weight);
				$parcel_weight = min($max_parcel_weight, $remaining_weight);
				
				$parcels[] = array(
					'Type'   => 1,
					'Weight' => $parcel_weight,
					'Length' => $dimensions['length'],
					'Width'  => $dimensions['width'],
					'Height' => $dimensions['height']
				);
				
				if ($remaining_weight <= $max_parcel_weight) {
					break;
				}
			}
			
			return $parcels;
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
		 * Admin notice for invalid package dimensions.
		 *
		 *  @since    1.0.0
		 */
		public function cargus_admin_dimensions_notice() {
			echo wp_kses_post( '<div class="notice notice-error"><p>' . __( 'Campurile pentru dimensiunile standard a coletului sunt obligatorii.', 'cargus' ) . '</p></div>' );
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

		/**
		 * Generates HTML for a custom repeater field in a WooCommerce shipping method settings page.
		 *
		 * This function constructs a table with input fields for weight and price, allowing users to add or remove rows dynamically.
		 *
		 * @param string $key  The key used to retrieve the field's value from the database.
		 * @param array  $data An associative array containing the field's configuration.
		 *
		 * @return string The generated HTML for the custom repeater field.
		 */
		public function generate_custom_repeater_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$value     = unserialize( get_option( $field_key, $data['default'] ) );
			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
					<?php echo $this->get_tooltip_html( $data ); ?>
				</th>
				<td class="forminp">
					<table class="wc-shipping-repeater-table widefat" style="max-width: 550px;">
						<thead>
							<tr>
								<th style="padding: 10px;"><?php _e( 'Greutate min(' . get_option( 'woocommerce_weight_unit' ) . ')', 'cargus' ); ?></th>
								<th style="padding: 10px;"><?php _e( 'Greutate max(' . get_option( 'woocommerce_weight_unit' ) . ')', 'cargus' ); ?></th>
								<th style="padding: 10px;"><?php _e( 'Cost (RON)', 'cargus' ); ?></th>
								<th style="padding: 10px;"></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $value ) && is_array( $value ) ) : ?>
								<?php foreach ( $value as $index => $item ) : ?>
									<tr data-counter="<?php echo esc_attr( $index ); ?>">
										<td><input type="number" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $index ); ?>][min-weight]" value="<?php echo esc_attr( $item['min-weight'] ); ?>" step=".001"/></td>
										<td><input type="number" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $index ); ?>][max-weight]" value="<?php echo esc_attr( $item['max-weight'] ); ?>" step=".001"/></td>
										<td><input type="number" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $item['price'] ); ?>" step=".001"/></td>
										<td><button type="button" class="button remove-row"><?php _e( 'Sterge', 'cagus' ); ?></button></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					<button type="button" style="margin-top: 10px;" class="button add-row"><?php _e( 'Adauga rand', 'cagus' ); ?></button>
					<?php echo $this->get_description_html( $data ); ?>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		/**
		 * Validate Text Field.
		 *
		 * Make sure the data is escaped correctly, etc.
		 *
		 * @param  string $key Field key.
		 * @param  string $value Posted Value.
		 * @return string
		 */
		public function validate_text_field( $key, $value ) {
			$value = is_null( $value ) ? '' : $value;
			if ( is_array( $value ) ) {
				return $value;
			}
			return wp_kses_post( trim( stripslashes( $value ) ) );
		}
	}
}
