<?php
/**
 * Main plugin orchestrator.
 * Instantiates all sub-classes and registers their hooks.
 * Also houses the v1 → v2 migration routine.
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Plugin {

	private static ?BCASW_Plugin $instance = null;

	// Sub-class instances.
	private BCASW_Settings       $settings;
	private BCASW_Order_Status   $order_status;
	private BCASW_Order_Actions  $order_actions;
	private BCASW_Bank_Selector  $bank_selector;
	private BCASW_Frontend       $frontend;
	private BCASW_Email          $email;

	// ─── Singleton ────────────────────────────────────────────────────────────

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings      = new BCASW_Settings();
		$this->order_status  = new BCASW_Order_Status();
		$this->order_actions = new BCASW_Order_Actions();
		$this->bank_selector = new BCASW_Bank_Selector();
		$this->frontend      = new BCASW_Frontend();
		$this->email         = new BCASW_Email();

		$this->register_hooks();
	}

	private function register_hooks(): void {
		// Settings API registration.
		$this->settings->init();

		// Custom order status.
		$this->order_status->init();

		// Admin actions + meta box.
		if ( is_admin() ) {
			$this->order_actions->init();

			// Admin settings menu.
			$admin_page = new BCASW_Admin_Page();
			$admin_page->init();
		}

		// Bank selector AJAX.
		$this->bank_selector->init();

		// Front-end rendering.
		if ( ! is_admin() ) {
			$this->frontend->init();
		}

		// Email integration.
		$this->email->init();

		// Plugin action links.
		add_filter(
			'plugin_action_links_' . plugin_basename( BCASW_FILE ),
			array( $this, 'add_action_links' )
		);
	}

	// ─── Plugin action links ──────────────────────────────────────────────────

	public function add_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=bcasw-settings' ) ) . '">'
			. esc_html__( 'Settings', 'bcas-to-whatsapp' )
			. '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	// ─── v1 → v2 Migration ────────────────────────────────────────────────────

	/**
	 * One-time migration from v1 (hardcoded) to v2 (settings-based).
	 * Runs only when the stored version is older than 2.0.0.
	 * Does NOT delete any existing data.
	 */
	public static function maybe_migrate(): void {
		$stored_version = get_option( 'bcasw_version', '1.0.0' );

		if ( version_compare( $stored_version, '2.0.0', '>=' ) ) {
			return;
		}

		// ── Seed defaults from v1 hardcoded values ──────────────────────────

		$defaults = BCASW_Settings::get_defaults();

		foreach ( $defaults as $key => $value ) {
			// Only add if option doesn't already exist (don't overwrite admin changes).
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}

		// ── Seed a default bank account if none exist ────────────────────────

		if ( BCASW_Bank_Accounts::count() === 0 ) {
			$default_account = array(
				'id'             => BCASW_Bank_Accounts::generate_id(),
				'label'          => 'Primary Account',
				'bank_name'      => '',   // Admin will fill in.
				'account_name'   => '',
				'account_number' => '',
				'sort_code'      => '',
				'iban'           => '',
				'swift_bic'      => '',
				'is_default'     => true,
			);
			BCASW_Bank_Accounts::save_all( array( $default_account ) );
		}

		update_option( 'bcasw_version', BCASW_VERSION );
	}
}
