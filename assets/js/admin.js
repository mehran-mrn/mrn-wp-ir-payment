(function () {
	'use strict';

	var gatewayFilter = 'all';
	var gatewaySearch = '';

	function filterGateways() {
		var visible = 0;
		document.querySelectorAll('.mrn-ir-gateway-card').forEach(function (card) {
			var groupMatch = gatewayFilter === 'all' || card.dataset.group === gatewayFilter;
			var textMatch = !gatewaySearch || (card.dataset.search || '').toLocaleLowerCase('fa-IR').indexOf(gatewaySearch) !== -1;
			var show = groupMatch && textMatch;
			card.hidden = !show;
			if (show) {
				visible += 1;
			}
		});
		var empty = document.querySelector('.mrn-ir-gateway-empty');
		if (empty) {
			empty.hidden = visible !== 0;
		}
	}

	var gatewaySearchInput = document.getElementById('mrn-ir-gateway-search');
	if (gatewaySearchInput) {
		gatewaySearchInput.addEventListener('input', function () {
			gatewaySearch = gatewaySearchInput.value.trim().toLocaleLowerCase('fa-IR');
			filterGateways();
		});
	}

	document.addEventListener('click', function (event) {
		var filter = event.target.closest('.mrn-ir-filter-chips button');
		if (filter) {
			document.querySelectorAll('.mrn-ir-filter-chips button').forEach(function (button) {
				button.classList.toggle('active', button === filter);
			});
			gatewayFilter = filter.dataset.filter;
			filterGateways();
			return;
		}

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
