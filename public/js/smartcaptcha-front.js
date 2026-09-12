function smartcaptchaProcessForm(form) {
	const existing = form.querySelector('input[name="smartcaptcha_token"]');
	if (existing) return;

	const container = document.createElement('div');
	container.className = 'smartcaptcha-container';
	container.style.cssText = 'position:fixed;left:-9999px;width:300px;height:65px;visibility:hidden;';
	form.appendChild(container);

	try {
		smartCaptcha.render(container, {
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

function smartCaptchaInit() {
	if (typeof smartCaptcha === 'undefined' || typeof smartcaptchaConfig === 'undefined' || !smartcaptchaConfig.sitekey) {
		return;
	}

	window.smartcaptchaReady = true;

	const cf7Selector = '.wpcf7-form';
	const defaultSelector = 'form:not(.wpcf7-form)';

    document.querySelectorAll(defaultSelector).forEach(smartcaptchaProcessForm);
    document.querySelectorAll(cf7Selector).forEach(smartcaptchaProcessForm);
}

window.addEventListener('load', smartCaptchaInit);
