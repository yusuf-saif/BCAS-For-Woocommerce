<?php
/**
 * Admin view — single bank account repeater row.
 *
 * Variables available in scope:
 *   $account  (array) — bank account data
 *
 * @package BCAS_To_WhatsApp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Use a stable index placeholder that admin JS will replace with a numeric index.
static $row_index = 0;
$idx         = $row_index++;
$id          = $account['id'] ?? '';
$label       = $account['label'] ?? '';
$bank_name   = $account['bank_name'] ?? '';
$is_default  = ! empty( $account['is_default'] );
$row_name    = $label ?: $bank_name ?: __( 'New Account', 'bcas-to-whatsapp' );
?>
<div class="bcasw-bank-row <?php echo $is_default ? 'is-open' : ''; ?>" data-bank-id="<?php echo esc_attr( $id ); ?>">

	<div class="bcasw-bank-row__header">
		<span class="bcasw-bank-row__drag" title="<?php esc_attr_e( 'Drag to reorder', 'bcas-to-whatsapp' ); ?>">⠿</span>
		<span class="bcasw-bank-row__name"><?php echo esc_html( $row_name ); ?></span>
		<?php if ( $is_default ) : ?>
			<span class="bcasw-bank-row__default-badge"><?php esc_html_e( 'Default', 'bcas-to-whatsapp' ); ?></span>
		<?php else : ?>
			<span class="bcasw-bank-row__default-badge" style="display:none;"><?php esc_html_e( 'Default', 'bcas-to-whatsapp' ); ?></span>
		<?php endif; ?>
		<span class="bcasw-bank-row__toggle">▾</span>
	</div>

	<div class="bcasw-bank-row__body">

		<input type="hidden" name="bcasw_bank[<?php echo $idx; ?>][id]" value="<?php echo esc_attr( $id ); ?>">

		<div class="bcasw-bank-fields">

			<div class="bcasw-bank-field">
				<label><?php esc_html_e( 'Account Label', 'bcas-to-whatsapp' ); ?></label>
				<input type="text"
					class="js-bank-label"
					name="bcasw_bank[<?php echo $idx; ?>][label]"
					value="<?php echo esc_attr( $label ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Main Account', 'bcas-to-whatsapp' ); ?>">
			</div>

			<div class="bcasw-bank-field">
				<label><?php esc_html_e( 'Bank Name', 'bcas-to-whatsapp' ); ?></label>
				<input type="text"
					class="js-bank-name"
					name="bcasw_bank[<?php echo $idx; ?>][bank_name]"
					value="<?php echo esc_attr( $bank_name ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. United Bank for Africa', 'bcas-to-whatsapp' ); ?>">
			</div>

			<div class="bcasw-bank-field">
				<label><?php esc_html_e( 'Account Name', 'bcas-to-whatsapp' ); ?></label>
				<input type="text"
					name="bcasw_bank[<?php echo $idx; ?>][account_name]"
					value="<?php echo esc_attr( $account['account_name'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'Name on account', 'bcas-to-whatsapp' ); ?>">
			</div>

			<div class="bcasw-bank-field">
				<label><?php esc_html_e( 'Account Number', 'bcas-to-whatsapp' ); ?></label>
				<input type="text"
					name="bcasw_bank[<?php echo $idx; ?>][account_number]"
					value="<?php echo esc_attr( $account['account_number'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( '0123456789', 'bcas-to-whatsapp' ); ?>">
			</div>

			<div class="bcasw-bank-field">
				<label><?php esc_html_e( 'Sort Code', 'bcas-to-whatsapp' ); ?></label>
				<input type="text"
					name="bcasw_bank[<?php echo $idx; ?>][sort_code]"
					value="<?php echo esc_attr( $account['sort_code'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( '00-00-00 (optional)', 'bcas-to-whatsapp' ); ?>">
			</div>

			<div class="bcasw-bank-field">
				<label><?php esc_html_e( 'IBAN', 'bcas-to-whatsapp' ); ?></label>
				<input type="text"
					name="bcasw_bank[<?php echo $idx; ?>][iban]"
					value="<?php echo esc_attr( $account['iban'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'GB00 0000 0000 (optional)', 'bcas-to-whatsapp' ); ?>">
			</div>

			<div class="bcasw-bank-field">
				<label><?php esc_html_e( 'SWIFT / BIC', 'bcas-to-whatsapp' ); ?></label>
				<input type="text"
					name="bcasw_bank[<?php echo $idx; ?>][swift_bic]"
					value="<?php echo esc_attr( $account['swift_bic'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'XXXXGBXX (optional)', 'bcas-to-whatsapp' ); ?>">
			</div>

		</div><!-- /.bcasw-bank-fields -->

		<div class="bcasw-bank-row__footer">
			<label class="bcasw-default-radio">
				<input type="radio"
					class="bcasw-default-radio-input"
					name="bcasw_default_bank"
					value="<?php echo esc_attr( $id ); ?>"
					<?php checked( $is_default ); ?>>
				<?php esc_html_e( 'Set as default account', 'bcas-to-whatsapp' ); ?>
			</label>

			<button type="button" class="bcasw-remove-btn">
				🗑 <?php esc_html_e( 'Remove', 'bcas-to-whatsapp' ); ?>
			</button>
		</div>

	</div><!-- /.bcasw-bank-row__body -->
</div><!-- /.bcasw-bank-row -->
