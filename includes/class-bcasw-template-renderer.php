<?php
/**
 * Template renderer — replaces {placeholders} safely.
 *
 * Known placeholders:
 *   {site_name}, {order_number}, {customer_name}, {order_total}, {currency},
 *   {bank_name}, {account_name}, {account_number}, {sort_code}, {iban}, {swift_bic},
 *   {billing_phone}, {billing_email}
 *
 * Unknown placeholders are removed from output to avoid broken strings.
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Template_Renderer {

	/**
	 * Render a template string with values from an order and bank account.
	 *
	 * @param string        $template Template string with {placeholders}.
	 * @param WC_Order      $order    WooCommerce order object.
	 * @param array|null    $bank     Bank account array (from BCASW_Bank_Accounts).
	 * @return string
	 */
	public static function render( string $template, WC_Order $order, ?array $bank = null ): string {
		$vars = self::build_vars( $order, $bank );
		return self::replace( $template, $vars );
	}

	/**
	 * Build the variable map for a given order and bank account.
	 *
	 * @param WC_Order   $order WooCommerce order.
	 * @param array|null $bank  Bank account array.
	 * @return array<string, string>
	 */
	public static function build_vars( WC_Order $order, ?array $bank = null ): array {
		$customer_name = trim(
			$order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
		);
		$order_total = wp_strip_all_tags(
			wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) )
		);

		return array(
			'site_name'      => BCASW_Settings::get( 'bcasw_site_name' ) ?: get_bloginfo( 'name' ),
			'order_number'   => (string) $order->get_order_number(),
			'customer_name'  => $customer_name ?: __( 'Customer', 'bcas-to-whatsapp' ),
			'order_total'    => $order_total,
			'currency'       => $order->get_currency(),
			'bank_name'      => $bank['bank_name']      ?? '',
			'account_name'   => $bank['account_name']   ?? '',
			'account_number' => $bank['account_number'] ?? '',
			'sort_code'      => $bank['sort_code']      ?? '',
			'iban'           => $bank['iban']            ?? '',
			'swift_bic'      => $bank['swift_bic']      ?? '',
			'billing_phone'  => $order->get_billing_phone(),
			'billing_email'  => $order->get_billing_email(),
		);
	}

	/**
	 * Replace {placeholders} in a string with provided values.
	 * Unknown placeholders are stripped.
	 *
	 * @param string               $template  Template string.
	 * @param array<string,string> $vars      Key→value map (keys without braces).
	 * @return string
	 */
	public static function replace( string $template, array $vars ): string {
		// Replace known placeholders.
		$search  = array_map( fn( $k ) => '{' . $k . '}', array_keys( $vars ) );
		$replace = array_values( $vars );
		$output  = str_replace( $search, $replace, $template );

		// Remove any remaining unknown {placeholders}.
		$output = preg_replace( '/\{[a-z0-9_]+\}/i', '', $output );

		return $output;
	}

	/**
	 * Build a WhatsApp URL (wa.me) from a phone number and rendered message.
	 *
	 * @param string $raw_number Phone number (may include spaces, +, dashes).
	 * @param string $message    Rendered message text.
	 * @return string
	 */
	public static function whatsapp_url( string $raw_number, string $message ): string {
		$number = preg_replace( '/[^0-9]/', '', $raw_number );
		return 'https://wa.me/' . rawurlencode( $number ) . '?text=' . rawurlencode( $message );
	}
}
