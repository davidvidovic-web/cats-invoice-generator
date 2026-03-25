<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into WooCommerce email sending to attach the generated PDF invoice.
 */
class Cats_Invoice_Email {

	/**
	 * Email IDs that should receive the invoice PDF as an attachment.
	 */
	private const ATTACH_TO_EMAILS = [
		'customer_processing_order',
		'customer_completed_order',
	];

	public function __construct() {
		add_filter( 'woocommerce_email_attachments', [ $this, 'attach_invoice' ], 99, 4 );
	}

	/**
	 * Generate and attach the invoice PDF to qualifying order emails.
	 *
	 * @param array     $attachments Existing email attachments (file paths).
	 * @param string    $email_id    WooCommerce email identifier.
	 * @param mixed     $order       Should be a WC_Order instance.
	 * @param mixed     $email       WC_Email instance (may be null in older WC).
	 * @return array
	 */
	public function attach_invoice( array $attachments, string $email_id, mixed $order, mixed $email = null ): array {
		if ( ! in_array( $email_id, self::ATTACH_TO_EMAILS, true ) ) {
			return $attachments;
		}

		if ( ! $order instanceof WC_Order ) {
			return $attachments;
		}

		try {
			$generator  = new Cats_Invoice_Generator();
			$attachment = $generator->generate_pdf( $order );

			if ( $attachment && file_exists( $attachment ) ) {
				$attachments[] = $attachment;
			}
		} catch ( \Throwable $e ) {
			// Never let a PDF failure prevent the email from sending.
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[Cats Invoice Generator] PDF generation failed for order #%d: %s',
						$order->get_id(),
						$e->getMessage()
					)
				);
			}
		}

		return $attachments;
	}
}
