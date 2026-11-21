/*
 * Makes the messages work: the button closes one, and a floating
 * message with a time goes away by itself. Messages that a script adds
 * to the page later work too: the click is caught on the document.
 */
(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-ub-message-close]') : null;
		var message = button ? button.closest('[data-ub-message]') : null;

		if (message) {
			message.remove();
		}
	});

	function timers() {
		document.querySelectorAll('[data-ub-message-duration]').forEach(function (message) {
			var time = parseInt(message.getAttribute('data-ub-message-duration'), 10);

			message.removeAttribute('data-ub-message-duration');

			if (time > 0) {
				setTimeout(function () { message.remove(); }, time);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', timers);
	} else {
		timers();
	}
})();
