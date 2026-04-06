<?php

/**
 * Main plugin orchestrator.
 * Instantiates all sub-classes and registers their hooks.
 * Also houses the v1 → v2 migration routine and the v2.2 readiness helpers.
 *
 * @package BCAS_To_WhatsApp
 *
 * Data integrity contract (v2.2):
 *   - Plugin settings  = source of truth for active configuration.
 *   - Order snapshot   = immutable historical truth per order.
 *   - WooCommerce BACS = compatibility mirror of the DEFAULT bank only.
 */

if (! defined('ABSPATH')) {
	exit;
}

class BCASW_Plugin
{

	private static ?BCASW_Plugin $instance = null;

	// Sub-class instances.
	private BCASW_Settings       $settings;
	private BCASW_Order_Status   $order_status;
	private BCASW_Order_Actions  $order_actions;
	private BCASW_Bank_Selector  $bank_selector;
	private BCASW_Frontend       $frontend;
	private BCASW_Email          $email;

	// ─── Singleton ────────────────────────────────────────────────────────────

	public static function get_instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		$this->settings      = new BCASW_Settings();
		$this->order_status  = new BCASW_Order_Status();
		$this->order_actions = new BCASW_Order_Actions();
		$this->bank_selector = new BCASW_Bank_Selector();
		$this->frontend      = new BCASW_Frontend();
		$this->email         = new BCASW_Email();

		$this->register_hooks();
	}

	private function register_hooks(): void
	{
		// Settings API registration — always active so admin can manage options.
		$this->settings->init();

		// Custom order status — always active so existing orders keep their status.
		$this->order_status->init();

		// Admin actions + meta box — always active in admin.
		if (is_admin()) {
			$this->order_actions->init();

			// Admin settings menu.
			$admin_page = new BCASW_Admin_Page();
			$admin_page->init();
		}

		// ─── Customer-facing features respect the master enable/disable toggle ──
		if (BCASW_Settings::get('bcasw_enabled')) {
			// Bank selector hooks (checkout rendering + order meta save).
			$this->bank_selector->init();

			// Front-end rendering (thank-you page, popup, checkout description).
			if (! is_admin()) {
				$this->frontend->init();
			}

			// Email integration (instruction injection into BACS emails).
			$this->email->init();
		}

		// Plugin action links — always visible.
		add_filter(
			'plugin_action_links_' . plugin_basename(BCASW_FILE),
			array($this, 'add_action_links')
		);

		// Admin notices: no-bank warning + post-migration prompt.
		if (is_admin()) {
			add_action('admin_notices', array($this, 'show_admin_notices'));
		}
	}

	// ─── Admin notices ────────────────────────────────────────────────────────

	/**
	 * Show admin notices.
	 *
	 * (a) BACS auto-enabled confirmation — one-time, transient-driven.
	 * (b) Setup incomplete summary — plugin enabled but is_ready() = false.
	 * (c) No bank accounts configured — prompt to add first bank.
	 * (d) Default bank account incomplete — sync was skipped, explain why.
	 * (e) Post-migration prompt — one-time after v1 → v2 upgrade.
	 */
	public function show_admin_notices(): void
	{
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Notice (a): WooCommerce BACS was just auto-enabled by this plugin.
		// Consumed from a short-lived transient set by maybe_auto_enable_bacs().
		if ( get_transient( 'bcasw_bacs_auto_enabled' ) ) {
			delete_transient( 'bcasw_bacs_auto_enabled' );
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo wp_kses(
				__( '<strong>BCAS to WhatsApp:</strong> WooCommerce Direct Bank Transfer has been enabled automatically — your setup is complete.', 'bcas-to-whatsapp' ),
				array( 'strong' => array() )
			);
			echo '</p></div>';
		}

		// Notice (b): Plugin is on but not ready — show a concise summary of what is missing.
		// Only shown on BCAS settings pages and WooCommerce pages to avoid admin noise.
		// Suppressed when notice (c) is already showing (no bank accounts at all).
		if (
			BCASW_Settings::get( 'bcasw_enabled' ) &&
			! self::is_ready() &&
			BCASW_Bank_Accounts::is_configured()
		) {
			$screen = get_current_screen();
			$show   = $screen && (
				strpos( $screen->id, 'bcasw' ) !== false ||
				strpos( $screen->id, 'woocommerce' ) !== false
			);

			if ( $show ) {
				$missing = array();

				// bcasw_wa_customer_number = the number customers send payment receipts to.
				if ( ! BCASW_Settings::is_valid_wa_number( BCASW_Settings::get( 'bcasw_wa_customer_number' ) ) ) {
					$wa_url    = admin_url( 'admin.php?page=bcasw-settings&tab=whatsapp' );
					/* translators: %s: WhatsApp settings URL */
					$missing[] = sprintf(
						__( '<a href="%s">Store WhatsApp Number</a> is missing or invalid', 'bcas-to-whatsapp' ),
						esc_url( $wa_url )
					);
				}

				if ( ! class_exists( 'WC_Gateway_BACS' ) ) {
					$missing[] = __( 'WooCommerce Direct Bank Transfer gateway could not be found', 'bcas-to-whatsapp' );
				}

				if ( ! empty( $missing ) ) {
					echo '<div class="notice notice-warning is-dismissible"><p>';
					echo wp_kses(
						'<strong>' . __( 'BCAS to WhatsApp — setup incomplete:', 'bcas-to-whatsapp' ) . '</strong> '
						. implode( '; ', $missing ) . '.',
						array( 'strong' => array(), 'a' => array( 'href' => array() ) )
					);
					echo '</p></div>';
				}
			}
		}

		// Notice (c): no accounts saved at all — prompt admin to create their first bank.
		if ( BCASW_Bank_Accounts::count() === 0 ) {
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

		// Notice (d): accounts exist but the default is incomplete — WC BACS sync was skipped.
		// The merchant has started adding a bank but has not finished filling it in.
		// Explain the consequence (no sync) so they understand why the BACS settings page
		// did not update.
		if ( BCASW_Bank_Accounts::count() > 0 ) {
			$default = BCASW_Bank_Accounts::get_default();
			if ( $default && ! BCASW_Bank_Accounts::is_account_valid( $default ) ) {
				$url = admin_url( 'admin.php?page=bcasw-settings&tab=banks' );
				echo '<div class="notice notice-warning is-dismissible"><p>';
				echo wp_kses(
					sprintf(
						/* translators: %s: settings URL */
						__( '<strong>BCAS to WhatsApp:</strong> Your default bank account is incomplete, so WooCommerce Direct Bank Transfer settings were not synced. <a href="%s">Complete the default bank details</a>.', 'bcas-to-whatsapp' ),
						esc_url( $url )
					),
					array( 'strong' => array(), 'a' => array( 'href' => array() ) )
				);
				echo '</p></div>';
			}
		}

		// Notice (e): one-time post-migration prompt (v1 → v2 upgrade).
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

	public function add_action_links(array $links): array
	{
		$settings_link = '<a href="' . esc_url(admin_url('admin.php?page=bcasw-settings')) . '">'
			. esc_html__('Settings', 'bcas-to-whatsapp')
			. '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	// ─── Readiness check ──────────────────────────────────────────────────────

	/**
	 * Returns true only when the plugin is fully configured and ready to operate.
	 *
	 * All four conditions must be met:
	 *   1. Plugin enabled toggle is on.
	 *   2. At least one valid bank account exists.
	 *   3. Store WhatsApp number is valid (≥10 digits).
	 *   4. WooCommerce BACS gateway class is available.
	 *
	 * Use this for admin notices and setup guidance.
	 * Do NOT use it to block normal admin editing or order processing.
	 *
	 * @return bool
	 */
	public static function is_ready(): bool
	{
		// Condition 1: plugin toggle.
		if ( ! BCASW_Settings::get( 'bcasw_enabled' ) ) {
			return false;
		}

		// Condition 2: at least one valid bank account.
		if ( ! BCASW_Bank_Accounts::is_configured() ) {
			return false;
		}

		// Condition 3: store WhatsApp number must be valid.
		// bcasw_wa_customer_number is the number customers send receipts to.
		$store_wa = BCASW_Settings::get( 'bcasw_wa_customer_number' );
		if ( ! BCASW_Settings::is_valid_wa_number( $store_wa ) ) {
			return false;
		}

		// Condition 4: WooCommerce BACS gateway must exist.
		if ( ! class_exists( 'WC_Gateway_BACS' ) ) {
			return false;
		}

		return true;
	}

	// ─── Auto-enable WooCommerce BACS ─────────────────────────────────────────

	/**
	 * If the plugin is now fully configured (is_ready() = true) and WooCommerce
	 * Direct Bank Transfer (BACS) is currently disabled, enable it automatically.
	 *
	 * This preserves WC compatibility without requiring the admin to manually
	 * visit WooCommerce → Settings → Payments and enable BACS.
	 *
	 * Sets a transient so show_admin_notices() can display a one-time confirmation.
	 *
	 * Call this after saving plugin settings (general tab or banks tab) so that
	 * completing setup automatically activates the underlying gateway.
	 *
	 * Does NOT touch any other gateway settings.
	 */
	public static function maybe_auto_enable_bacs(): void
	{
		// Only run when setup is complete.
		if ( ! self::is_ready() ) {
			return;
		}

		// Read the current WC gateway settings.
		$gateway_settings = get_option( 'woocommerce_bacs_settings', array() );

		// Already enabled — nothing to do.
		if ( isset( $gateway_settings['enabled'] ) && 'yes' === $gateway_settings['enabled'] ) {
			return;
		}

		// Enable BACS without touching any other gateway setting.
		$gateway_settings['enabled'] = 'yes';
		update_option( 'woocommerce_bacs_settings', $gateway_settings );

		// Flag for a one-time admin notice on next page load.
		set_transient( 'bcasw_bacs_auto_enabled', '1', 30 );

		self::log( 'WooCommerce Direct Bank Transfer auto-enabled by plugin (setup is complete).' );
	}

	// ─── v1 → v2 Migration ────────────────────────────────────────────────────

	/**
	 * One-time migration from v1 (hardcoded) to v2 (settings-based).
	 * Runs only when the stored version is older than 2.0.0.
	 * Does NOT delete any existing data.
	 */
	public static function maybe_migrate(): void
	{
		$stored_version = get_option('bcasw_version', '1.0.0');

		// ── v2.0.1 repair — runs on v2.0.0 installs with blank bank data ────────
		// If the stored version is exactly 2.0.0 and the default account is still
		// a blank placeholder (e.g. created by the old empty-seed migration),
		// try to re-seed from WooCommerce BACS accounts.
		if ('2.0.0' === $stored_version && ! BCASW_Bank_Accounts::is_configured()) {
			$wc_accounts = get_option('woocommerce_bacs_accounts', array());

			if (! empty($wc_accounts) && is_array($wc_accounts)) {
				$repaired = array();
				foreach ($wc_accounts as $i => $wc) {
					$repaired[] = array(
						'id'             => BCASW_Bank_Accounts::generate_id(),
						'label'          => sanitize_text_field($wc['bank_name'] ?? ('Account ' . ($i + 1))),
						'bank_name'      => sanitize_text_field($wc['bank_name']      ?? ''),
						'account_name'   => sanitize_text_field($wc['account_name']   ?? ''),
						'account_number' => sanitize_text_field($wc['account_number'] ?? ''),
						'sort_code'      => sanitize_text_field($wc['sort_code']      ?? ''),
						'iban'           => sanitize_text_field($wc['iban']           ?? ''),
						'swift_bic'      => sanitize_text_field($wc['bic']            ?? ''),
						'is_default'     => (0 === $i),
					);
				}
				// Only save if at least one imported account has real data.
				$has_real = false;
				foreach ($repaired as $r) {
					if (! empty($r['account_number']) && ! empty($r['bank_name'])) {
						$has_real = true;
						break;
					}
				}
				if ($has_real) {
					BCASW_Bank_Accounts::save_all($repaired);
				}
			}
			// Bump to 2.0.1 so this repair block doesn't repeat every request.
			update_option('bcasw_version', '2.0.1');
			return;
		}

		// Already at 2.0.0 or later with real data — nothing to do.
		if (version_compare($stored_version, '2.0.0', '>=')) {
			return;
		}

		// ── Fresh v1 → v2 upgrade ──────────────────────────────────────────────

		// Seed option defaults.
		foreach (BCASW_Settings::get_defaults() as $key => $value) {
			if (false === get_option($key)) {
				update_option($key, $value);
			}
		}

		// Seed bank accounts.
		if (BCASW_Bank_Accounts::count() === 0) {

			$wc_accounts = get_option('woocommerce_bacs_accounts', array());

			if (! empty($wc_accounts) && is_array($wc_accounts)) {
				// Import existing WooCommerce BACS accounts.
				$accounts = array();
				foreach ($wc_accounts as $i => $wc) {
					$accounts[] = array(
						'id'             => BCASW_Bank_Accounts::generate_id(),
						'label'          => sanitize_text_field($wc['bank_name'] ?? ('Account ' . ($i + 1))),
						'bank_name'      => sanitize_text_field($wc['bank_name']      ?? ''),
						'account_name'   => sanitize_text_field($wc['account_name']   ?? ''),
						'account_number' => sanitize_text_field($wc['account_number'] ?? ''),
						'sort_code'      => sanitize_text_field($wc['sort_code']      ?? ''),
						'iban'           => sanitize_text_field($wc['iban']           ?? ''),
						'swift_bic'      => sanitize_text_field($wc['bic']            ?? ''),
						'is_default'     => (0 === $i),
					);
				}
				BCASW_Bank_Accounts::save_all($accounts);
			} else {
				// Fallback: create an empty placeholder account.
				// Admin will see the "no bank details" notice and fill it in.
				BCASW_Bank_Accounts::save_all(array(
					array(
						'id'             => BCASW_Bank_Accounts::generate_id(),
						// Label is human-readable only — does not affect sync or validation.
						'label'          => 'Default Account',
						'bank_name'      => '',
						'account_name'   => '',
						'account_number' => '',
						'sort_code'      => '',
						'iban'           => '',
						'swift_bic'      => '',
						'is_default'     => true,
					),
				));
			}
		}

		// Prompt admin to review settings after migration.
		update_option('bcasw_migrated_notice', '1');
		update_option('bcasw_version', BCASW_VERSION);
		self::log('Migration completed from v' . $stored_version . ' to v' . BCASW_VERSION);
	}

	// ─── Debug logging ───────────────────────────────────────────────────────

	/**
	 * Log a debug message when WP_DEBUG is enabled.
	 *
	 * @param string $message Message to log.
	 */
	public static function log(string $message): void
	{
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[BCAS to WhatsApp] ' . $message);
		}
	}
}
