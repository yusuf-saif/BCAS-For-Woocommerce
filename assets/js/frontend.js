/* jshint esversion: 6 */
/**
 * BCAS to WhatsApp — Frontend JS
 * Handles: copy-to-clipboard, popup open/close, bank selector.
 */
(function () {
	'use strict';

	var i18n   = (window.bcaswData && window.bcaswData.i18n)   || {};
	var COPIED  = i18n.copied  || 'Copied!';
	var COPY    = i18n.copy    || 'Copy';

	/* ── Clipboard helper ─────────────────────────────────────────────────── */

	function copyText(text, btn) {
		if (!text) return;

		var originalText = btn ? btn.textContent : '';

		function onSuccess() {
			if (!btn) return;
			btn.textContent = COPIED;
			btn.classList.add('bcasw-copy-btn--success');
			setTimeout(function () {
				btn.textContent = originalText;
				btn.classList.remove('bcasw-copy-btn--success');
			}, 1800);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
				legacyCopy(text, btn, onSuccess);
			});
		} else {
			legacyCopy(text, btn, onSuccess);
		}
	}

	function legacyCopy(text, btn, onSuccess) {
		var el = document.createElement('textarea');
		el.value = text;
		el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
		document.body.appendChild(el);
		el.focus();
		el.select();
		try {
			document.execCommand('copy');
			onSuccess();
		} catch (e) {
			console.warn('[BCASW] Copy failed', e);
		}
		document.body.removeChild(el);
	}

	/* ── Copy button delegation ───────────────────────────────────────────── */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.bcasw-copy-btn');
		if (!btn) return;
		var value = btn.getAttribute('data-copy') || '';
		copyText(value, btn);
	});

	/* ── Popup ────────────────────────────────────────────────────────────── */

	var overlay  = document.getElementById('bcasw-overlay');
	var closeIds = ['bcasw-closePopup', 'bcasw-closePopup2'];

	function closePopup() {
		if (overlay) {
			overlay.style.display = 'none';
		}
	}

	// Close via named buttons.
	closeIds.forEach(function (id) {
		var btn = document.getElementById(id);
		if (btn) btn.addEventListener('click', closePopup);
	});

	// Close by clicking backdrop.
	if (overlay) {
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) closePopup();
		});
	}

	// Close via ESC key.
	document.addEventListener('keydown', function (e) {
		if ((e.key === 'Escape' || e.keyCode === 27) && overlay) {
			closePopup();
		}
	});

	// Focus-trap inside popup (basic).
	if (overlay) {
		overlay.addEventListener('keydown', function (e) {
			if (e.key !== 'Tab') return;
			var focusable = overlay.querySelectorAll(
				'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
			);
			if (!focusable.length) return;
			var first = focusable[0];
			var last  = focusable[focusable.length - 1];
			if (e.shiftKey) {
				if (document.activeElement === first) { e.preventDefault(); last.focus(); }
			} else {
				if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
			}
		});
	}

	/* ── Bank account selector ────────────────────────────────────────────── */

	var selector = document.getElementById('bcasw-bank-selector');

	if (selector) {
		var orderId    = selector.getAttribute('data-order-id');
		var nonce      = selector.getAttribute('data-nonce');
		var detailGrid = document.getElementById('bcasw-details-grid');
		var waBtn      = document.getElementById('bcasw-wa-btn-inline');
		var waPopupBtn = document.getElementById('bcasw-wa-btn-popup');

		selector.addEventListener('click', function (e) {
			var card = e.target.closest('.bcasw-bank-card');
			if (!card) return;

			var bankId   = card.getAttribute('data-bank-id');
			var bankData = JSON.parse(card.getAttribute('data-bank') || '{}');

			// Update active state.
			selector.querySelectorAll('.bcasw-bank-card').forEach(function (c) {
				c.classList.remove('bcasw-bank-card--active');
				c.setAttribute('aria-pressed', 'false');
			});
			card.classList.add('bcasw-bank-card--active');
			card.setAttribute('aria-pressed', 'true');

			// Update detail grid data attributes.
			if (detailGrid) {
				updateDetailGrid(detailGrid, bankData);
			}

			// Update WhatsApp button links.
			var newWaUrl = buildWaUrl(detailGrid, bankData);
			if (waBtn)      waBtn.setAttribute('href', newWaUrl);
			if (waPopupBtn) waPopupBtn.setAttribute('href', newWaUrl);

			// Persist to server.
			saveBank(orderId, bankId, nonce);
		});
	}

	/**
	 * Update the visible bank detail fields in the grid.
	 */
	function updateDetailGrid(grid, bankData) {
		var fieldMap = {
			bank_name:      'bank_name',
			account_name:   'account_name',
			account_number: 'account_number',
			sort_code:      'sort_code',
			iban:           'iban',
			swift_bic:      'swift_bic',
		};
		Object.keys(fieldMap).forEach(function (key) {
			var el = grid.querySelector('[data-bank-field="' + key + '"] .bcasw-detail-value');
			if (el) el.textContent = bankData[key] || '';

			var copyBtn = grid.querySelector('[data-bank-field="' + key + '"] .bcasw-copy-btn');
			if (copyBtn) copyBtn.setAttribute('data-copy', bankData[key] || '');
		});
	}

	/**
	 * Build a WhatsApp URL from the current data-* attributes on the detail grid.
	 */
	function buildWaUrl(grid, bankData) {
		if (!grid) return '#';

		var number   = grid.getAttribute('data-wa-number') || '';
		var tpl      = grid.getAttribute('data-wa-tpl') || '';
		var siteName = grid.getAttribute('data-site-name') || '';
		var custName = grid.getAttribute('data-customer-name') || '';
		var orderNum = grid.getAttribute('data-order-number') || '';
		var total    = grid.getAttribute('data-order-total') || '';
		var currency = grid.getAttribute('data-currency') || '';

		var message = tpl
			.replace('{site_name}',      siteName)
			.replace('{order_number}',   orderNum)
			.replace('{customer_name}',  custName)
			.replace('{order_total}',    total)
			.replace('{currency}',       currency)
			.replace('{bank_name}',      bankData.bank_name      || '')
			.replace('{account_name}',   bankData.account_name   || '')
			.replace('{account_number}', bankData.account_number || '')
			.replace('{sort_code}',      bankData.sort_code      || '')
			.replace('{iban}',           bankData.iban           || '')
			.replace('{swift_bic}',      bankData.swift_bic      || '')
			.replace(/\{[a-z0-9_]+\}/gi, '');

		var cleanNumber = number.replace(/[^0-9]/g, '');
		return 'https://wa.me/' + encodeURIComponent(cleanNumber) + '?text=' + encodeURIComponent(message);
	}

	/**
	 * AJAX: persist selected bank on the server.
	 */
	function saveBank(orderId, bankId, nonce) {
		var ajaxUrl = (window.bcaswData && window.bcaswData.ajaxurl) || '/wp-admin/admin-ajax.php';
		var body    = new URLSearchParams();
		body.append('action',   'bcasw_select_bank');
		body.append('order_id', orderId);
		body.append('bank_id',  bankId);
		body.append('nonce',    nonce);

		fetch(ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		}).catch(function (err) {
			console.warn('[BCASW] Bank save failed', err);
		});
	}

})();
