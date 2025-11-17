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
		
		$.post(wu_opensrs.ajax_url, {
			action: 'wu_opensrs_check_domain',
			domain: domain,
			tld: tld,
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
	
	$('#wu-opensrs-domain-search').on('keypress', function(e) {
		if (e.which === 13) {
			e.preventDefault();
			$('#wu-opensrs-check-btn').click();
		}
	});
});