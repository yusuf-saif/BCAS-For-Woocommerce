/* jshint esversion: 6 */
/**
 * BCAS to WhatsApp — Frontend JS
 * Handles:
 *   - Checkout bank-account selector (show/hide + hidden-field update)
 *   - Copy-to-clipboard (modern + legacy fallback)
 *   - Thank-you page popup (open/close, ESC key, focus-trap)
 */
(function () {
	'use strict';

	var i18n   = (window.bcaswData && window.bcaswData.i18n) || {};
	var COPIED = i18n.copied || 'Copied!';

	/* ── Clipboard ────────────────────────────────────────────────────────────── */

	function copyText(text, btn) {
		if (!text || !btn) return;
		var orig = btn.textContent;

		function onSuccess() {
			btn.textContent = COPIED;
			btn.classList.add('bcasw-copy-btn--success');
			setTimeout(function () {
				btn.textContent = orig;
				btn.classList.remove('bcasw-copy-btn--success');
			}, 1800);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
				legacyCopy(text, onSuccess);
			});
		} else {
			legacyCopy(text, onSuccess);
		}
	}

	function legacyCopy(text, onSuccess) {
		var el = document.createElement('textarea');
		el.value = text;
		el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
		document.body.appendChild(el);
		el.focus();
		el.select();
		try { document.execCommand('copy'); onSuccess(); } catch (e) {}
		document.body.removeChild(el);
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.bcasw-copy-btn');
		if (btn) copyText(btn.getAttribute('data-copy') || '', btn);
	});

	/* ── Popup ────────────────────────────────────────────────────────────────── */

	var overlay = document.getElementById('bcasw-overlay');

	function openPopup() {
		if (overlay) {
			overlay.style.display = 'flex';
		}
	}

	function closePopup() {
		if (overlay) overlay.style.display = 'none';
	}

	// Show popup after the configured delay (data-popup-delay attribute, in ms).
	// Default: 5 seconds. The inline bank block is always the primary experience;
	// the popup is a secondary reminder that appears after the customer has had
	// time to read the transfer details.
	if (overlay) {
		var delay = parseInt(overlay.getAttribute('data-popup-delay') || '5000', 10);
		setTimeout(openPopup, delay);
	}

	['bcasw-closePopup', 'bcasw-closePopup2'].forEach(function (id) {
		var btn = document.getElementById(id);
		if (btn) btn.addEventListener('click', closePopup);
	});

	if (overlay) {
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) closePopup();
		});
	}

	document.addEventListener('keydown', function (e) {
		if ((e.key === 'Escape' || e.keyCode === 27) && overlay) closePopup();
	});

	// Basic focus-trap inside popup.
	if (overlay) {
		overlay.addEventListener('keydown', function (e) {
			if (e.key !== 'Tab') return;
			var focusable = overlay.querySelectorAll(
				'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
			);
			if (!focusable.length) return;
			var first = focusable[0], last = focusable[focusable.length - 1];
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault(); last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault(); first.focus();
			}
		});
	}

	/* ── Checkout bank-account selector ──────────────────────────────────────── */

	var checkoutWrap   = document.getElementById('bcasw-checkout-selector-wrap');
	var hiddenBankId   = document.getElementById('bcasw_selected_bank_id');
	var rememberedId   = '';   // persists across WC checkout refreshes

	/**
	 * Show or hide the bank selector depending on the chosen payment method.
	 * When bcasw_bacs_only=true, selector is always visible.
	 */
	function updateSelectorVisibility() {
		if (!checkoutWrap) return;
		var bacsOnly = checkoutWrap.getAttribute('data-bacs-only') === 'true';
		if (bacsOnly) {
			checkoutWrap.style.display = '';
			return;
		}
		var checked = document.querySelector('input[name="payment_method"]:checked');
		checkoutWrap.style.display = (checked && checked.value === 'bacs') ? '' : 'none';
	}

	/**
	 * After WC rerenders the checkout area, re-apply the selected bank card.
	 */
	function reapplySelection() {
		if (!rememberedId || !checkoutWrap) return;
		var cards = checkoutWrap.querySelectorAll('.bcasw-bank-card');
		cards.forEach(function (card) {
			var active = card.getAttribute('data-bank-id') === rememberedId;
			card.classList.toggle('bcasw-bank-card--active', active);
			card.setAttribute('aria-pressed', active ? 'true' : 'false');
		});
		if (hiddenBankId) hiddenBankId.value = rememberedId;
	}

	if (checkoutWrap) {
		// Handle card clicks.
		checkoutWrap.addEventListener('click', function (e) {
			var card = e.target.closest('.bcasw-bank-card');
			if (!card) return;

			var newId = card.getAttribute('data-bank-id');
			rememberedId = newId;

			// Update UI.
			checkoutWrap.querySelectorAll('.bcasw-bank-card').forEach(function (c) {
				var active = c === card;
				c.classList.toggle('bcasw-bank-card--active', active);
				c.setAttribute('aria-pressed', active ? 'true' : 'false');
			});

			// Update hidden field.
			if (hiddenBankId) hiddenBankId.value = newId;
		});

		// Initial state.
		updateSelectorVisibility();
	}

	// WooCommerce classic checkout events.
	document.addEventListener('change', function (e) {
		if (e.target && e.target.name === 'payment_method') {
			updateSelectorVisibility();
		}
	});

	// After WC rerenders (jQuery-based event — safe to skip if jQuery absent).
	if (typeof window.jQuery !== 'undefined') {
		window.jQuery(document.body).on('updated_checkout', function () {
			// Selector area is re-rendered; re-fetch refs.
			checkoutWrap = document.getElementById('bcasw-checkout-selector-wrap');
			hiddenBankId = document.getElementById('bcasw_selected_bank_id');
			updateSelectorVisibility();
			reapplySelection();
		});
	}

})();
