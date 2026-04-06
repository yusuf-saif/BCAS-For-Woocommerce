<?php
/**
 * Bank account CRUD helpers.
 *
 * Bank accounts are stored as a JSON-encoded array in the 'bcasw_banks' option.
 * Each account is an associative array:
 *
 *   [
 *     'id'           => string (UUID v4),
 *     'label'        => string,
 *     'bank_name'    => string,
 *     'account_name' => string,
 *     'account_number'=> string,
 *     'sort_code'    => string,
 *     'iban'         => string,
 *     'swift_bic'    => string,
 *     'is_default'   => bool,
 *   ]
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Bank_Accounts {

	private const OPTION_KEY = 'bcasw_banks';

	// ─── Read ─────────────────────────────────────────────────────────────────

	/**
	 * Return all bank accounts (may be empty array).
	 */
	public static function get_all(): array {
		$raw = get_option( self::OPTION_KEY, '[]' );
		$accounts = json_decode( $raw, true );
		return is_array( $accounts ) ? $accounts : array();
	}

	/**
	 * Return the default account (first with is_default=true, or just the first).
	 */
	public static function get_default(): ?array {
		$accounts = self::get_all();
		if ( empty( $accounts ) ) {
			return null;
		}
		foreach ( $accounts as $account ) {
			if ( ! empty( $account['is_default'] ) ) {
				return $account;
			}
		}
		return $accounts[0];
	}

	/**
	 * Return a single account by its UUID id.
	 */
	public static function get_by_id( string $id ): ?array {
		foreach ( self::get_all() as $account ) {
			if ( isset( $account['id'] ) && $account['id'] === $id ) {
				return $account;
			}
		}
		return null;
	}

	/**
	 * Return how many accounts are configured.
	 */
	public static function count(): int {
		return count( self::get_all() );
	}

	/**
	 * Return true if there is at least one account with real bank details.
	 *
	 * A bank account is considered configured only if bank_name, account_name,
	 * and account_number are all non-empty, non-whitespace, and not known
	 * placeholder text.
	 */
	public static function is_configured(): bool {
		foreach ( self::get_all() as $a ) {
			if ( self::is_account_valid( $a ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check whether a single bank account has valid, real data.
	 *
	 * Rejects empty strings, whitespace-only values, and known placeholder text.
	 * This definition is used consistently across admin notices, sync, rendering.
	 *
	 * @param array $account Bank account data array.
	 * @return bool
	 */
	public static function is_account_valid( array $account ): bool {
		$placeholders = array(
			'your bank name',
			'your account name',
			'your account number',
		);

		$bank_name      = trim( $account['bank_name'] ?? '' );
		$account_name   = trim( $account['account_name'] ?? '' );
		$account_number = trim( $account['account_number'] ?? '' );

		// All three required fields must be present.
		if ( '' === $bank_name || '' === $account_name || '' === $account_number ) {
			return false;
		}

		// Reject known placeholder strings.
		if (
			in_array( strtolower( $bank_name ), $placeholders, true ) ||
			in_array( strtolower( $account_name ), $placeholders, true ) ||
			in_array( strtolower( $account_number ), $placeholders, true )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Return the default account only if it passes the configured validation.
	 * Returns null if the default account is a placeholder or has missing fields.
	 */
	public static function get_default_if_configured(): ?array {
		$default = self::get_default();
		if ( ! $default ) {
			return null;
		}
		return self::is_account_valid( $default ) ? $default : null;
	}

	// ─── Write ────────────────────────────────────────────────────────────────

	/**
	 * Sanitise and persist an array of accounts.
	 *
	 * @param array $accounts Raw input (e.g. from $_POST).
	 */
	public static function save_all( array $accounts ): void {
		$clean = array();

		foreach ( $accounts as $a ) {
			$clean[] = self::sanitize_account( $a );
		}

		// Ensure exactly one default. If none is set, mark the first.
		$has_default = false;
		foreach ( $clean as &$a ) {
			if ( $a['is_default'] ) {
				$has_default = true;
				break;
			}
		}
		unset( $a );

		if ( ! $has_default && ! empty( $clean ) ) {
			$clean[0]['is_default'] = true;
		}

		update_option( self::OPTION_KEY, wp_json_encode( $clean ) );

		// Keep WooCommerce native BACS settings in sync.
		self::sync_to_woocommerce( $clean );
	}

	/**
	 * Sync the DEFAULT bank account into WooCommerce's woocommerce_bacs_accounts option.
	 *
	 * WooCommerce BACS is a COMPATIBILITY MIRROR only.
	 * The plugin may manage multiple bank accounts; only the default one is ever
	 * written to WooCommerce native BACS settings. Additional accounts are managed
	 * exclusively by this plugin and are never exposed to WooCommerce directly.
	 *
	 * This keeps the native WC BACS settings page and order emails consistent with
	 * the default bank without requiring the admin to maintain both places manually.
	 *
	 * Sync is skipped if the default account fails validation — invalid or placeholder
	 * data is never written to WooCommerce native BACS settings.
	 *
	 * @param array $accounts Sanitised accounts array (full list, not just default).
	 */
	private static function sync_to_woocommerce( array $accounts ): void {
		$default = null;
		foreach ( $accounts as $a ) {
			if ( ! empty( $a['is_default'] ) ) {
				$default = $a;
				break;
			}
		}
		if ( ! $default && ! empty( $accounts ) ) {
			$default = $accounts[0];
		}
		if ( ! $default ) {
			self::debug_log( 'Bank sync skipped: no accounts exist.' );
			return;
		}

		// Validate before syncing — never push invalid data to WC BACS.
		if ( ! self::is_account_valid( $default ) ) {
			self::debug_log( 'Bank sync skipped: default account is invalid or placeholder.' );
			return;
		}

		// Mirror the default account only. WC BACS accepts a flat array of account rows;
		// we always write exactly one entry (the default). Other accounts exist only here.
		$wc_account = array(
			array(
				'account_name'   => $default['account_name']   ?? '',
				'account_number' => $default['account_number'] ?? '',
				'bank_name'      => $default['bank_name']      ?? '',
				'sort_code'      => $default['sort_code']      ?? '',
				'iban'           => $default['iban']            ?? '',
				'bic'            => $default['swift_bic']      ?? '',
			),
		);
		update_option( 'woocommerce_bacs_accounts', $wc_account );
		self::debug_log( 'Bank sync completed: default account mirrored to WC BACS.' );
	}


	/**
	 * Log a debug message when WP_DEBUG is enabled.
	 *
	 * @param string $message Message to log.
	 */
	private static function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[BCAS to WhatsApp] ' . $message );
		}
	}

	/**
	 * Generate a UUID v4 string for new accounts.
	 */
	public static function generate_id(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}

	// ─── Sanitise ─────────────────────────────────────────────────────────────

	private static function sanitize_account( array $a ): array {
		return array(
			'id'             => sanitize_text_field( $a['id'] ?? self::generate_id() ),
			'label'          => sanitize_text_field( $a['label'] ?? '' ),
			'bank_name'      => sanitize_text_field( $a['bank_name'] ?? '' ),
			'account_name'   => sanitize_text_field( $a['account_name'] ?? '' ),
			'account_number' => sanitize_text_field( $a['account_number'] ?? '' ),
			'sort_code'      => sanitize_text_field( $a['sort_code'] ?? '' ),
			'iban'           => sanitize_text_field( $a['iban'] ?? '' ),
			'swift_bic'      => sanitize_text_field( $a['swift_bic'] ?? '' ),
			'is_default'     => ! empty( $a['is_default'] ),
		);
	}
}
