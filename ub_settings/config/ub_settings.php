<?php

return [
	/**
	 * The database group of config/database.php that holds the table.
	 * null is the default database.
	 */
	'database' => null,

	/**
	 * The name of the table, without the prefix of the connection. The
	 * migration (./minion settings:migration) creates "settings": change
	 * the name there as well.
	 */
	'table' => 'settings',

	/**
	 * For how many seconds the settings are kept in the cache
	 * (Kohana::cache()) between requests. 0 reads the table once in every
	 * request that uses a setting.
	 *
	 * A change made with UB_Settings clears the cache. When several
	 * servers each have a cache of their own (the file cache), the
	 * others see a change only when their copy runs out.
	 */
	'cache' => 0,
];
