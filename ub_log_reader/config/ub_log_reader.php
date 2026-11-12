<?php
/**
 * Settings of the log reader. Copy this file to application/config/ to
 * change them.
 */
return [
	// The directory of the log files; application/logs when null
	'directory' => null,

	// Have the page at "ub-log-reader"? The Minion task works either way.
	'page' => true,

	/**
	 * The page outside the DEVELOPMENT environment. It is only there
	 * when BOTH are set: "production" is true, and the address of the
	 * visitor is in "allowed_ips". Everybody else gets "not found".
	 *
	 * An entry of the list is one address or a network:
	 *
	 *     'allowed_ips' => ['203.0.113.7', '10.0.0.0/8', '2001:db8::/32'],
	 *
	 * Behind a proxy or a load balancer, name it in
	 * Request::$trusted_proxies first: without that, every visitor has
	 * the address of the proxy, and listing it here lets everybody in.
	 */
	'production'  => false,
	'allowed_ips' => [],

	/**
	 * Keep an index of the logs in a SQLite database, for faster
	 * searches when there are many logs. It is a file beside the logs
	 * (ub_log_reader.sqlite), about as large as they are, that keeps
	 * itself up to date and can be deleted at any time.
	 *
	 * Needs the pdo_sqlite extension of PHP; without it the log files
	 * are read, as with false.
	 */
	'index' => false,
];
