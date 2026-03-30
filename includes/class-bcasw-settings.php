<?php

/**
 * Settings manager — registers all plugin options and provides a static get() helper.
 *
 * @package BCAS_To_WhatsApp
 */

if (! defined('ABSPATH')) {
	exit;
}

class BCASW_Settings
{

	// ─── Default values ───────────────────────────────────────────────────────

	private static array $defaults = array(

		// General
		'bcasw_enabled'            => '1',
		'bcasw_site_name'          => '',
		'bcasw_bacs_only'          => '1',
		'bcasw_enable_popup'       => '1',
		'bcasw_enable_inline'      => '1',
		'bcasw_enable_copy'        => '1',
		'bcasw_enable_email_sync'  => '1',

		// WhatsApp
		'bcasw_wa_customer_number' => '',
		'bcasw_wa_admin_number'    => '',
		'bcasw_wa_customer_tpl'    => "Hello, I have made payment for Order #{order_number} on {site_name}.\nCustomer: {customer_name}\nAmount: {order_total}\nBank: {bank_name}\nAccount Name: {account_name}\nAccount Number: {account_number}\nPlease find my receipt attached.",
		'bcasw_wa_admin_tpl'       => "Order #{order_number} — {customer_name} | {billing_phone} | {billing_email}\nTotal: {order_total}",

		// Popup
		'bcasw_popup_title'        => 'Complete Your Payment',
		'bcasw_popup_body'         => 'Please transfer the exact amount to the bank account below, then send your payment receipt on WhatsApp.',
		'bcasw_popup_btn_label'    => 'Send Receipt on WhatsApp',

		// Instructions
		'bcasw_checkout_desc'      => 'Pay by direct bank transfer. Use your order number as reference. Your order will be processed once payment is confirmed.',
		'bcasw_instr_template'     => "Bank: {bank_name}\nAccount Name: {account_name}\nAccount Number: {account_number}\nSort Code: {sort_code}\n\nUse Order #{order_number} as your payment reference.",
	);

	// ─── Public helpers ───────────────────────────────────────────────────────

	/**
	 * Get a plugin option, falling back to the defined default.
	 *
	 * @param string $key     Option key (without leading underscore).
	 * @param mixed  $default Override default (optional).
	 * @return mixed
	 */
	public static function get(string $key, $default = null)
	{
		if (null === $default) {
			$default = self::$defaults[$key] ?? '';
		}
		return get_option($key, $default);
	}

	/**
	 * Return all default values (used during migration and for blank installs).
	 */
	public static function get_defaults(): array
	{
		return self::$defaults;
	}

	// ─── Hooks ────────────────────────────────────────────────────────────────

	public function init(): void
	{
		add_action('admin_init', array($this, 'register_settings'));
	}

	/**
	 * Register every setting with sanitise callbacks.
	 */
	public function register_settings(): void
	{

		// ── General ─────────────────────────────────────────────────────────

		register_setting('bcasw_general', 'bcasw_enabled',           array('sanitize_callback' => 'absint'));
		register_setting('bcasw_general', 'bcasw_site_name',         array('sanitize_callback' => 'sanitize_text_field'));
		register_setting('bcasw_general', 'bcasw_bacs_only',         array('sanitize_callback' => 'absint'));
		register_setting('bcasw_general', 'bcasw_enable_popup',      array('sanitize_callback' => 'absint'));
		register_setting('bcasw_general', 'bcasw_enable_inline',     array('sanitize_callback' => 'absint'));
		register_setting('bcasw_general', 'bcasw_enable_copy',       array('sanitize_callback' => 'absint'));
		register_setting('bcasw_general', 'bcasw_enable_email_sync', array('sanitize_callback' => 'absint'));

		// ── WhatsApp ─────────────────────────────────────────────────────────

		register_setting('bcasw_whatsapp', 'bcasw_wa_customer_number', array('sanitize_callback' => array(__CLASS__, 'sanitize_phone')));
		register_setting('bcasw_whatsapp', 'bcasw_wa_admin_number',    array('sanitize_callback' => array(__CLASS__, 'sanitize_phone')));
		register_setting('bcasw_whatsapp', 'bcasw_wa_customer_tpl',    array('sanitize_callback' => 'sanitize_textarea_field'));
		register_setting('bcasw_whatsapp', 'bcasw_wa_admin_tpl',       array('sanitize_callback' => 'sanitize_textarea_field'));

		// ── Popup ─────────────────────────────────────────────────────────────

		register_setting('bcasw_popup', 'bcasw_popup_title',     array('sanitize_callback' => 'sanitize_text_field'));
		register_setting('bcasw_popup', 'bcasw_popup_body',      array('sanitize_callback' => 'sanitize_textarea_field'));
		register_setting('bcasw_popup', 'bcasw_popup_btn_label', array('sanitize_callback' => 'sanitize_text_field'));

		// ── Instructions ──────────────────────────────────────────────────────

		register_setting('bcasw_instructions', 'bcasw_checkout_desc',   array('sanitize_callback' => 'sanitize_textarea_field'));
		register_setting('bcasw_instructions', 'bcasw_instr_template',  array('sanitize_callback' => 'sanitize_textarea_field'));

		// Note: 'bcasw_banks' is handled separately by BCASW_Bank_Accounts.
	}

	/**
	 * Strip everything except digits and leading +.
	 */
	public static function sanitize_phone(string $number): string
	{
		$stripped = preg_replace('/[^0-9+]/', '', $number);
		// Collapse any internal + signs.
		return preg_replace('/(?<!^)\+/', '', $stripped);
	}
}
