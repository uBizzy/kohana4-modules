<?php
/**
 * Creates the table of the ub_log module: one row for each message of
 * the log.
 *
 * Works on MySQL/MariaDB, PostgreSQL and SQLite. Times are kept as
 * whole numbers (seconds since 1970, UTC); "level" is the number of
 * the level, 0 (emergency) to 7 (debug), so that "this level and
 * worse" is one comparison. This file is yours: change it before you
 * run it.
 */
return new class extends Migration {

	/**
	 * Makes the table.
	 */
	public function up(Database $db): void
	{
		// The column that numbers the rows is spelled differently by each database
		$id = match ($db->driver()) {
			'mysql'  => 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'pgsql'  => 'id BIGSERIAL PRIMARY KEY',
			'sqlite' => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
			default  => throw new Kohana_Exception('The ub_logs table can be created on MySQL, PostgreSQL and SQLite only'),
		};

		$table = $db->quote_table('ub_logs');

		// Some of these names are words of SQL: they are quoted
		$q = fn (string $column): string => $db->quote_column($column);

		$db->query(null, 'CREATE TABLE '.$table.' ('
			.$id.', '
			.$q('time').' INTEGER NOT NULL, '
			.$q('level').' SMALLINT NOT NULL, '
			.$q('type').' VARCHAR(64) NULL, '
			.$q('message').' TEXT NOT NULL, '
			.$q('context').' TEXT NULL, '
			.$q('request').' VARCHAR(8) NOT NULL, '
			.$q('reference').' VARCHAR(16) NULL, '
			.$q('user_id').' INTEGER NULL, '
			.$q('ip').' VARCHAR(45) NOT NULL, '
			.$q('method').' VARCHAR(10) NOT NULL, '
			.$q('uri').' VARCHAR(255) NOT NULL, '
			.$q('file').' VARCHAR(255) NULL, '
			.$q('line').' INTEGER NULL)');

		// Index names are shared by all tables on PostgreSQL and SQLite, so
		// they carry the table prefix too
		$prefix = $db->table_prefix();

		$db->query(null, 'CREATE INDEX '.$prefix.'ub_logs_time ON '.$table.' ('.$q('time').')');
		$db->query(null, 'CREATE INDEX '.$prefix.'ub_logs_level ON '.$table.' ('.$q('level').', '.$q('time').')');
		$db->query(null, 'CREATE INDEX '.$prefix.'ub_logs_type ON '.$table.' ('.$q('type').', '.$q('time').')');
		$db->query(null, 'CREATE INDEX '.$prefix.'ub_logs_request ON '.$table.' ('.$q('request').')');
		$db->query(null, 'CREATE INDEX '.$prefix.'ub_logs_reference ON '.$table.' ('.$q('reference').')');
		$db->query(null, 'CREATE INDEX '.$prefix.'ub_logs_user ON '.$table.' ('.$q('user_id').')');
	}

	/**
	 * Removes the table, with the log in it.
	 */
	public function down(Database $db): void
	{
		$db->query(null, 'DROP TABLE '.$db->quote_table('ub_logs'));
	}

};
