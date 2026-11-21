<?php

return [
	/**
	 * The database group of config/database.php that holds the table.
	 * null is the default database.
	 */
	'database' => null,

	/**
	 * For how many days messages are kept, for the task
	 * "./minion ublog:clean": "days" for warnings and lighter levels,
	 * "error_days" for errors and worse.
	 */
	'keep' => [
		'days'       => 90,
		'error_days' => 365,
	],

	/**
	 * Messages that are also sent by email.
	 *
	 * - to:      the addresses; none sends nothing
	 * - level:   this level and worse are sent (Log::ERROR: errors,
	 *            critical, alert, emergency)
	 * - types:   messages of these types are sent whatever their level
	 * - limit:   at most [how many emails, in how many seconds]; what
	 *            comes after that is in the log only
	 * - subject: the subject of the emails
	 *
	 * One email holds all such messages of a request: level, type,
	 * message, request code, error code and address. The context is
	 * not sent. With the ub_email module the email goes through it,
	 * otherwise through the mail() function of PHP.
	 */
	'alert' => [
		'to'      => [],
		'level'   => LOG_ERR,
		'types'   => [],
		'limit'   => [10, 3600],
		'subject' => 'Log alert',
	],
];
