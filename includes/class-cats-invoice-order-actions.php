<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects invoice generation to the WooCommerce order admin:
 *  - Meta box on the order detail page (download + regenerate buttons)
 *  - Custom row action in the orders list ("Preuzmi fakturu")
 *  - Secure download handler for both entry points
 */
class Cats_Invoice_Order_Actions {

	public function __construct() {
		// Meta box on single order page (classic editor & HPOS screen).
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );

		// Row action in WC orders list (both legacy post list & HPOS list).
		add_filter( 'woocommerce_admin_order_actions', [ $this, 'add_order_list_action' ], 10, 2 );
		add_action( 'admin_head', [ $this, 'order_action_icon_css' ] );

		// Handle the download request (nonce-protected POST/GET).
		add_action( 'admin_post_cats_invoice_download', [ $this, 'handle_download' ] );
	}

	// -------------------------------------------------------------------------
	// Meta box
	// -------------------------------------------------------------------------

	public function register_meta_box(): void {
		// Works for both the classic CPT screen and the HPOS order screen.
		$screens = [ 'shop_order', 'woocommerce_page_wc-orders' ];

		foreach ( $screens as $screen ) {
			add_meta_box(
				'cats_invoice_meta_box',
				__( 'Faktura', 'cats-invoice-generator' ),
				[ $this, 'render_meta_box' ],
				$screen,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( $post_or_order ): void {
		// Support both classic (WP_Post) and HPOS (WC_Order) argument.
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		$order_id   = $order->get_id();
		$pdf_exists = file_exists( CATS_INVOICE_TMP_DIR . 'faktura-' . $order_id . '.pdf' );

		$download_url = wp_nonce_url(
			add_query_arg(
				[
					'action'   => 'cats_invoice_download',
					'order_id' => $order_id,
				],
				admin_url( 'admin-post.php' )
			),
			'cats_invoice_download_' . $order_id
		);
		?>
		<p>
			<a href="<?php echo esc_url( $download_url ); ?>"
			   class="button button-primary" style="width:100%;text-align:center;">
				<?php esc_html_e( 'Preuzmi fakturu (PDF)', 'cats-invoice-generator' ); ?>
			</a>
		</p>
		<?php if ( $pdf_exists ) : ?>
			<p style="font-size:11px;color:#666;margin:4px 0 0;">
				<?php esc_html_e( 'Faktura je već generisana. Dugme će je regenerisati.', 'cats-invoice-generator' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Orders list row action
	// -------------------------------------------------------------------------

	/**
	 * @param array    $actions Existing order row actions.
	 * @param WC_Order $order
	 */
	public function add_order_list_action( array $actions, WC_Order $order ): array {
		$url = wp_nonce_url(
			add_query_arg(
				[
					'action'   => 'cats_invoice_download',
					'order_id' => $order->get_id(),
				],
				admin_url( 'admin-post.php' )
			),
			'cats_invoice_download_' . $order->get_id()
		);

		$actions['cats_invoice'] = [
			'url'    => $url,
			'name'   => __( 'Preuzmi fakturu', 'cats-invoice-generator' ),
			'action' => 'cats_invoice',
		];

		return $actions;
	}

	/** Small icon for the row action button in the orders list. */
	public function order_action_icon_css(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		// Show on both legacy post-list and HPOS order list.
		if ( ! in_array( $screen->id, [ 'edit-shop_order', 'woocommerce_page_wc-orders' ], true ) ) {
			return;
		}
		echo '<style>
			.wc-action-button-cats_invoice::after { font-family: Dashicons; content: "\f316" !important; }
		</style>';
	}

	// -------------------------------------------------------------------------
	// Download handler
	// -------------------------------------------------------------------------

	public function handle_download(): void {
		// Verify nonce.
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_die( esc_html__( 'Nevažeći zahtjev.', 'cats-invoice-generator' ) );
		}

		if ( ! check_admin_referer( 'cats_invoice_download_' . $order_id ) ) {
			wp_die( esc_html__( 'Nevažeći sigurnosni token.', 'cats-invoice-generator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Nemate dozvolu za ovu radnju.', 'cats-invoice-generator' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Narudžba nije pronađena.', 'cats-invoice-generator' ) );
		}

		try {
			$generator = new Cats_Invoice_Generator();
			$file_path = $generator->generate_pdf( $order );
		} catch ( \Throwable $e ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s error message */
						__( 'Greška pri generisanju fakture: %s', 'cats-invoice-generator' ),
						$e->getMessage()
					)
				)
			);
		}

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'PDF fajl nije pronađen.', 'cats-invoice-generator' ) );
		}

		$filename = 'faktura-' . $order->get_order_number() . '.pdf';

		// Stream the file to the browser as a download.
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $file_path ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );
		exit;
	}
}
