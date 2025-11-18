jQuery(document).ready(function($) {
	var checking = false;
	
	$('#wu-opensrs-check-btn').on('click', function() {
		if (checking) return;
		
		var domain = $('#wu-opensrs-domain-search').val().trim();
		var tld = $('#wu-opensrs-tld-select').val();
		
		if (!domain) {
			alert(wu_opensrs.error);
			return;
		}
		
		checking = true;
		$('#wu-opensrs-result').html('<div class="wu-alert wu-alert-info">' + wu_opensrs.checking + '</div>');
		$('#wu-opensrs-check-btn').prop('disabled', true);
		
		var productId = $('#wu-opensrs-product-id').val() || '';
		var productProvider = $('#wu-opensrs-product-provider').val() || '';

		$.post(wu_opensrs.ajax_url, {
			action: 'wu_opensrs_check_domain',
			domain: domain,
			tld: tld,
			product_id: productId,
			nonce: wu_opensrs.nonce
		}, function(response) {
			checking = false;
			$('#wu-opensrs-check-btn').prop('disabled', false);
			
			if (response.success && response.data.available) {
				$('#wu-opensrs-result').html(
					'<div class="wu-alert wu-alert-success">' + wu_opensrs.available + '</div>'
				);
				$('#wu-opensrs-domain-available').val('1');
				$('#wu-opensrs-domain-full').val(response.data.domain);
				$('#wu-opensrs-domain-price').val(response.data.price);
				$('#wu-opensrs-price').text(response.data.formatted_price);
				$('#wu-opensrs-pricing').show();
			} else if (response.success && !response.data.available) {
				$('#wu-opensrs-result').html(
					'<div class="wu-alert wu-alert-error">' + wu_opensrs.unavailable + '</div>'
				);
				$('#wu-opensrs-domain-available').val('0');
				$('#wu-opensrs-pricing').hide();
			} else {
				$('#wu-opensrs-result').html(
					'<div class="wu-alert wu-alert-error">' + wu_opensrs.error + '</div>'
				);
			}
		}).fail(function() {
			checking = false;
			$('#wu-opensrs-check-btn').prop('disabled', false);
			$('#wu-opensrs-result').html(
				'<div class="wu-alert wu-alert-error">' + wu_opensrs.error + '</div>'
			);
		});
	});

	// Validate contact fields client-side when provider is NameCheap
	$('#wu-opensrs-check-btn').on('click', function() {
		var provider = $('#wu-opensrs-product-provider').val() || '';
		if (provider === 'namecheap') {
			var required = ['domain_contact[first_name]', 'domain_contact[last_name]', 'domain_contact[email]', 'domain_contact[phone]', 'domain_contact[addr1]', 'domain_contact[city]', 'domain_contact[postal_code]', 'domain_contact[country]'];
			var missing = [];
			required.forEach(function(name) {
				var el = $('input[name="' + name + '"]');
				if (!el.length || !el.val().trim()) {
					missing.push(name);
				}
			});
			if (missing.length) {
				alert(wu_opensrs.contact_required || 'Please complete the registrant contact details to proceed with registration.');
				return;
			}
		}
	});

	// Prevent final form submission if NameCheap contact fields are missing
	var checkoutForm = $('#wu-opensrs-domain-widget').closest('form');
	if (checkoutForm.length) {
		checkoutForm.on('submit', function(e) {
			var provider = $('#wu-opensrs-product-provider').val() || '';
			if (provider === 'namecheap') {
				var required = ['domain_contact[first_name]', 'domain_contact[last_name]', 'domain_contact[email]', 'domain_contact[phone]', 'domain_contact[addr1]', 'domain_contact[city]', 'domain_contact[postal_code]', 'domain_contact[country]'];
				var missing = [];
				required.forEach(function(name) {
					var el = $('input[name="' + name + '"]');
					if (!el.length || !el.val().trim()) {
						missing.push(name);
					}
				});
				if (missing.length) {
					e.preventDefault();
					alert(wu_opensrs.contact_required || 'Please complete the registrant contact details to proceed with registration.');
					// Optionally focus the first missing field
					var first = $('input[name="' + missing[0] + '"]');
					if (first.length) first.focus();
					return false;
				}
			}
		});
	}

	// Show an alert if server redirected back with missing contact error
	if (window.location.search.indexOf('wu_domain_error=missing_contact') !== -1) {
		alert(wu_opensrs.contact_required || 'Please complete the registrant contact details to proceed with registration.');
	}
	
	$('#wu-opensrs-domain-search').on('keypress', function(e) {
		if (e.which === 13) {
			e.preventDefault();
			$('#wu-opensrs-check-btn').click();
		}
	});
});

// Handle auto-renew toggle
$('.wu-toggle-auto-renew').on('change', function() {
	var checkbox = $(this);
	var domainId = checkbox.data('domain-id');
	var enabled = checkbox.is(':checked');
	
	$.post(ajaxurl, {
		action: 'wu_toggle_auto_renew',
		domain_id: domainId,
		enabled: enabled ? 1 : 0,
		nonce: '<?php echo wp_create_nonce( "wu-domain-management" ); ?>'
	}, function(response) {
		if (!response.success) {
			checkbox.prop('checked', !enabled);
			alert('<?php esc_html_e( "Error updating auto-renewal setting", "wu-opensrs" ); ?>');
		}
	});
});