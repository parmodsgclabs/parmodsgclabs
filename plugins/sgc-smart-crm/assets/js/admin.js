/**
 * SGC Smart CRM Pro — Admin JS
 *
 * Global helpers shared across all admin tabs.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 */

/* global SGC_CRM, ajaxurl */

(function ($) {
	'use strict';

	// =========================================================================
	// GLOBAL: Modal close via backdrop or ESC
	// =========================================================================
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			$('.sgc-crm-modal:visible').hide();
		}
	});

	// =========================================================================
	// GLOBAL: Flash notice helper (JS-side)
	// =========================================================================
	window.SGC_CRM_Notice = function (msg, type, $container) {
		if (!$container) $container = $('#sgc-crm-global-notice');
		if (!$container.length) return;
		$container
			.text(msg)
			.removeClass('success error')
			.addClass(type || 'success')
			.show();
		setTimeout(function () {
			$container.fadeOut();
		}, 4000);
	};

	// =========================================================================
	// PRODUCTS TAB: Load promo from sessionStorage if coming from products tab
	// =========================================================================
	if (window.location.href.indexOf('from=promo') > -1) {
		try {
			var promoRaw = sessionStorage.getItem('sgc_crm_promo');
			if (promoRaw) {
				var promo = JSON.parse(promoRaw);
				sessionStorage.removeItem('sgc_crm_promo');
				// Wait for campaigns tab DOM to be ready
				$(function () {
					$('#campaign-id').val('');
					$('#campaign-modal-title').text('New Campaign (Product Promo)');
					$('#campaign-name').val(promo.subject || 'Product Promo Campaign');
					$('#campaign-subject').val(promo.subject || '');
					$('#campaign-preheader').val(promo.preheader || '');
					$('#campaign-body').val(promo.body_html || '');
					$('#campaign-type').val('product_promo');
					$('#campaign-modal').show();
				});
			}
		} catch (e) {
			// sessionStorage not available or parse error — ignore.
		}
	}

	// =========================================================================
	// SETTINGS TAB: save_option for non-toggle options via AJAX
	// Extend the whitelisted options check to include email settings
	// by using a direct option save approach.
	// =========================================================================
	window.sgc_crm_save_direct_option = function (option_name, option_value, callback) {
		$.post(ajaxurl, {
			action:       'sgc_crm_save_option',
			nonce:        SGC_CRM.nonce,
			option_name:  option_name,
			option_value: option_value
		}, function (r) {
			if (typeof callback === 'function') callback(r);
		});
	};

	// =========================================================================
	// GLOBAL: Auto-dismiss WP admin notices after 6s
	// =========================================================================
	setTimeout(function () {
		$('.notice:not(.notice-error)').not('.is-dismissible').fadeOut(600);
	}, 6000);

}(jQuery));
