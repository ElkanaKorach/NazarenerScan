/* global NazarenerScan, Html5Qrcode, jQuery */
(function ($) {
	'use strict';

	const HISTORY_KEY = 'ns_scan_history';
	const HISTORY_MAX = 8;

	let currentBarcode = '';
	let html5QrCode    = null;
	let hintsTimer     = null;
	let scannerActive  = false;

	// ── Offline detection ────────────────────────────────────────────────────

	function updateOnlineStatus() {
		$('#ns-offline-banner').toggle(!navigator.onLine);
	}
	window.addEventListener('online',  updateOnlineStatus);
	window.addEventListener('offline', updateOnlineStatus);

	// ── Step navigation ──────────────────────────────────────────────────────

	function showStep(id) {
		$('.ns-step').removeClass('ns-step--active');
		$('#' + id).addClass('ns-step--active');
	}

	// ── Scan history (localStorage) ──────────────────────────────────────────

	function loadHistory() {
		try { return JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); }
		catch (e) { return []; }
	}

	function saveHistory(items) {
		try { localStorage.setItem(HISTORY_KEY, JSON.stringify(items)); } catch (e) {}
	}

	function addToHistory(barcode, status) {
		var history = loadHistory().filter(function(h) { return h.barcode !== barcode; });
		history.unshift({ barcode: barcode, status: status, ts: Date.now() });
		history = history.slice(0, HISTORY_MAX);
		saveHistory(history);
		renderHistory();
	}

	function renderHistory() {
		var history = loadHistory();
		if (!history.length) { $('#ns-history').hide(); return; }
		var $list = $('#ns-history-list').empty();
		history.forEach(function(h) {
			var icon = h.status === 'green' ? '🟢' : h.status === 'red' ? '🔴' : '🟡';
			$('<button class="ns-history-item">')
				.attr('data-barcode', h.barcode)
				.html(icon + ' <code>' + escHtml(h.barcode) + '</code>')
				.appendTo($list);
		});
		$('#ns-history').show();
	}

	// ── Barcode lookup ───────────────────────────────────────────────────────

	function lookupBarcode(barcode) {
		barcode = String(barcode).trim();
		if (!barcode || !navigator.onLine) {
			if (!navigator.onLine) showError(NazarenerScan.i18n.offline);
			return;
		}
		currentBarcode = barcode;
		stopScanner();
		showStep('ns-step-result');
		$('#ns-result-green, #ns-result-red, #ns-result-yellow').hide();
		$('#ns-result-loading').show();

		$.post(NazarenerScan.ajaxUrl, {
			action: 'nazarener_lookup',
			nonce:  NazarenerScan.nonce,
			barcode: barcode,
		})
		.done(function(res) {
			$('#ns-result-loading').hide();
			if (!res.success) {
				showError(res.data.message || NazarenerScan.i18n.submitError);
				return;
			}
			var d = res.data;
			addToHistory(barcode, d.status);

			if (d.status === 'green')  renderGreen(d, barcode);
			else if (d.status === 'red') renderRed(d, barcode);
			else renderYellow(d, barcode);
		})
		.fail(function(xhr) {
			$('#ns-result-loading').hide();
			if (xhr.status === 429) showError(NazarenerScan.i18n.rateLimited);
			else showError(navigator.onLine ? NazarenerScan.i18n.submitError : NazarenerScan.i18n.offline);
		});
	}

	function renderGreen(d, barcode) {
		$('#ns-g-name').text(d.name || barcode);
		$('#ns-g-brand').text(d.brand || '').toggle(!!d.brand);
		$('#ns-g-barcode').text(barcode);
		if (d.notes) $('#ns-g-notes').text(d.notes).show(); else $('#ns-g-notes').hide();
		if (d.image_url) {
			$('#ns-g-image').html('<img src="' + escUrl(d.image_url) + '" alt="" />').show();
		} else {
			$('#ns-g-image').hide();
		}
		$('#ns-result-green').show();
	}

	function renderRed(d, barcode) {
		$('#ns-r-name').text(d.name || barcode);
		$('#ns-r-brand').text(d.brand || '').toggle(!!d.brand);
		$('#ns-r-barcode').text(barcode);
		if (d.forbidden_ingredients) {
			$('#ns-r-forbidden').text(d.forbidden_ingredients);
			$('#ns-r-forbidden-box').show();
		} else { $('#ns-r-forbidden-box').hide(); }
		if (d.biblical_reference) {
			$('#ns-r-biblical').text('📖 ' + d.biblical_reference).show();
		} else { $('#ns-r-biblical').hide(); }
		$('#ns-result-red').show();
	}

	function renderYellow(d, barcode) {
		$('#ns-y-barcode').text(barcode);
		$('#ns-already-submitted').toggle(!!d.already_submitted);
		$('#ns-notify-box').toggle(!d.already_submitted);
		$('#ns-open-submit').toggle(!d.already_submitted);
		$('#ns-result-yellow').show();
	}

	// ── Scanner ──────────────────────────────────────────────────────────────

	function startScanner() {
		if (typeof Html5Qrcode === 'undefined' || scannerActive) return;

		html5QrCode = new Html5Qrcode('ns-reader');
		scannerActive = true;

		html5QrCode.start(
			{ facingMode: 'environment' },
			{ fps: 10, qrbox: { width: 260, height: 110 } },
			function onScan(text) {
				lookupBarcode(text);
			}
		).catch(function() {
			scannerActive = false;
			$('#ns-reader').html('<div class="ns-camera-error">' + NazarenerScan.i18n.errorCamera + '</div>');
		});
	}

	function stopScanner() {
		if (html5QrCode && scannerActive) {
			html5QrCode.stop().catch(function(){});
			scannerActive = false;
		}
	}

	// ── Submission ───────────────────────────────────────────────────────────

	function submitProduct(e) {
		e.preventDefault();

		if (NazarenerScan.requireLogin && !NazarenerScan.isLoggedIn) {
			showSubmitError('Bitte melde dich an, um Produkte einzureichen.');
			return;
		}
		if (!$('#ns-privacy-consent').prop('checked')) {
			showSubmitError(NazarenerScan.i18n.privacyRequired);
			return;
		}
		if (!navigator.onLine) {
			showSubmitError(NazarenerScan.i18n.offline);
			return;
		}

		var $btn = $('#ns-submit-btn');
		$btn.prop('disabled', true).text('⏳ Wird eingereicht…');
		$('#ns-submit-error').hide();

		var formData = new FormData(document.getElementById('ns-submit-form'));
		formData.append('action', 'nazarener_submit');
		formData.append('nonce', NazarenerScan.nonce);

		$.ajax({
			url: NazarenerScan.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
		})
		.done(function(res) {
			if (res.success) {
				// Show success email hint if email was provided
				if ($('#ns-submitter-email').val()) {
					$('#ns-success-email-hint').show();
				} else {
					$('#ns-success-email-hint').hide();
				}
				showStep('ns-step-success');
			} else {
				$btn.prop('disabled', false).text('📤 Zur Prüfung einreichen');
				showSubmitError(res.data.message || NazarenerScan.i18n.submitError);
				// Refresh captcha on wrong answer
				if (res.data.message && res.data.message.indexOf('Sicherheit') !== -1) {
					refreshCaptcha();
				}
			}
		})
		.fail(function(xhr) {
			$btn.prop('disabled', false).text('📤 Zur Prüfung einreichen');
			showSubmitError(xhr.status === 429 ? NazarenerScan.i18n.rateLimited : NazarenerScan.i18n.submitError);
		});
	}

	function showSubmitError(msg) {
		$('#ns-submit-error').text(msg).show();
	}

	function showError(msg) {
		// Generic error for lookup phase – show on result page
		$('#ns-result-green, #ns-result-red, #ns-result-yellow').hide();
		$('#ns-result-loading').hide();
		$('#ns-step-result').prepend(
			$('<div class="ns-error-banner">').text(msg).delay(4000).fadeOut(400)
		);
	}

	// ── Ingredient hints ──────────────────────────────────────────────────────

	function fetchHints(text) {
		if (!NazarenerScan.showHints || text.length < 4) {
			$('#ns-hints-banner').hide();
			return;
		}
		$.post(NazarenerScan.ajaxUrl, {
			action: 'nazarener_analyze_hints',
			nonce:  NazarenerScan.nonce,
			text:   text,
		}).done(function(res) {
			if (!res.success || !res.data.hints.length) { $('#ns-hints-banner').hide(); return; }
			var $list = $('#ns-hints-list').empty();
			res.data.hints.forEach(function(h) {
				$('<li>').addClass('ns-hint-' + h.type)
					.text((h.type === 'warning' ? '⚠️ ' : 'ℹ️ ') + h.message)
					.appendTo($list);
			});
			$('#ns-hints-banner').show();
		});
	}

	// ── Captcha refresh ───────────────────────────────────────────────────────

	function refreshCaptcha() {
		$.post(NazarenerScan.ajaxUrl, {
			action: 'nazarener_captcha_new',
			nonce:  NazarenerScan.nonce,
		}).done(function(res) {
			if (res.success) {
				$('#ns-captcha-token').val(res.data.token);
				$('#ns-captcha-question').text(res.data.question);
				$('#ns-captcha-answer').val('').focus();
			}
		});
	}

	// ── Notify me ────────────────────────────────────────────────────────────

	function registerNotifyMe() {
		var email = $('#ns-notify-email').val().trim();
		if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			$('#ns-notify-feedback').text('Bitte gültige E-Mail eingeben.').show();
			return;
		}
		$('#ns-notify-btn').prop('disabled', true).text('…');

		$.post(NazarenerScan.ajaxUrl, {
			action:  'nazarener_notify_me',
			nonce:   NazarenerScan.nonce,
			barcode: currentBarcode,
			email:   email,
		}).done(function(res) {
			$('#ns-notify-btn').prop('disabled', false).text('Benachrichtigen');
			$('#ns-notify-feedback').text(
				res.success ? NazarenerScan.i18n.notifySuccess : (res.data.message || NazarenerScan.i18n.notifyError)
			).show();
			if (res.success) {
				$('#ns-notify-email').prop('disabled', true);
				$('#ns-notify-btn').prop('disabled', true);
			}
		}).fail(function() {
			$('#ns-notify-btn').prop('disabled', false).text('Benachrichtigen');
			$('#ns-notify-feedback').text(NazarenerScan.i18n.notifyError).show();
		});
	}

	// ── Photo preview ─────────────────────────────────────────────────────────

	function setupPhotoPreview(inputId, previewId, removeId) {
		$('#' + inputId).on('change', function() {
			var file = this.files[0];
			if (!file) return;
			var reader = new FileReader();
			reader.onload = function(e) {
				$('#' + previewId).attr('src', e.target.result).show();
				$('#' + removeId).show();
				$('#' + inputId).closest('.ns-photo-upload').find('.ns-photo-label').hide();
			};
			reader.readAsDataURL(file);
		});
		$('#' + removeId).on('click', function() {
			$('#' + inputId).val('');
			$('#' + previewId).hide().attr('src', '');
			$(this).hide();
			$('#' + inputId).closest('.ns-photo-upload').find('.ns-photo-label').show();
		});
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	function escHtml(str) {
		return String(str).replace(/[&<>"']/g, function(c) {
			return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
		});
	}

	function escUrl(url) {
		try { return new URL(url).href; } catch(e) { return ''; }
	}

	// ── Init ──────────────────────────────────────────────────────────────────

	$(function() {
		// Set form timestamp
		$('#ns-form-time').val(Math.floor(Date.now() / 1000));

		// Offline banner
		updateOnlineStatus();

		// Scanner
		startScanner();

		// History
		renderHistory();

		// Manual barcode input
		$('#ns-barcode-submit').on('click', function() {
			lookupBarcode($('#ns-barcode-input').val());
		});
		$('#ns-barcode-input').on('keydown', function(e) {
			if (e.key === 'Enter') { e.preventDefault(); lookupBarcode($(this).val()); }
		});

		// History click
		$('#ns-history-list').on('click', '.ns-history-item', function() {
			lookupBarcode($(this).data('barcode'));
		});

		// Back buttons
		$('#ns-back-to-scan').on('click', function() {
			$('#ns-barcode-input').val('');
			showStep('ns-step-scan');
			startScanner();
			renderHistory();
		});
		$('#ns-back-to-result').on('click', function() {
			showStep('ns-step-result');
		});
		$('#ns-scan-again').on('click', function() {
			$('#ns-barcode-input').val('');
			$('#ns-submit-form')[0].reset();
			$('#ns-hints-banner').hide();
			showStep('ns-step-scan');
			startScanner();
			renderHistory();
		});

		// Open submit form
		$('#ns-open-submit').on('click', function() {
			// Prep form
			$('#ns-submit-barcode').val(currentBarcode);
			$('#ns-barcode-display').text(currentBarcode);
			$('#ns-product-name, #ns-ingredients-text, #ns-submitter-name, #ns-submitter-email').val('');
			$('#ns-privacy-consent').prop('checked', false);
			$('#ns-captcha-answer').val('');
			$('#ns-submit-error').hide();
			$('#ns-hints-banner').hide();
			$('#ns-product-photo, #ns-ingredients-photo').val('');
			$('#ns-product-photo-preview, #ns-ingredients-photo-preview').hide().attr('src', '');
			$('#ns-remove-product-photo, #ns-remove-ingredients-photo').hide();
			$('.ns-photo-label').show();
			$('#ns-submit-btn').prop('disabled', false).text('📤 Zur Prüfung einreichen');
			// Set fresh form time
			$('#ns-form-time').val(Math.floor(Date.now() / 1000));
			showStep('ns-step-submit');
		});

		// Submit
		$('#ns-submit-form').on('submit', submitProduct);

		// Hints on typing
		$('#ns-ingredients-text').on('input', function() {
			clearTimeout(hintsTimer);
			var text = $(this).val();
			hintsTimer = setTimeout(function() { fetchHints(text); }, 700);
		});

		// Captcha refresh
		$('#ns-captcha-refresh').on('click', refreshCaptcha);

		// Notify me
		$('#ns-notify-btn').on('click', registerNotifyMe);
		$('#ns-notify-email').on('keydown', function(e) {
			if (e.key === 'Enter') { e.preventDefault(); registerNotifyMe(); }
		});

		// Photo previews
		setupPhotoPreview('ns-product-photo',     'ns-product-photo-preview',     'ns-remove-product-photo');
		setupPhotoPreview('ns-ingredients-photo', 'ns-ingredients-photo-preview', 'ns-remove-ingredients-photo');
	});

	function updateOnlineStatus() {
		$('#ns-offline-banner').toggle(!navigator.onLine);
	}

}(jQuery));
