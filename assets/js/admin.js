(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var expand = event.target.closest('.mrn-ir-expand');
		if (expand) {
			var card = expand.closest('.mrn-ir-gateway-card');
			var open = card.classList.toggle('is-open');
			expand.setAttribute('aria-expanded', open ? 'true' : 'false');
		}

		var check = event.target.closest('.mrn-ir-check');
		if (!check) {
			return;
		}
		var result = check.parentNode.querySelector('.mrn-ir-check-result');
		var data = new FormData();
		data.append('action', 'mrn_ir_check_provider');
		data.append('nonce', window.mrnIrPayment.nonce);
		data.append('provider', check.dataset.provider);
		check.disabled = true;
		result.className = 'mrn-ir-check-result';
		result.textContent = window.mrnIrPayment.texts.checking;

		fetch(window.mrnIrPayment.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (response) { return response.json(); })
			.then(function (payload) {
				result.classList.add(payload.success ? 'success' : 'error');
				result.textContent = payload.data && payload.data.message ? payload.data.message : window.mrnIrPayment.texts.error;
			})
			.catch(function () {
				result.classList.add('error');
				result.textContent = window.mrnIrPayment.texts.error;
			})
			.finally(function () { check.disabled = false; });
	});
}());
