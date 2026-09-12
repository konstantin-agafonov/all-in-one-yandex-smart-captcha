function smartcaptchaProcessForm(form) {
	const existing = form.querySelector('input[name="smartcaptcha_token"]');
	if (existing) return;

	const container = document.createElement('div');
	container.className = 'smartcaptcha-container';
	container.style.display = 'none';
	form.appendChild(container);

	try {
		smartcaptcha.render(container, {
			sitekey: smartcaptchaConfig.sitekey,
			invisible: true,
			callback: function (token) {
				let input = form.querySelector('input[name="smartcaptcha_token"]');
				if (!input) {
					input = document.createElement('input');
					input.type = 'hidden';
					input.name = 'smartcaptcha_token';
					form.appendChild(input);
				}
				input.value = token;
			},
		});
	} catch (e) {
		console.warn('SmartCaptcha render error:', e);
	}
}

window.onloadSmartcaptcha = function () {
	window.smartcaptchaReady = true;

	if (typeof smartcaptchaConfig === 'undefined' || !smartcaptchaConfig.sitekey) {
		return;
	}

	const cf7Selector = '.wpcf7-form';
	const defaultSelector = 'form:not(.wpcf7-form)';

	function processAllForms() {
		document.querySelectorAll(defaultSelector).forEach(smartcaptchaProcessForm);
		document.querySelectorAll(cf7Selector).forEach(smartcaptchaProcessForm);
	}

	if (document.readyState === 'complete') {
		processAllForms();
	} else {
		window.addEventListener('load', processAllForms);
	}
};
