/* jshint esversion: 6 */
/**
 * BCAS to WhatsApp — Admin Settings JS
 * Handles: tab switching, bank account repeater (add/remove/reorder).
 */
(function ($) {
	'use strict';

	var cfg = window.bcaswAdmin || {};

	/* ── Tab switching ────────────────────────────────────────────────────── */

	function initTabs() {
		var $tabs   = $('.bcasw-tab-btn');
		var $panels = $('.bcasw-tab-panel');

		$tabs.on('click', function () {
			var target = $(this).data('tab');

			$tabs.removeClass('is-active');
			$panels.removeClass('is-active');

			$(this).addClass('is-active');
			$('#bcasw-panel-' + target).addClass('is-active');

			// Update hidden field.
			$('#bcasw_active_tab').val(target);
		});
	}

	/* ── Bank account repeater ────────────────────────────────────────────── */

	function initBankRepeater() {
		var $list = $('#bcasw-banks-list');
		if (!$list.length) return;

		// Drag-to-reorder.
		$list.sortable({
			handle: '.bcasw-bank-row__drag',
			placeholder: 'bcasw-sortable-placeholder',
			start: function (e, ui) {
				ui.item.addClass('is-dragging');
			},
			stop: function (e, ui) {
				ui.item.removeClass('is-dragging');
				reindexRows($list);
			},
		});

		// Toggle accordion.
		$list.on('click', '.bcasw-bank-row__header', function () {
			var $row = $(this).closest('.bcasw-bank-row');
			$row.toggleClass('is-open');
		});

		// Remove a row.
		$list.on('click', '.bcasw-remove-btn', function (e) {
			e.stopPropagation();
			if (!window.confirm(cfg.confirmDelete || 'Remove this bank account?')) return;
			var $row = $(this).closest('.bcasw-bank-row');
			$row.remove();
			reindexRows($list);
		});

		// Live-update the header label when the label/bank_name field changes.
		$list.on('input', '.js-bank-label, .js-bank-name', function () {
			var $row   = $(this).closest('.bcasw-bank-row');
			var label  = $row.find('.js-bank-label').val().trim();
			var name   = $row.find('.js-bank-name').val().trim();
			$row.find('.bcasw-bank-row__name').text(label || name || 'New Account');
		});

		// Default radio: show badge on the selected row.
		$list.on('change', '.bcasw-default-radio-input', function () {
			$list.find('.bcasw-bank-row__default-badge').hide();
			$(this).closest('.bcasw-bank-row').find('.bcasw-bank-row__default-badge').show();
		});

		// Add new account.
		$('#bcasw-add-bank').on('click', function () {
			var tpl = cfg.newAccountTpl || '';
			// Replace placeholder ID with unique value.
			var uniqueId = 'new_' + Date.now();
			tpl = tpl.replace(/__NEW__/g, uniqueId);
			var $newRow = $(tpl);
			$list.append($newRow);
			reindexRows($list);
			$newRow.addClass('is-open');
			$newRow.find('.js-bank-label').focus();
		});
	}

	/**
	 * Re-index all name attributes after add/remove/reorder.
	 */
	function reindexRows($list) {
		$list.find('.bcasw-bank-row').each(function (i) {
			$(this).find('[name]').each(function () {
				var $el   = $(this);
				var name  = $el.attr('name');
				// Replace the numeric index: bcasw_bank[N][field] → bcasw_bank[i][field]
				$el.attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
			});
		});
	}

	/* ── Variable pill click-to-insert ────────────────────────────────────── */

	function initVarPills() {
		$(document).on('click', '.bcasw-var-pill', function () {
			var pill  = $(this);
			var text  = pill.text();
			var $dest = pill.closest('.bcasw-field').find('textarea');
			if (!$dest.length) return;

			var el    = $dest[0];
			var start = el.selectionStart;
			var end   = el.selectionEnd;
			var val   = el.value;
			el.value  = val.slice(0, start) + text + val.slice(end);
			el.selectionStart = el.selectionEnd = start + text.length;
			el.focus();
		});
	}

	/* ── Init ─────────────────────────────────────────────────────────────── */

	$(function () {
		initTabs();
		initBankRepeater();
		initVarPills();
	});

})(jQuery);
