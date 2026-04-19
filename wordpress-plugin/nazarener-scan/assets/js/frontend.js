/* global NazarenerScan, Html5Qrcode */
(function ($) {
	'use strict';

	let currentBarcode = '';
	let html5QrCode   = null;
	let hintsTimer    = null;

	// ── Step navigation ──────────────────────────────────────────────────────

	function showStep(stepId) {
		$('.ns-step').removeClass('ns-step--active');
		$('#' + stepId).addClass('ns-step--active');
	}

	// ── Barcode lookup ───────────────────────────────────────────────────────

	function lookupBarcode(barcode) {
		barcode = barcode.trim();
		if (!barcode) return;
		currentBarcode = barcode;

		showStep('ns-step-result');
		$('#ns-result-green, #ns-result-red, #ns-result-yellow').hide();

		$.post(NazarenerScan.ajaxUrl, {
			action:  'nazarener_lookup',
			nonce:   NazarenerScan.nonce,
			barcode: barcode,
		}, function (res) {
			if (!res.success) return;
			const d = res.data;

			if (d.status === 'green') {
				renderGreen(d, barcode);
			} else if (d.status === 'red') {
				renderRed(d, barcode);
			} else {
				renderYellow(d, barcode);
			}
		});
	}

	function renderGreen(d, barcode) {
		$('#ns-result-name').text(d.name || barcode);
		$('#ns-result-brand').text(d.brand || '').toggle(!!d.brand);
		$('#ns-result-barcode-green').text(barcode);
		$('#ns-result-notes').text(d.notes || '').toggle(!!d.notes);
		$('#ns-result-green').show();
	}

	function renderRed(d, barcode) {
		$('#ns-result-name-red').text(d.name || barcode);
		$('#ns-result-brand-red').text(d.brand || '').toggle(!!d.brand);
		$('#ns-result-barcode-red').text(barcode);

		if (d.forbidden_ingredients) {
			$('#ns-forbidden-list').text(d.forbidden_ingredients);
			$('#ns-forbidden-box').show();
		} else {
			$('#ns-forbidden-box').hide();
		}
		$('#ns-result-red').show();
	}

	function renderYellow(d, barcode) {
		$('#ns-result-barcode-yellow').text(barcode);
		$('#ns-already-submitted').toggle(!!d.already_submitted);
		$('#ns-open-submit').toggle(!d.already_submitted);
		$('#ns-result-yellow').show();
	}

	// ── Scanner ──────────────────────────────────────────────────────────────

	function startScanner() {
		if (typeof Html5Qrcode === 'undefined') return;

		html5QrCode = new Html5Qrcode('ns-reader');

		html5QrCode.start(
			{ facingMode: 'environment' },
			{
				fps: 10,
				qrbox: { width: 250, height: 120 },
				aspectRatio: 1.7,
				formatsToSupport: [
					Html5Qrcode.SUPPORTED_FORMATS ? undefined : null,
					// Falls back to all formats automatically
				].filter(Boolean),
			},
			function onScanSuccess(decodedText) {
				html5QrCode.stop().then(function () {
					lookupBarcode(decodedText);
				});
			}
		).catch(function () {
			// Camera permission denied — user can still type manually
			$('#ns-reader').html(
				'<div class="ns-camera-error">' + NazarenerScan.i18n.errorCamera + '</div>'
			);
		});
	}

	// ── Submission ───────────────────────────────────────────────────────────

	function submitProduct(e) {
		e.preventDefault();

		if (NazarenerScan.requireLogin && !NazarenerScan.isLoggedIn) {
			alert('Bitte melde dich an, um Produkte einzureichen.');
			return;
		}

		const formData = new FormData(document.getElementById('ns-submit-form'));
		formData.append('action', 'nazarener_submit');
		formData.append('nonce', NazarenerScan.nonce);

		const $btn = $('#ns-submit-btn');
		$btn.prop('disabled', true).text('⏳ Wird eingereicht…');

		$.ajax({
			url:         NazarenerScan.ajaxUrl,
			type:        'POST',
			data:        formData,
			processData: false,
			contentType: false,
			success: function (res) {
				if (res.success) {
					showStep('ns-step-success');
				} else {
					alert(res.data.message || NazarenerScan.i18n.submitError);
					$btn.prop('disabled', false).text('📤 Zur Prüfung einreichen');
				}
			},
			error: function () {
				alert(NazarenerScan.i18n.submitError);
				$btn.prop('disabled', false).text('📤 Zur Prüfung einreichen');
			},
		});
	}

	// ── Ingredient hints ──────────────────────────────────────────────────────

	function fetchHints(text) {
		if (text.length < 4) {
			$('#ns-hints-banner').hide();
			return;
		}
		$.post(NazarenerScan.ajaxUrl, {
			action: 'nazarener_analyze_hints',
			nonce:  NazarenerScan.nonce,
			text:   text,
		}, function (res) {
			if (!res.success || !res.data.hints.length) {
				$('#ns-hints-banner').hide();
				return;
			}
			const $list = $('#ns-hints-list').empty();
			res.data.hints.forEach(function (h) {
				$('<li>').addClass('ns-hint-' + h.type)
					.text((h.type === 'warning' ? '⚠️ ' : 'ℹ️ ') + h.message)
					.appendTo($list);
			});
			$('#ns-hints-banner').show();
		});
	}

	// ── Photo preview ─────────────────────────────────────────────────────────

	function setupPhotoPreview(inputId, previewId, removeId) {
		$('#' + inputId).on('change', function () {
			const file = this.files[0];
			if (!file) return;
			const reader = new FileReader();
			reader.onload = function (e) {
				$('#' + previewId).attr('src', e.target.result).show();
				$('#' + removeId).show();
			};
			reader.readAsDataURL(file);
		});
		$('#' + removeId).on('click', function () {
			$('#' + inputId).val('');
			$('#' + previewId).hide().attr('src', '');
			$(this).hide();
		});
	}

	// ── Init ──────────────────────────────────────────────────────────────────

	$(function () {
		startScanner();

		// Manual barcode input
		$('#ns-barcode-submit').on('click', function () {
			lookupBarcode($('#ns-barcode-input').val());
		});
		$('#ns-barcode-input').on('keydown', function (e) {
			if (e.key === 'Enter') lookupBarcode($(this).val());
		});

		// Back buttons
		$('#ns-back-to-scan').on('click', function () {
			$('#ns-barcode-input').val('');
			showStep('ns-step-scan');
			startScanner();
		});
		$('#ns-back-to-result').on('click', function () {
			showStep('ns-step-result');
		});
		$('#ns-scan-again').on('click', function () {
			$('#ns-barcode-input').val('');
			showStep('ns-step-scan');
			startScanner();
		});

		// Open submit form
		$('#ns-open-submit').on('click', function () {
			$('#ns-submit-barcode').val(currentBarcode);
			$('#ns-barcode-display').text(currentBarcode);
			$('#ns-product-name').val('');
			$('#ns-ingredients-text').val('');
			$('#ns-hints-banner').hide();
			$('#ns-product-photo').val('');
			$('#ns-ingredients-photo').val('');
			$('#ns-product-photo-preview, #ns-ingredients-photo-preview').hide();
			$('#ns-remove-product-photo, #ns-remove-ingredients-photo').hide();
			$('#ns-submit-btn').prop('disabled', false).text('📤 Zur Prüfung einreichen');
			showStep('ns-step-submit');
		});

		// Submit form
		$('#ns-submit-form').on('submit', submitProduct);

		// Ingredient hints on typing
		$('#ns-ingredients-text').on('input', function () {
			clearTimeout(hintsTimer);
			const text = $(this).val();
			hintsTimer = setTimeout(function () { fetchHints(text); }, 600);
		});

		// Photo previews
		setupPhotoPreview('ns-product-photo', 'ns-product-photo-preview', 'ns-remove-product-photo');
		setupPhotoPreview('ns-ingredients-photo', 'ns-ingredients-photo-preview', 'ns-remove-ingredients-photo');
	});

}(jQuery));
