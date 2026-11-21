<?php
/**
 * Writes the migration that creates the table of the ub_log module
 * in application/migrations.
 *
 *     ./minion ublog:migration
 *     ./minion migration:run
 *
 * The file is a copy of ub_log/create_ub_logs_table.php of the
 * module, with the date of today in its name. It belongs to your
 * application from then on.
 *
 * Needs the migration module.
 *
 * @package    UB_Log
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class Task_Ublog_Migration extends Minion_Task {

	/**
	 * @var  string  What the migration is called, after the date and time
	 */
	public const string NAME = 'create_ub_logs_table';

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 * @throws  Kohana_Exception  when the migration module is off, or the migration is already there
	 */
	protected function _execute(array $params): mixed
	{
		if ( ! class_exists('Migration_Runner'))
			throw new Kohana_Exception('Enable the migration module first: the table of the log is created by a migration');

		$runner = new Migration_Runner;

		foreach (array_keys($runner->all()) as $name)
		{
			// Written twice, the second one would fail on a table that exists
			if (str_ends_with($name, '_'.static::NAME))
				throw new Kohana_Exception('The migration :name is already there', [':name' => $name]);
		}

		$file    = Kohana::find_file('ub_log', static::NAME);
		$content = is_string($file) ? file_get_contents($file) : false;

		if ($content === false)
			throw new Kohana_Exception('The file ub_log/:name.php of the module was not found', [':name' => static::NAME]);

		$file = $runner->create(static::NAME, null, $content);

		Minion_CLI::success('Created '.Debug::path($file));
		Minion_CLI::write('Apply it with: ./minion migration:run');

		return null;
	}

}
