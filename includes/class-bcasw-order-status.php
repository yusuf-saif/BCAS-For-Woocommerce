<?php
/**
 * Custom order status: Awaiting Receipt (wc-awaiting-receipt).
 *
 * Flow: Pending Payment → Awaiting Receipt → Processing → Completed
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BCASW_Order_Status {

	const STATUS_SLUG  = 'awaiting-receipt';         // without wc- prefix
	const STATUS_KEY   = 'wc-awaiting-receipt';      // with wc- prefix

	public function init(): void {
		add_action( 'init',                                         array( $this, 'register_post_status' ) );
		add_filter( 'woocommerce_register_shop_order_statuses',     array( $this, 'register_wc_status' ) );
		add_filter( 'woocommerce_order_statuses',                   array( $this, 'add_to_order_statuses' ) );
		add_action( 'woocommerce_checkout_order_processed',         array( $this, 'set_bacs_order_status' ), 10, 3 );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'allow_payment_from_awaiting' ) );
		add_filter( 'wc_order_statuses',                            array( $this, 'add_to_order_statuses' ) );

		// Bulk actions and admin column colour.
		add_filter( 'bulk_actions-edit-shop_order',                  array( $this, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order',           array( $this, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'woocommerce_admin_order_statuses_default_columns', array( $this, 'add_status_column_colour' ) );
	}

	// ─── Registration ─────────────────────────────────────────────────────────

	/**
	 * Register as a WordPress post status so it persists in the database.
	 */
	public function register_post_status(): void {
		register_post_status(
			self::STATUS_KEY,
			array(
				'label'                     => _x( 'Awaiting Receipt', 'Order status', 'bcas-to-whatsapp' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop(
					'Awaiting Receipt <span class="count">(%s)</span>',
					'Awaiting Receipt <span class="count">(%s)</span>',
					'bcas-to-whatsapp'
				),
			)
		);
	}

	/**
	 * Tell WooCommerce about our custom status (pre-HPOS hook).
	 */
	public function register_wc_status( array $statuses ): array {
		$statuses[ self::STATUS_KEY ] = array(
			'label'                     => _x( 'Awaiting Receipt', 'Order status', 'bcas-to-whatsapp' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop(
				'Awaiting Receipt <span class="count">(%s)</span>',
				'Awaiting Receipt <span class="count">(%s)</span>',
				'bcas-to-whatsapp'
			),
		);
		return $statuses;
	}

	/**
	 * Add to the order status dropdown list in admin.
	 */
	public function add_to_order_statuses( array $statuses ): array {
		// Insert after 'wc-on-hold'.
		$new = array();
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'wc-on-hold' === $key ) {
				$new[ self::STATUS_KEY ] = _x( 'Awaiting Receipt', 'Order status', 'bcas-to-whatsapp' );
			}
		}
		// In case on-hold wasn't found, just append.
		if ( ! isset( $new[ self::STATUS_KEY ] ) ) {
			$new[ self::STATUS_KEY ] = _x( 'Awaiting Receipt', 'Order status', 'bcas-to-whatsapp' );
		}
		return $new;
	}

	// ─── Order assignment ─────────────────────────────────────────────────────

	/**
	 * Move new BACS orders into Awaiting Receipt immediately after checkout.
	 * Fires at priority 10 (bank selector saves at priority 5, before this).
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted checkout data.
	 * @param WC_Order $order       Order object.
	 */
	public function set_bacs_order_status( int $order_id, array $posted_data, WC_Order $order ): void {
		// Apply to ALL BACS orders — no dependency on plugin settings.
		if ( $order->get_payment_method() !== 'bacs' ) {
			return;
		}
		$order->update_status(
			self::STATUS_SLUG,
			__( 'Awaiting customer receipt via WhatsApp.', 'bcas-to-whatsapp' )
		);
	}

	/**
	 * Allow customers to pay again from Awaiting Receipt (e.g. retry link).
	 */
	public function allow_payment_from_awaiting( array $statuses ): array {
		$statuses[] = self::STATUS_SLUG;
		return array_unique( $statuses );
	}

	// ─── Bulk actions ─────────────────────────────────────────────────────────

	public function add_bulk_action( array $actions ): array {
		$actions['mark_awaiting-receipt'] = __( 'Change status to Awaiting Receipt', 'bcas-to-whatsapp' );
		return $actions;
	}

	public function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( 'mark_awaiting-receipt' !== $action ) {
			return $redirect_to;
		}
		foreach ( $post_ids as $post_id ) {
			$order = wc_get_order( (int) $post_id );
			if ( $order ) {
				$order->update_status( self::STATUS_SLUG );
			}
		}
		return $redirect_to;
	}

	/**
	 * Give the status a distinct colour in the admin order list.
	 */
	public function add_status_column_colour( array $columns ): array {
		$columns[ self::STATUS_KEY ] = 'order-status-awaiting-receipt';
		return $columns;
	}

	// ─── Admin inline CSS for status pill colour ─────────────────────────────

	public static function admin_status_css(): void {
		echo '<style>
			.order-status.status-awaiting-receipt { background:#fff3cd; color:#856404; }
			mark.order-status.status-awaiting-receipt { background:#fff3cd; color:#856404; }
		</style>';
	}
}
