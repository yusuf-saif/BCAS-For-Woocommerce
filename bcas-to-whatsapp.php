<?php

/**
 * Plugin Name: BCAS to WhatsApp
 * Plugin URI:  https://saifyusuf.xyz
 * Description: WooCommerce Bank Transfer (BACS) helper — admin-configurable bank details, custom order status, WhatsApp receipt flow, and mobile-friendly thank-you experience.
 * Version:     2.0.1
 * Author:      S A Yusuf
 * Author URI:  https://saifyusuf.xyz
 * Text Domain: bcas-to-whatsapp
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 *
 * @package BCAS_To_WhatsApp
 */

if (! defined('ABSPATH')) {
	exit;
}

// ─── Constants ────────────────────────────────────────────────────────────────

define('BCASW_VERSION', '2.0.1');
define('BCASW_FILE',    __FILE__);
define('BCASW_DIR',     plugin_dir_path(__FILE__));
define('BCASW_URL',     plugin_dir_url(__FILE__));

// ─── Declare WooCommerce HPOS compatibility ───────────────────────────────────

add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
});

// ─── Autoloader ───────────────────────────────────────────────────────────────

/**
 * Simple PSR-4-style autoloader for BCASW_ classes.
 */
spl_autoload_register(function ($class) {
	if (strpos($class, 'BCASW_') !== 0) {
		return;
	}

	// Convert class name to file name: BCASW_Foo_Bar → class-bcasw-foo-bar.php
	$file_name = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

	$locations = array(
		BCASW_DIR . 'includes/' . $file_name,
		BCASW_DIR . 'admin/'    . $file_name,
	);

	foreach ($locations as $path) {
		if (file_exists($path)) {
			require_once $path;
			return;
		}
	}
});

// ─── Bootstrap ────────────────────────────────────────────────────────────────

add_action('plugins_loaded', function () {
	if (! class_exists('WooCommerce')) {
		add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__('BCAS to WhatsApp requires WooCommerce to be installed and active.', 'bcas-to-whatsapp')
				. '</p></div>';
		});
		return;
	}

	// Run one-time v1→v2 migration if needed.
	BCASW_Plugin::maybe_migrate();

	// Start the plugin.
	BCASW_Plugin::get_instance();
}, 0);
