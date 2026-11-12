<?php
/**
 * Settings of the debug bar. Copy this file to application/config/ to
 * change them.
 *
 * The bar is only ever shown in the DEVELOPMENT environment: that is
 * fixed in the code and cannot be changed here.
 */
return [
	// Show the bar at the bottom of every HTML page?
	'enabled' => true,

	// The panels, in the order they are shown. Set one to false to leave it out.
	'panels' => [
		'timeline' => true,   // everything that was measured, on a time line
		'database' => true,   // queries, how long they took, repeated ones
		'cache'    => true,   // what the Cache module found and did not find
		'request'  => true,   // route, controller, parameters, headers, cookies
		'views'    => true,   // the views that were rendered
		'session'  => true,   // what the session holds
		'logs'     => true,   // log messages and notices of this request
		'routes'   => true,   // every route, the matched one marked
		'files'    => true,   // PHP files that were loaded
		'modules'  => true,   // enabled modules
		'server'   => true,   // PHP version, extensions, limits
		'ajax'     => true,   // background requests made by the page
		'custom'   => true,   // data added with Profiler::add()
		'config'   => false,  // config groups listed in "config_groups" below
	],

	// Config groups the "config" panel may show. Nothing is shown unless
	// it is listed here. Values with a secret name (password, key, salt,
	// token...) are hidden; still, do not list groups such as "database",
	// "encrypt" or "auth".
	'config_groups' => [],

	// Replace the text values written inside SQL ('...') by '***'. Queries
	// that use prepared statements (the default) hold no values at all.
	'mask_sql' => true,

	// Names of routes whose pages get no bar
	'exclude_routes' => ['docs/media', 'guide-search', 'codebench'],

	// A repeated query is marked from this many times on
	'duplicate_queries' => 2,

	// Groups of the timeline that start switched off; one click on their
	// name in the panel shows them
	'timeline_off' => ['classes', 'find file'],

	// The timeline leaves out what took less than this many milliseconds
	// (the many small file lookups of the framework); 0 shows everything
	'timeline_shortest' => 0.05,
];
