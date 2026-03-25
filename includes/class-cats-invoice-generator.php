<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Handles PDF generation for a WooCommerce order.
 */
class Cats_Invoice_Generator {

	/**
	 * Generate a PDF invoice for the given order and return its file path.
	 *
	 * @throws \RuntimeException If the PDF cannot be written to disk.
	 */
	public function generate_pdf( WC_Order $order ): string {
		$this->ensure_tmp_dirs();

		$file_path = CATS_INVOICE_TMP_DIR . 'faktura-' . $order->get_id() . '.pdf';
		$html      = $this->render_template( $order );

		$options = new Options();
		$options->setDefaultFont( 'DejaVu Sans' );
		$options->setIsHtml5ParserEnabled( true );
		$options->setIsRemoteEnabled( false );      // Images loaded via file:// path only.
		$options->setChroot( ABSPATH );             // Allow files within the WP root.
		$options->setTempDir( CATS_INVOICE_TMP_DIR );
		$options->setFontDir( CATS_INVOICE_TMP_DIR . 'fonts/' );
		$options->setFontCache( CATS_INVOICE_TMP_DIR . 'fonts/' );

		$dompdf = new Dompdf( $options );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		$pdf_bytes = $dompdf->output();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $file_path, $pdf_bytes ) ) {
			throw new \RuntimeException( 'Could not write PDF to: ' . $file_path );
		}

		return $file_path;
	}

	// -------------------------------------------------------------------------
	// Template rendering
	// -------------------------------------------------------------------------

	private function render_template( WC_Order $order ): string {
		$settings = get_option( 'cats_invoice_settings', [] );

		// Convert logo URL to an absolute file path so Dompdf can load it
		// directly from disk without needing remote HTTP access.
		$logo_path = '';
		if ( ! empty( $settings['logo_url'] ) ) {
			$logo_path = $this->url_to_file_path( $settings['logo_url'] );
		}

		ob_start();
		// Variables available inside the template: $order, $settings, $logo_path.
		include CATS_INVOICE_DIR . 'templates/invoice-template.php';
		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert a WordPress upload URL to an absolute server file path.
	 * Returns the original URL unchanged if the mapping cannot be determined.
	 */
	private function url_to_file_path( string $url ): string {
		$upload_dir = wp_upload_dir();

		if ( str_starts_with( $url, $upload_dir['baseurl'] ) ) {
			return str_replace(
				$upload_dir['baseurl'],
				$upload_dir['basedir'],
				$url
			);
		}

		// Fallback: try site-root mapping.
		$site_url = untrailingslashit( get_site_url() );
		if ( str_starts_with( $url, $site_url ) ) {
			return untrailingslashit( ABSPATH ) . substr( $url, strlen( $site_url ) );
		}

		return $url;
	}

	private function ensure_tmp_dirs(): void {
		foreach ( [ CATS_INVOICE_TMP_DIR, CATS_INVOICE_TMP_DIR . 'fonts/' ] as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}
		}
	}
}
