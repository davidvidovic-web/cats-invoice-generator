<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Invoice Settings admin page under the WooCommerce menu
 * and handles saving/rendering all settings fields.
 */
class Cats_Invoice_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	// -------------------------------------------------------------------------
	// Menu & settings registration
	// -------------------------------------------------------------------------

	public function add_settings_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Postavke faktura', 'cats-invoice-generator' ),
			__( 'Postavke faktura', 'cats-invoice-generator' ),
			'manage_woocommerce',
			'cats-invoice-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			'cats_invoice_settings_group',
			'cats_invoice_settings',
			[ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
		);

		add_settings_section(
			'cats_invoice_company_section',
			__( 'Podaci o firmi', 'cats-invoice-generator' ),
			null,
			'cats-invoice-settings'
		);

		$fields = [
			'company_name'       => __( 'Naziv firme', 'cats-invoice-generator' ),
			'company_address'    => __( 'Adresa firme', 'cats-invoice-generator' ),
			'jib_number'         => __( 'JIB', 'cats-invoice-generator' ),
			'vat_number'         => __( 'PDV / ID broj', 'cats-invoice-generator' ),
			'bank_name'          => __( 'Naziv banke', 'cats-invoice-generator' ),
			'bank_account'       => __( 'Broj računa / IBAN', 'cats-invoice-generator' ),
			'payment_reference'  => __( 'Svrha uplate', 'cats-invoice-generator' ),
			'footer_text'        => __( 'Tekst u podnožju', 'cats-invoice-generator' ),
			'logo_url'           => __( 'Logo firme', 'cats-invoice-generator' ),
		];

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'cats_invoice_' . $key,
				$label,
				[ $this, 'render_field' ],
				'cats-invoice-settings',
				'cats_invoice_company_section',
				[ 'field' => $key ]
			);
		}

		// --- EU / International payment section ---
		add_settings_section(
			'cats_invoice_eu_section',
			__( 'Međunarodno plaćanje / EU (SWIFT)', 'cats-invoice-generator' ),
			function () {
				echo '<p class="description">' . esc_html__( 'Prikazuje se na fakturama za kupce izvan Bosne i Hercegovine.', 'cats-invoice-generator' ) . '</p>';
			},
			'cats-invoice-settings'
		);

		$eu_fields = [
			'eu_intermediary_swift' => __( '56A: SWIFT posredničke banke', 'cats-invoice-generator' ),
			'eu_intermediary_bank'  => __( '56A: Posrednička banka (naziv i adresa)', 'cats-invoice-generator' ),
			'eu_bank_swift'         => __( '57A: SWIFT banke korisnika', 'cats-invoice-generator' ),
			'eu_bank_name'          => __( '57A: Banka korisnika', 'cats-invoice-generator' ),
			'eu_beneficiary_iban'   => __( '59: IBAN korisnika', 'cats-invoice-generator' ),
			'eu_payment_reference'  => __( '70: Svrha plaćanja', 'cats-invoice-generator' ),
		];

		foreach ( $eu_fields as $key => $label ) {
			add_settings_field(
				'cats_invoice_' . $key,
				$label,
				[ $this, 'render_field' ],
				'cats-invoice-settings',
				'cats_invoice_eu_section',
				[ 'field' => $key ]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Sanitization
	// -------------------------------------------------------------------------

	public function sanitize_settings( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( [
			'company_name', 'jib_number', 'vat_number',
			'bank_name', 'bank_account', 'payment_reference',
			'eu_intermediary_swift', 'eu_bank_swift',
			'eu_beneficiary_iban', 'eu_payment_reference',
		] as $field ) {
			$sanitized[ $field ] = isset( $input[ $field ] )
				? sanitize_text_field( $input[ $field ] )
				: '';
		}

		foreach ( [ 'company_address', 'eu_intermediary_bank', 'eu_bank_name', 'footer_text' ] as $field ) {
			$sanitized[ $field ] = isset( $input[ $field ] )
				? sanitize_textarea_field( $input[ $field ] )
				: '';
		}

		$sanitized['logo_url'] = isset( $input['logo_url'] )
			? esc_url_raw( $input['logo_url'] )
			: '';

		return $sanitized;
	}

	// -------------------------------------------------------------------------
	// Field rendering
	// -------------------------------------------------------------------------

	public function render_field( array $args ): void {
		$options = get_option( 'cats_invoice_settings', [] );
		$field   = $args['field'];
		$value   = $options[ $field ] ?? '';

		switch ( $field ) {
			case 'company_address':
			case 'eu_intermediary_bank':
			case 'eu_bank_name':
			case 'footer_text':
				printf(
					'<textarea name="cats_invoice_settings[%1$s]" id="cats_invoice_%1$s" rows="4" class="large-text">%2$s</textarea>',
					esc_attr( $field ),
					esc_textarea( $value )
				);
				break;

			case 'logo_url':
				$this->render_logo_field( $value );
				break;

			default:
				printf(
					'<input type="text" name="cats_invoice_settings[%1$s]" id="cats_invoice_%1$s" value="%2$s" class="regular-text">',
					esc_attr( $field ),
					esc_attr( $value )
				);
		}
	}

	private function render_logo_field( string $value ): void {
		?>
		<input type="hidden"
			   name="cats_invoice_settings[logo_url]"
			   id="cats_invoice_logo_url"
			   value="<?php echo esc_attr( $value ); ?>">

		<?php if ( $value ) : ?>
			<div id="cats_invoice_logo_preview_wrap" style="margin-bottom:8px;">
				<img src="<?php echo esc_url( $value ); ?>"
					 id="cats_invoice_logo_preview"
					 style="max-height:80px;max-width:260px;display:block;">
			</div>
		<?php else : ?>
			<div id="cats_invoice_logo_preview_wrap" style="margin-bottom:8px;display:none;">
				<img src="" id="cats_invoice_logo_preview" style="max-height:80px;max-width:260px;display:block;">
			</div>
		<?php endif; ?>

		<button type="button" class="button" id="cats_invoice_logo_upload">
			<?php esc_html_e( 'Učitaj / Odaberi logo', 'cats-invoice-generator' ); ?>
		</button>
		<button type="button"
				class="button"
				id="cats_invoice_logo_remove"
			<?php echo $value ? '' : 'style="display:none;"'; ?>>
			<?php esc_html_e( 'Ukloni', 'cats-invoice-generator' ); ?>
		</button>
		<p class="description"><?php esc_html_e( 'Preporučeno: PNG ili JPG, max 400px širine.', 'cats-invoice-generator' ); ?></p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Settings page render
	// -------------------------------------------------------------------------

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Nemate dozvolu za pristup ovoj stranici.', 'cats-invoice-generator' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Postavke faktura', 'cats-invoice-generator' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'cats_invoice_settings_group' );
				do_settings_sections( 'cats-invoice-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Scripts
	// -------------------------------------------------------------------------

	public function enqueue_scripts( string $hook ): void {
		if ( 'woocommerce_page_cats-invoice-settings' !== $hook ) {
			return;
		}

		// wp_enqueue_media() loads the full WP media frame (wp.media).
		wp_enqueue_media();

		// Register a tiny inline script that depends on 'wp-mediaelement' so it
		// is guaranteed to execute after wp.media is fully initialised.
		wp_register_script(
			'cats-invoice-logo-uploader',
			false,
			[ 'wp-mediaelement' ],
			CATS_INVOICE_VERSION,
			true   // footer
		);
		wp_enqueue_script( 'cats-invoice-logo-uploader' );
		wp_add_inline_script( 'cats-invoice-logo-uploader', $this->logo_uploader_js() );
	}

	private function logo_uploader_js(): string {
		return <<<'JS'
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var uploadBtn  = document.getElementById('cats_invoice_logo_upload');
        var removeBtn  = document.getElementById('cats_invoice_logo_remove');
        var logoInput  = document.getElementById('cats_invoice_logo_url');
        var previewWrap = document.getElementById('cats_invoice_logo_preview_wrap');
        var preview    = document.getElementById('cats_invoice_logo_preview');

        if (!uploadBtn) { return; }

        var mediaFrame;

        uploadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (mediaFrame) { mediaFrame.open(); return; }

            mediaFrame = wp.media({
                title:    'Odaberi logo fakture',
                button:   { text: 'Koristi ovu sliku' },
                multiple: false,
                library:  { type: 'image' }
            });

            mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                logoInput.value         = attachment.url;
                preview.src             = attachment.url;
                previewWrap.style.display = '';
                removeBtn.style.display   = '';
            });

            mediaFrame.open();
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                logoInput.value           = '';
                preview.src               = '';
                previewWrap.style.display = 'none';
                removeBtn.style.display   = 'none';
            });
        }
    });
}());
JS;
	}
}
