/**
 * Settings screen helper: the connection test button.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.getElementById( 'email-guard-test-button' );
		var result = document.getElementById( 'email-guard-test-result' );
		if ( ! button || ! result || 'undefined' === typeof window.emailsherlockEmailGuard ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var config = window.emailsherlockEmailGuard;
			var apiKey = document.getElementById( 'email-guard-api-key' );
			var siteKey = document.getElementById( 'email-guard-site-key' );

			button.disabled = true;
			result.textContent = config.testing;
			result.style.color = '';

			var body = new URLSearchParams();
			body.append( 'action', config.action );
			body.append( 'nonce', config.nonce );
			body.append( 'api_key', apiKey ? apiKey.value : '' );
			body.append( 'site_key', siteKey ? siteKey.value : '' );

			fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( payload ) {
					var ok = payload && payload.success;
					var message = payload && payload.data && payload.data.message ? payload.data.message : config.failed;
					result.textContent = message;
					result.style.color = ok ? '#00a32a' : '#d63638';
				} )
				.catch( function () {
					result.textContent = config.failed;
					result.style.color = '#d63638';
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	} );
}() );
