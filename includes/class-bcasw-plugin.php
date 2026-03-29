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

		// Bank selector hooks (checkout rendering + order meta save).
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

		// Admin notices: no-bank warning + post-migration prompt.
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
		}
	}

	// ─── Admin notices ────────────────────────────────────────────────────────

	/**
	 * Show admin notices.
	 * (a) Warning if no bank accounts are configured.
	 * (b) One-time post-migration prompt to review settings.
	 */
	public function show_admin_notices(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Warning: no bank accounts OR accounts exist but are still unconfigured placeholders.
		if ( ! BCASW_Bank_Accounts::is_configured() ) {
			$url = admin_url( 'admin.php?page=bcasw-settings&tab=banks' );
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo wp_kses(
				sprintf(
					/* translators: %s: settings URL */
					__( '<strong>BCAS to WhatsApp:</strong> No bank account details configured yet. <a href="%s">Add your bank details</a> so customers know where to transfer.', 'bcas-to-whatsapp' ),
					esc_url( $url )
				),
				array( 'strong' => array(), 'a' => array( 'href' => array() ) )
			);
			echo '</p></div>';
		}

		// One-time migration notice.
		if ( get_option( 'bcasw_migrated_notice' ) ) {
			delete_option( 'bcasw_migrated_notice' );
			$url = admin_url( 'admin.php?page=bcasw-settings' );
			echo '<div class="notice notice-info is-dismissible"><p>';
			echo wp_kses(
				sprintf(
					/* translators: %s: settings URL */
					__( '<strong>BCAS to WhatsApp v2:</strong> Plugin upgraded. Please <a href="%s">review your settings</a>.', 'bcas-to-whatsapp' ),
					esc_url( $url )
				),
				array( 'strong' => array(), 'a' => array( 'href' => array() ) )
			);
			echo '</p></div>';
		}
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

		// ── v2.0.1 repair — runs on v2.0.0 installs with blank bank data ────────
		// If the stored version is exactly 2.0.0 and the default account is still
		// a blank placeholder (e.g. created by the old empty-seed migration),
		// try to re-seed from WooCommerce BACS accounts.
		if ( '2.0.0' === $stored_version && ! BCASW_Bank_Accounts::is_configured() ) {
			$wc_accounts = get_option( 'woocommerce_bacs_accounts', array() );

			if ( ! empty( $wc_accounts ) && is_array( $wc_accounts ) ) {
				$repaired = array();
				foreach ( $wc_accounts as $i => $wc ) {
					$repaired[] = array(
						'id'             => BCASW_Bank_Accounts::generate_id(),
						'label'          => sanitize_text_field( $wc['bank_name'] ?? ( 'Account ' . ( $i + 1 ) ) ),
						'bank_name'      => sanitize_text_field( $wc['bank_name']      ?? '' ),
						'account_name'   => sanitize_text_field( $wc['account_name']   ?? '' ),
						'account_number' => sanitize_text_field( $wc['account_number'] ?? '' ),
						'sort_code'      => sanitize_text_field( $wc['sort_code']      ?? '' ),
						'iban'           => sanitize_text_field( $wc['iban']           ?? '' ),
						'swift_bic'      => sanitize_text_field( $wc['bic']            ?? '' ),
						'is_default'     => ( 0 === $i ),
					);
				}
				// Only save if at least one imported account has real data.
				$has_real = false;
				foreach ( $repaired as $r ) {
					if ( ! empty( $r['account_number'] ) && ! empty( $r['bank_name'] ) ) {
						$has_real = true;
						break;
					}
				}
				if ( $has_real ) {
					BCASW_Bank_Accounts::save_all( $repaired );
				}
			}
			// Bump to 2.0.1 so this repair block doesn't repeat every request.
			update_option( 'bcasw_version', '2.0.1' );
			return;
		}

		// Already at 2.0.0 or later with real data — nothing to do.
		if ( version_compare( $stored_version, '2.0.0', '>=' ) ) {
			return;
		}

		// ── Fresh v1 → v2 upgrade ──────────────────────────────────────────────

		// Seed option defaults.
		foreach ( BCASW_Settings::get_defaults() as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}

		// Seed bank accounts.
		if ( BCASW_Bank_Accounts::count() === 0 ) {

			$wc_accounts = get_option( 'woocommerce_bacs_accounts', array() );

			if ( ! empty( $wc_accounts ) && is_array( $wc_accounts ) ) {
				// Import existing WooCommerce BACS accounts.
				$accounts = array();
				foreach ( $wc_accounts as $i => $wc ) {
					$accounts[] = array(
						'id'             => BCASW_Bank_Accounts::generate_id(),
						'label'          => sanitize_text_field( $wc['bank_name'] ?? ( 'Account ' . ( $i + 1 ) ) ),
						'bank_name'      => sanitize_text_field( $wc['bank_name']      ?? '' ),
						'account_name'   => sanitize_text_field( $wc['account_name']   ?? '' ),
						'account_number' => sanitize_text_field( $wc['account_number'] ?? '' ),
						'sort_code'      => sanitize_text_field( $wc['sort_code']      ?? '' ),
						'iban'           => sanitize_text_field( $wc['iban']           ?? '' ),
						'swift_bic'      => sanitize_text_field( $wc['bic']            ?? '' ),
						'is_default'     => ( 0 === $i ),
					);
				}
				BCASW_Bank_Accounts::save_all( $accounts );

			} else {
				// Fallback: create an empty placeholder account.
				// Admin will see the "no bank details" notice and fill it in.
				BCASW_Bank_Accounts::save_all( array(
					array(
						'id'             => BCASW_Bank_Accounts::generate_id(),
						'label'          => 'Bank Account',
						'bank_name'      => 'Your Bank Name',
						'account_name'   => 'Your Account Name',
						'account_number' => 'Your Account Number',
						'sort_code'      => '',
						'iban'           => '',
						'swift_bic'      => '',
						'is_default'     => true,
					),
				) );
			}
		}

		// Prompt admin to review settings after migration.
		update_option( 'bcasw_migrated_notice', '1' );
		update_option( 'bcasw_version', BCASW_VERSION );
	}
}
