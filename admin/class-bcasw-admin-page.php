<?php
/**
 * Admin settings page — registers the menu and handles form saving.
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Admin_Page {

	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_post_bcasw_save', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// ─── Menu ─────────────────────────────────────────────────────────────────

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'BCAS to WhatsApp', 'bcas-to-whatsapp' ),
			__( 'BCAS to WhatsApp', 'bcas-to-whatsapp' ),
			'manage_woocommerce',
			'bcasw-settings',
			array( $this, 'render_page' )
		);
	}

	// ─── Assets ───────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_bcasw-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'bcasw-admin',
			BCASW_URL . 'assets/css/admin.css',
			array(),
			BCASW_VERSION
		);
		wp_enqueue_script(
			'bcasw-admin',
			BCASW_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			BCASW_VERSION,
			true // footer
		);
		wp_localize_script(
			'bcasw-admin',
			'bcaswAdmin',
			array(
				'confirmDelete' => __( 'Are you sure you want to remove this bank account?', 'bcas-to-whatsapp' ),
				'newAccountTpl' => $this->get_blank_account_template(),
			)
		);
	}

	// ─── Page render ──────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bcas-to-whatsapp' ) );
		}

		$active_tab = sanitize_key( $_GET['tab'] ?? 'general' );
		$saved      = isset( $_GET['saved'] ) && '1' === $_GET['saved'];

		include BCASW_DIR . 'admin/views/settings-page.php';
	}

	// ─── Save handler ─────────────────────────────────────────────────────────

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bcas-to-whatsapp' ) );
		}

		check_admin_referer( 'bcasw_save_settings', 'bcasw_nonce' );

		$tab = sanitize_key( $_POST['bcasw_active_tab'] ?? 'general' );

		switch ( $tab ) {
			case 'general':
				$this->save_general();
				break;
			case 'whatsapp':
				$this->save_whatsapp();
				break;
			case 'popup':
				$this->save_popup();
				break;
			case 'instructions':
				$this->save_instructions();
				break;
			case 'banks':
				$this->save_banks();
				break;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bcasw-settings&tab=' . $tab . '&saved=1' ) );
		exit;
	}

	// ─── Tab save helpers ─────────────────────────────────────────────────────

	private function save_general(): void {
		update_option( 'bcasw_enabled',           absint( $_POST['bcasw_enabled'] ?? 0 ) );
		update_option( 'bcasw_site_name',         sanitize_text_field( $_POST['bcasw_site_name'] ?? '' ) );
		update_option( 'bcasw_bacs_only',         absint( $_POST['bcasw_bacs_only'] ?? 0 ) );
		update_option( 'bcasw_enable_popup',      absint( $_POST['bcasw_enable_popup'] ?? 0 ) );
		update_option( 'bcasw_enable_inline',     absint( $_POST['bcasw_enable_inline'] ?? 0 ) );
		update_option( 'bcasw_enable_copy',       absint( $_POST['bcasw_enable_copy'] ?? 0 ) );
		update_option( 'bcasw_enable_email_sync', absint( $_POST['bcasw_enable_email_sync'] ?? 0 ) );
	}

	private function save_whatsapp(): void {
		update_option( 'bcasw_wa_customer_number', BCASW_Settings::sanitize_phone( $_POST['bcasw_wa_customer_number'] ?? '' ) );
		update_option( 'bcasw_wa_admin_number',    BCASW_Settings::sanitize_phone( $_POST['bcasw_wa_admin_number'] ?? '' ) );
		update_option( 'bcasw_wa_customer_tpl',    sanitize_textarea_field( $_POST['bcasw_wa_customer_tpl'] ?? '' ) );
		update_option( 'bcasw_wa_admin_tpl',       sanitize_textarea_field( $_POST['bcasw_wa_admin_tpl'] ?? '' ) );
	}

	private function save_popup(): void {
		update_option( 'bcasw_popup_title',     sanitize_text_field( $_POST['bcasw_popup_title'] ?? '' ) );
		update_option( 'bcasw_popup_body',      sanitize_textarea_field( $_POST['bcasw_popup_body'] ?? '' ) );
		update_option( 'bcasw_popup_btn_label', sanitize_text_field( $_POST['bcasw_popup_btn_label'] ?? '' ) );
	}

	private function save_instructions(): void {
		update_option( 'bcasw_checkout_desc',  sanitize_textarea_field( $_POST['bcasw_checkout_desc'] ?? '' ) );
		update_option( 'bcasw_instr_template', sanitize_textarea_field( $_POST['bcasw_instr_template'] ?? '' ) );
	}

	private function save_banks(): void {
		$raw_banks = $_POST['bcasw_bank'] ?? array();

		if ( ! is_array( $raw_banks ) ) {
			BCASW_Bank_Accounts::save_all( array() );
			return;
		}

		$default_id = sanitize_text_field( $_POST['bcasw_default_bank'] ?? '' );
		$accounts   = array();

		foreach ( $raw_banks as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id                     = sanitize_text_field( $entry['id'] ?? '' );
			$entry['id']            = $id ?: BCASW_Bank_Accounts::generate_id();
			$entry['is_default']    = ( $default_id && $default_id === $id );
			$accounts[]             = $entry;
		}

		BCASW_Bank_Accounts::save_all( $accounts );
	}

	// ─── Helpers for admin JS ─────────────────────────────────────────────────

	/**
	 * Return HTML for a blank bank account row (used by admin JS repeater).
	 */
	public function get_blank_account_template(): string {
		ob_start();
		$account = array(
			'id'             => '__NEW__',
			'label'          => '',
			'bank_name'      => '',
			'account_name'   => '',
			'account_number' => '',
			'sort_code'      => '',
			'iban'           => '',
			'swift_bic'      => '',
			'is_default'     => false,
		);
		include BCASW_DIR . 'admin/views/bank-account-row.php';
		return ob_get_clean();
	}

	/**
	 * Render a bank account row — called from the settings view.
	 */
	public static function render_bank_row( array $account ): void {
		include BCASW_DIR . 'admin/views/bank-account-row.php';
	}
}
