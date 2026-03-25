<?php
/**
 * Plugin Name: Cats Invoice Generator
 * Description: Generates PDF invoices from WooCommerce orders and attaches them to order emails.
 * Version:     1.0.0
 * Author:      David Vidović
 * Author URI:  http://davidvidovic.com
 * Text Domain: cats-invoice-generator
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CATS_INVOICE_VERSION', '1.0.0' );
define( 'CATS_INVOICE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CATS_INVOICE_URL', plugin_dir_url( __FILE__ ) );
define( 'CATS_INVOICE_TMP_DIR', CATS_INVOICE_DIR . 'tmp/' );

// Declare WooCommerce HPOS compatibility.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Load Composer autoloader (after composer install).
$autoloader = CATS_INVOICE_DIR . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

require_once CATS_INVOICE_DIR . 'includes/class-cats-invoice-admin.php';
require_once CATS_INVOICE_DIR . 'includes/class-cats-invoice-generator.php';
require_once CATS_INVOICE_DIR . 'includes/class-cats-invoice-email.php';
require_once CATS_INVOICE_DIR . 'includes/class-cats-invoice-order-actions.php';

/**
 * Main plugin bootstrap — singleton.
 */
final class Cats_Invoice_Generator_Plugin {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	public function init(): void {
		if ( ! $this->woocommerce_is_active() ) {
			add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
			return;
		}

		if ( ! file_exists( CATS_INVOICE_DIR . 'vendor/autoload.php' ) ) {
			add_action( 'admin_notices', [ $this, 'vendor_missing_notice' ] );
			return;
		}

		// Ensure defaults are populated even when the plugin was already active
		// before new fields were added (runs only when a value is actually missing).
		$this->set_default_settings();

		new Cats_Invoice_Admin();
		new Cats_Invoice_Email();
		new Cats_Invoice_Order_Actions();
	}

	public function activate(): void {
		$this->create_tmp_directory();
		$this->set_default_settings();
	}

	private function set_default_settings(): void {
		$defaults = [
			// Opći podaci firme
			'company_name'          => "CAT'S CLUB Slađana Regoja Krešojević s.p.",
			'company_address'       => "Dr Mladena Stojanovića 119\n78000 Banja Luka",
			'jib_number'            => '4512394400004',
			'vat_number'            => '512394400004',
			// Domaće plaćanje (BiH)
			'bank_name'             => 'Atos banka a.d. Banja Luka',
			'bank_account'          => '567-241-25002037-69',
			'payment_reference'     => 'uplata za robu',
			// Međunarodno plaćanje (EU / SWIFT)
			'eu_intermediary_swift' => 'RZBAATWWXXX',
			'eu_intermediary_bank'  => "RAIFFEISEN BANK INTERNATIONAL AG\nAM STADTPARK 9\nVIENNA 1030\nAUSTRIA",
			'eu_bank_swift'         => 'SABRBA2B',
			'eu_bank_name'          => "ATOS BANK A.D.\nBANJA LUKA",
			'eu_beneficiary_iban'   => 'BA3956724 10001035726',
			'eu_payment_reference'  => 'uplata za robu',
			// Ostalo
			'footer_text'           => '',
			'logo_url'              => '',
		];

		$existing = get_option( 'cats_invoice_settings', [] );

		// Merge: defaults fill in any key that is missing or an empty string.
		// Keys already set in the admin (non-empty) are never overwritten.
		$merged = $defaults;
		foreach ( $existing as $key => $value ) {
			if ( '' !== $value ) {
				$merged[ $key ] = $value;
			}
		}

		// Only write to the DB when something actually changed.
		if ( $merged !== $existing ) {
			update_option( 'cats_invoice_settings', $merged );
		}
	}

	public function deactivate(): void {
		wp_clear_scheduled_hook( 'cats_invoice_cleanup_tmp' );
	}

	private function create_tmp_directory(): void {
		if ( ! file_exists( CATS_INVOICE_TMP_DIR ) ) {
			wp_mkdir_p( CATS_INVOICE_TMP_DIR );
		}

		$htaccess = CATS_INVOICE_TMP_DIR . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" );
		}

		$index = CATS_INVOICE_TMP_DIR . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php // Silence is golden.\n" );
		}

		$fonts_dir = CATS_INVOICE_TMP_DIR . 'fonts/';
		if ( ! file_exists( $fonts_dir ) ) {
			wp_mkdir_p( $fonts_dir );
		}
	}

	private function woocommerce_is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function woocommerce_missing_notice(): void {
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Cats Invoice Generator: WooCommerce mora biti instaliran i aktivan.', 'cats-invoice-generator' )
			. '</p></div>';
	}

	public function vendor_missing_notice(): void {
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Cats Invoice Generator: pokrenite "composer install" u direktoriju plugina kako biste instalirali zavisnosti.', 'cats-invoice-generator' )
			. '</p></div>';
	}
}

Cats_Invoice_Generator_Plugin::get_instance();
