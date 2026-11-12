/*
 * Behaviour of the debug bar. Plain JavaScript, no library, no inline
 * event handlers.
 */
(function () {
	'use strict';

	var bar = document.getElementById('ub-debug');

	if ( ! bar || bar.dataset.kDbReady)
		return;

	bar.dataset.kDbReady = '1';

	// What was open is remembered while the browser tab lives. Storage
	// can be switched off in the browser: the bar works without it.
	var store = {
		get: function (key) { try { return sessionStorage.getItem('ub-debug-' + key); } catch (e) { return null; } },
		set: function (key, value) { try { sessionStorage.setItem('ub-debug-' + key, value); } catch (e) {} }
	};

	// Shows one panel, or none
	function open(name) {
		bar.querySelectorAll('.k-db-panel').forEach(function (panel) {
			panel.hidden = (panel.id !== 'k-db-panel-' + name);
		});

		bar.querySelectorAll('[data-k-db-tab]').forEach(function (tab) {
			tab.setAttribute('aria-expanded', tab.dataset.kDbTab === name ? 'true' : 'false');
		});

		store.set('open', name || '');
	}

	// Leaves room at the bottom of the page, so the bar covers nothing
	function makeRoom() {
		document.body.style.paddingBottom = bar.classList.contains('k-db-minimized')
			? ''
			: bar.querySelector('.k-db-bar').offsetHeight + 'px';
	}

	bar.addEventListener('click', function (event) {
		var button = event.target.closest('button');

		if ( ! button)
			return;

		if (button.dataset.kDbGroup) {
			// The filter of the timeline: show or hide the rows of a group
			var show = button.getAttribute('aria-pressed') !== 'true';

			button.setAttribute('aria-pressed', show ? 'true' : 'false');

			bar.querySelectorAll('[data-k-db-row]').forEach(function (row) {
				if (row.dataset.kDbRow === button.dataset.kDbGroup)
					row.hidden = ! show;
			});
		}
		else if (button.dataset.kDbMore) {
			// A list that starts closed
			var list = document.getElementById(button.dataset.kDbMore);

			list.hidden = ! list.hidden;
			button.textContent = list.hidden ? 'show' : 'hide';
		}
		else if (button.dataset.kDbTab) {
			// A second click on the open tab closes it
			open(button.getAttribute('aria-expanded') === 'true' ? '' : button.dataset.kDbTab);
		}
		else if ('kDbClose' in button.dataset) {
			open('');
		}
		else if ('kDbMinimize' in button.dataset) {
			store.set('minimized', bar.classList.toggle('k-db-minimized') ? '1' : '');
			makeRoom();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape')
			open('');
	});

	// The bars of the timeline: placed here, because a strict
	// Content-Security-Policy allows no style attributes in the HTML
	bar.querySelectorAll('[data-k-db-left]').forEach(function (span) {
		span.style.left = span.dataset.kDbLeft + '%';
		span.style.width = span.dataset.kDbWidth + '%';
	});

	if (store.get('minimized'))
		bar.classList.add('k-db-minimized');

	if (store.get('open') && document.getElementById('k-db-panel-' + store.get('open')))
		open(store.get('open'));

	makeRoom();
	window.addEventListener('resize', makeRoom);

	// The AJAX panel: the browser tells about every background request
	// of the page. The server puts its own numbers in the Server-Timing
	// header of each response.
	var rows = document.getElementById('k-db-ajax-rows');
	var badge = document.getElementById('k-db-badge-ajax');
	var count = 0;

	if (rows && window.PerformanceObserver) {
		var cell = function (row, text, number) {
			var td = document.createElement('td');
			td.textContent = text;

			if (number)
				td.className = 'k-db-number';

			row.appendChild(td);
		};

		try {
			new PerformanceObserver(function (list) {
				list.getEntries().forEach(function (entry) {
					if (entry.initiatorType !== 'fetch' && entry.initiatorType !== 'xmlhttprequest')
						return;

					var server = (entry.serverTiming || []).map(function (timing) {
						return timing.name + (timing.duration ? ' ' + Math.round(timing.duration) + ' ms' : '') + (timing.description ? ' (' + timing.description + ')' : '');
					}).join(', ');

					var row = document.createElement('tr');

					cell(row, entry.name.replace(location.origin, ''));
					cell(row, entry.responseStatus ? String(entry.responseStatus) : '', true);
					cell(row, Math.round(entry.duration) + ' ms', true);
					cell(row, server);

					rows.appendChild(row);

					var empty = document.getElementById('k-db-ajax-empty');

					if (empty)
						empty.hidden = true;

					if (badge)
						badge.textContent = String(++count);
				});
			}).observe({type: 'resource', buffered: true});
		}
		catch (e) {
			// An older browser: the panel stays empty
		}
	}
})();
