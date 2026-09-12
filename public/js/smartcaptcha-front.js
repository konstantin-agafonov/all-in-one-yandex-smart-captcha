function smartCaptchaProcessForm(form) {
	const existing = form.querySelector('input[name="smartcaptcha_token"]');
	if (existing) return;

	const input = document.createElement('input');
	input.type = 'hidden';
	input.name = 'smartcaptcha_token';

	const container = document.createElement('div');
	container.className = 'smartcaptcha-container';

	const submit = form.querySelector('.wpcf7-submit');

	const resume = () => {
		if (form.getAttribute('data-smartcaptcha') !== 'waiting') {
			return;
		}
		form.setAttribute('data-smartcaptcha', 'done');
		form.appendChild(input);
		const btn = form.querySelector('.wpcf7-submit, input[type="submit"], button[type="submit"]');
		if (btn) {
			btn.click();
		} else {
			form.requestSubmit();
		}
	};

	let widget;
	try {
		widget = smartCaptcha.render(container, {
			sitekey: smartcaptchaConfig.sitekey,
			invisible: true,
			callback: function (token) {
				input.value = token;
				resume();
			},
		});
	} catch (e) {
		console.warn('SmartCaptcha render error:', e);
		return;
	}

	form.__smartcaptchaWidgetId = widget;

	const resetCaptcha = () => {
		input.value = '';
		form.setAttribute('data-smartcaptcha', 'done');
		try {
			smartCaptcha.reset(widget);
		} catch (e) {
			console.warn('SmartCaptcha reset error:', e);
		}
	};

	['wpcf7mailsent', 'wpcf7failed', 'wpcf7spam', 'wpcf7invalid'].forEach((eventName) => {
		form.addEventListener(eventName, resetCaptcha);
	});
	form.addEventListener('reset', resetCaptcha);

	if (submit && submit.parentNode) {
		submit.parentNode.insertBefore(container, submit);
	} else {
		form.appendChild(container);
	}
}

function smartCaptchaOnSubmit(event) {
	if (event.defaultPrevented || !event.target || event.target.tagName !== 'FORM') {
		return;
	}

	const form = event.target;
	const widgetId = form.__smartcaptchaWidgetId;

	if (!widgetId) {
		return;
	}

	const input = form.querySelector('input[name="smartcaptcha_token"]');
	if (input && input.value) {
		return;
	}

	event.preventDefault();
	event.stopImmediatePropagation();

	if (form.getAttribute('data-smartcaptcha') === 'waiting') {
		return;
	}

	form.setAttribute('data-smartcaptcha', 'waiting');
	try {
		smartCaptcha.execute(widgetId);
	} catch (e) {
		console.warn('SmartCaptcha execute error:', e);
		form.setAttribute('data-smartcaptcha', 'done');
	}
}

function smartCaptchaInit() {
	if (typeof smartCaptcha === 'undefined' || typeof smartcaptchaConfig === 'undefined' || !smartcaptchaConfig.sitekey) {
		return;
	}

	window.smartcaptchaReady = true;

	const cf7Selector = '.wpcf7-form';
	const defaultSelector = 'form:not(.wpcf7-form)';

	document.querySelectorAll(defaultSelector).forEach(smartCaptchaProcessForm);
	document.querySelectorAll(cf7Selector).forEach(smartCaptchaProcessForm);

	document.addEventListener('submit', smartCaptchaOnSubmit, true);
}

window.addEventListener('load', smartCaptchaInit);