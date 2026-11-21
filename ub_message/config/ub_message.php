<?php

return [
	/**
	 * Which class names the messages get:
	 *
	 * - 'theme'  those of the module, with the colours of the framework
	 *            theme (config/theme.php)
	 * - 'bulma'  those of Bulma ("notification is-success"), for a site
	 *            that loads Bulma itself. Class names only: the module
	 *            loads nothing.
	 */
	'style' => 'theme',

	/**
	 * After how many milliseconds a floating message goes away by
	 * itself. 0 keeps it until it is closed.
	 */
	'duration' => 0,

	/**
	 * Do messages have a button that closes them?
	 */
	'dismissible' => true,

	/**
	 * The view that prints a list of messages.
	 */
	'view' => 'ub_message/list',
];
