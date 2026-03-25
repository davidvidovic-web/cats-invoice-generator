<?php
/**
 * Invoice PDF template.
 *
 * Available variables:
 *   $order     (WC_Order)  — the WooCommerce order
 *   $settings  (array)     — plugin settings from get_option('cats_invoice_settings')
 *   $logo_path (string)    — absolute file-system path to the logo image (may be empty)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$billing_country      = $order->get_billing_country();
$country_name         = WC()->countries->countries[ $billing_country ] ?? $billing_country;

// Detect local pickup shipping method.
$is_local_pickup = false;
foreach ( $order->get_shipping_methods() as $shipping_method ) {
	if ( in_array( $shipping_method->get_method_id(), [ 'local_pickup', 'local_pickup_plus' ], true ) ) {
		$is_local_pickup = true;
		break;
	}
}

$order_date           = $order->get_date_created();
$order_date_formatted = $order_date
	? date_i18n( 'd.m.Y.', $order_date->getTimestamp() )
	: '';

// Inline CSS from file.
$css_file = CATS_INVOICE_DIR . 'assets/css/invoice.css';
$css      = file_exists( $css_file )
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	? file_get_contents( $css_file )
	: '';
?>
<!DOCTYPE html>
<html lang="bs">
<head>
<meta charset="UTF-8">
<style><?php echo $css; // Already sanitized — internal CSS file only. ?></style>
</head>
<body>

<!-- ═══════════════════════════ HEADER ═══════════════════════════ -->
<table class="header-table" width="100%">
	<tr>
		<!-- Company info -->
		<td class="td-company" width="55%">
			<?php if ( $logo_path && file_exists( $logo_path ) ) : ?>
				<img src="<?php echo esc_attr( $logo_path ); ?>" alt="" class="logo">
			<?php endif; ?>

			<?php if ( ! empty( $settings['company_name'] ) ) : ?>
				<div class="company-name"><?php echo esc_html( $settings['company_name'] ); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $settings['company_address'] ) ) : ?>
				<div class="company-address">
					<?php echo nl2br( esc_html( $settings['company_address'] ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $settings['jib_number'] ) ) : ?>
				<div class="company-vat">
					<strong><?php esc_html_e( 'JIB', 'cats-invoice-generator' ); ?>:</strong>
					<?php echo esc_html( $settings['jib_number'] ); ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $settings['vat_number'] ) ) : ?>
				<div class="company-vat">
					<strong><?php esc_html_e( 'PDV/ID', 'cats-invoice-generator' ); ?>:</strong>
					<?php echo esc_html( $settings['vat_number'] ); ?>
				</div>
			<?php endif; ?>
		</td>

		<!-- Invoice meta -->
		<td class="td-meta" width="45%">
			<div class="invoice-title"><?php esc_html_e( 'FAKTURA', 'cats-invoice-generator' ); ?></div>
			<table class="meta-table" width="100%">
				<tr>
					<td class="meta-label"><?php esc_html_e( 'Broj fakture', 'cats-invoice-generator' ); ?></td>
					<td class="meta-value"><?php echo esc_html( $order->get_order_number() ); ?></td>
				</tr>
				<tr>
					<td class="meta-label"><?php esc_html_e( 'Datum', 'cats-invoice-generator' ); ?></td>
					<td class="meta-value"><?php echo esc_html( $order_date_formatted ); ?></td>
				</tr>
				<tr>
					<td class="meta-label"><?php esc_html_e( 'Način plaćanja', 'cats-invoice-generator' ); ?></td>
					<td class="meta-value">
						<?php
						if ( 'BA' !== $billing_country && $is_local_pickup ) {
							echo esc_html__( 'Plaćanje na račun', 'cats-invoice-generator' );
						} else {
							echo esc_html( $order->get_payment_method_title() );
						}
						?>
					</td>
				</tr>
				<tr>
					<td class="meta-label"><?php esc_html_e( 'Status', 'cats-invoice-generator' ); ?></td>
					<td class="meta-value"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<div class="divider"></div>

<!-- ═══════════════════════════ BILLING ADDRESS ═══════════════════════════ -->
<table class="section-table" width="100%">
	<tr>
		<td width="100%">
			<div class="section-label"><?php esc_html_e( 'Kupac', 'cats-invoice-generator' ); ?></div>
			<div class="billing-name">
				<?php echo esc_html( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ); ?>
			</div>
			<?php if ( $order->get_billing_company() ) : ?>
				<div><?php echo esc_html( $order->get_billing_company() ); ?></div>
			<?php endif; ?>
			<div><?php echo esc_html( $order->get_billing_address_1() ); ?></div>
			<?php if ( $order->get_billing_address_2() ) : ?>
				<div><?php echo esc_html( $order->get_billing_address_2() ); ?></div>
			<?php endif; ?>
			<div>
				<?php echo esc_html( trim( $order->get_billing_city() . ', ' . $order->get_billing_postcode(), ', ' ) ); ?>
			</div>
			<div><?php echo esc_html( $country_name ); ?></div>
			<?php if ( $order->get_billing_email() ) : ?>
				<div class="billing-email"><?php echo esc_html( $order->get_billing_email() ); ?></div>
			<?php endif; ?>
		</td>
	</tr>
</table>

<div class="divider"></div>

<!-- ═══════════════════════════ ORDER ITEMS ═══════════════════════════ -->
<table class="items-table" width="100%">
	<thead>
		<tr>
			<th class="th-desc"><?php esc_html_e( 'Opis', 'cats-invoice-generator' ); ?></th>
			<th class="th-qty"><?php esc_html_e( 'Kol.', 'cats-invoice-generator' ); ?></th>
			<th class="th-price"><?php esc_html_e( 'Cijena/kom', 'cats-invoice-generator' ); ?></th>
			<th class="th-total"><?php esc_html_e( 'Ukupno', 'cats-invoice-generator' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $order->get_items() as $item ) : ?>
			<?php
			/** @var WC_Order_Item_Product $item */
			$product    = $item->get_product();
			$qty        = $item->get_quantity();
			$line_total = (float) $item->get_total();
			$unit_price = $qty > 0 ? $line_total / $qty : 0.0;
			?>
			<tr>
				<td class="td-desc">
					<?php echo esc_html( $item->get_name() ); ?>
					<?php if ( $product && $product->get_sku() ) : ?>
						<div class="item-sku">SKU: <?php echo esc_html( $product->get_sku() ); ?></div>
					<?php endif; ?>
				</td>
				<td class="td-qty"><?php echo esc_html( $qty ); ?></td>
				<td class="td-price">
					<?php echo wp_kses_post( wc_price( $unit_price, [ 'currency' => $order->get_currency() ] ) ); ?>
				</td>
				<td class="td-total">
					<?php echo wp_kses_post( wc_price( $line_total, [ 'currency' => $order->get_currency() ] ) ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<?php if ( (float) $order->get_discount_total() > 0 ) : ?>
			<tr class="totals-row">
				<td colspan="3" class="totals-label">
					<?php esc_html_e( 'Popust', 'cats-invoice-generator' ); ?>
				</td>
				<td class="td-total">
					&minus; <?php echo wp_kses_post( wc_price( $order->get_discount_total(), [ 'currency' => $order->get_currency() ] ) ); ?>
				</td>
			</tr>
		<?php endif; ?>

		<?php if ( (float) $order->get_shipping_total() > 0 ) : ?>
			<tr class="totals-row">
				<td colspan="3" class="totals-label">
					<?php esc_html_e( 'Dostava', 'cats-invoice-generator' ); ?>
				</td>
				<td class="td-total">
					<?php echo wp_kses_post( wc_price( $order->get_shipping_total(), [ 'currency' => $order->get_currency() ] ) ); ?>
				</td>
			</tr>
		<?php endif; ?>

		<?php if ( (float) $order->get_total_tax() > 0 ) : ?>
			<tr class="totals-row">
				<td colspan="3" class="totals-label">
					<?php esc_html_e( 'PDV', 'cats-invoice-generator' ); ?>
				</td>
				<td class="td-total">
					<?php echo wp_kses_post( wc_price( $order->get_total_tax(), [ 'currency' => $order->get_currency() ] ) ); ?>
				</td>
			</tr>
		<?php endif; ?>

		<tr class="grand-total-row">
			<td colspan="3" class="grand-total-label">
				<?php esc_html_e( 'UKUPNO ZA PLATITI', 'cats-invoice-generator' ); ?>
			</td>
			<td class="grand-total-amount">
				<?php echo wp_kses_post( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) ); ?>
			</td>
		</tr>
	</tfoot>
</table>

<!-- ═══════════════════════════ PODACI ZA PLAĆANJE ═══════════════════════════ -->
<div class="divider"></div>

<?php if ( 'BA' === $billing_country ) : ?>
	<!-- Domaće plaćanje (BiH kupci) -->
	<div class="bank-box">
		<div class="section-label"><?php esc_html_e( 'Podaci za uplatu', 'cats-invoice-generator' ); ?></div>
		<?php if ( ! empty( $settings['bank_name'] ) ) : ?>
			<div>
				<strong><?php esc_html_e( 'Banka', 'cats-invoice-generator' ); ?>:</strong>
				<?php echo esc_html( $settings['bank_name'] ); ?>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $settings['bank_account'] ) ) : ?>
			<div>
				<strong><?php esc_html_e( 'Broj računa', 'cats-invoice-generator' ); ?>:</strong>
				<?php echo esc_html( $settings['bank_account'] ); ?>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $settings['payment_reference'] ) ) : ?>
			<div>
				<strong><?php esc_html_e( 'Svrha uplate', 'cats-invoice-generator' ); ?>:</strong>
				<?php echo esc_html( $settings['payment_reference'] ); ?>
			</div>
		<?php endif; ?>
	</div>

<?php else : ?>
	<!-- Međunarodno plaćanje (EU i ostale zemlje) -->
	<div class="bank-box bank-box--eu">
		<div class="section-label"><?php esc_html_e( 'Podaci za međunarodno plaćanje (SWIFT)', 'cats-invoice-generator' ); ?></div>

		<table class="swift-table" width="100%">
			<?php if ( ! empty( $settings['eu_intermediary_swift'] ) || ! empty( $settings['eu_intermediary_bank'] ) ) : ?>
				<tr>
					<td class="swift-code">56A</td>
					<td class="swift-label"><?php esc_html_e( 'Posrednička banka', 'cats-invoice-generator' ); ?> <span class="swift-type">Swift</span></td>
					<td class="swift-value">
						<?php if ( ! empty( $settings['eu_intermediary_swift'] ) ) : ?>
							<span class="swift-code-val"><?php echo esc_html( $settings['eu_intermediary_swift'] ); ?></span><br>
						<?php endif; ?>
						<?php if ( ! empty( $settings['eu_intermediary_bank'] ) ) : ?>
							<?php echo nl2br( esc_html( $settings['eu_intermediary_bank'] ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>

			<?php if ( ! empty( $settings['eu_bank_swift'] ) || ! empty( $settings['eu_bank_name'] ) ) : ?>
				<tr>
					<td class="swift-code">57A</td>
					<td class="swift-label"><?php esc_html_e( 'Banka korisnika', 'cats-invoice-generator' ); ?> <span class="swift-type">Swift</span></td>
					<td class="swift-value">
						<?php if ( ! empty( $settings['eu_bank_swift'] ) ) : ?>
							<span class="swift-code-val"><?php echo esc_html( $settings['eu_bank_swift'] ); ?></span><br>
						<?php endif; ?>
						<?php if ( ! empty( $settings['eu_bank_name'] ) ) : ?>
							<?php echo nl2br( esc_html( $settings['eu_bank_name'] ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>

			<?php if ( ! empty( $settings['eu_beneficiary_iban'] ) ) : ?>
				<tr>
					<td class="swift-code">59</td>
					<td class="swift-label"><?php esc_html_e( 'Korisnik', 'cats-invoice-generator' ); ?> <span class="swift-type">IBAN</span></td>
					<td class="swift-value">
						<span class="swift-code-val"><?php echo esc_html( $settings['eu_beneficiary_iban'] ); ?></span><br>
						<?php echo esc_html( $settings['company_name'] ?? '' ); ?><br>
						<?php echo nl2br( esc_html( $settings['company_address'] ?? '' ) ); ?>
					</td>
				</tr>
			<?php endif; ?>

			<?php if ( ! empty( $settings['eu_payment_reference'] ) ) : ?>
				<tr>
					<td class="swift-code">70</td>
					<td class="swift-label"><?php esc_html_e( 'Svrha plaćanja', 'cats-invoice-generator' ); ?></td>
					<td class="swift-value"><?php echo esc_html( $settings['eu_payment_reference'] ); ?></td>
				</tr>
			<?php endif; ?>
		</table>

		<?php if ( $is_local_pickup ) : ?>
			<div class="shipping-note"><?php esc_html_e( '* Kupac snosi troškove dostave.', 'cats-invoice-generator' ); ?></div>
		<?php endif; ?>
	</div>

<?php endif; ?>

<!-- ═══════════════════════════ FOOTER ═══════════════════════════ -->
<?php if ( ! empty( $settings['footer_text'] ) ) : ?>
	<div class="invoice-footer">
		<?php echo nl2br( esc_html( $settings['footer_text'] ) ); ?>
	</div>
<?php endif; ?>

</body>
</html>
