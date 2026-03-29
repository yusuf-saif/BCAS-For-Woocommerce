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
