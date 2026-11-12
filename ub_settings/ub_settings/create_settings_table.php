<?php
/**
 * Creates the table of the ub_settings module: one row for each
 * setting, its name and its value as JSON.
 *
 * Works on MySQL/MariaDB, PostgreSQL and SQLite. This file is yours: to
 * give the table another name, change it here and in
 * config/ub_settings.php.
 */
return new class extends Migration {

	/**
	 * Makes the table.
	 */
	public function up(Database $db): void
	{
		// MySQL has "value" as a word of its own: the names are quoted
		$db->query(null, 'CREATE TABLE '.$db->quote_table('settings').' ('
			.$db->quote_column('name').' VARCHAR(100) NOT NULL PRIMARY KEY, '
			.$db->quote_column('value').' TEXT NOT NULL)');
	}

	/**
	 * Removes the table, with all settings in it.
	 */
	public function down(Database $db): void
	{
		$db->query(null, 'DROP TABLE '.$db->quote_table('settings'));
	}

};
