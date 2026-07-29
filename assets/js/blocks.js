(function () {
	'use strict';
	var settings = window.wc.wcSettings.getSetting('mrn_ir_payment_data', {});
	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var decode = window.wp.htmlEntities.decodeEntities;

	var Label = function (props) {
		return el('span', null, decode(settings.title || 'MRN پرداخت ایران'));
	};

	var Content = function (props) {
		var providers = settings.providers || [];
		var state = useState(providers.length ? providers[0].slug : '');
		var selected = state[0];
		var setSelected = state[1];
		var onPaymentSetup = props.eventRegistration.onPaymentSetup;

		window.wp.element.useEffect(function () {
			return onPaymentSetup(function () {
				return {
					type: props.emitResponse.responseTypes.SUCCESS,
					meta: { paymentMethodData: [{ key: 'mrn_ir_provider', value: selected }] }
				};
			});
		}, [selected, onPaymentSetup]);

		return el('div', { className: 'mrn-ir-block-content' },
			el('p', null, decode(settings.description || '')),
			providers.map(function (provider) {
				return el('label', { className: 'mrn-ir-checkout-provider', key: provider.slug },
					el('input', {
						type: 'radio',
						name: 'mrn_ir_provider_block',
						value: provider.slug,
						checked: selected === provider.slug,
						onChange: function () { setSelected(provider.slug); }
					}),
					el('span', { className: 'mrn-ir-provider-dot', style: { '--mrn-provider': provider.accent } }),
					el('span', null,
						el('strong', null, decode(provider.name)),
						el('small', null, (provider.mode === 'installment' ? 'اقساطی' : 'آنلاین') + ' · ' + decode(provider.description))
					)
				);
			})
		);
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod({
		name: 'mrn_ir_payment',
		label: el(Label),
		content: el(Content),
		edit: el(Content),
		canMakePayment: function () { return (settings.providers || []).length > 0; },
		ariaLabel: decode(settings.title || 'MRN پرداخت ایران'),
		supports: { features: settings.supports || ['products'] }
	});
}());
