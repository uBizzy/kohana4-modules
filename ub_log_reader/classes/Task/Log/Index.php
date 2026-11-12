<?php
/**
 * Brings the index of the logs up to date, or makes it again.
 *
 *     ./minion log:index
 *     ./minion log:index --rebuild
 *
 * The index keeps itself up to date with every search: this task is
 * for making it before the first search of many logs, and for starting
 * again from nothing.
 *
 * Needs "index" set to true in config/ub_log_reader.php.
 *
 * Options:
 *  --rebuild    empty the index and read every log file again
 *  --dir        directory of the logs, application/logs when left out
 *
 * @package    UB_Log_Reader
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class Task_Log_Index extends Minion_Task {

	/**
	 * @var  array<int|string, mixed>
	 */
	protected array $_options = [
		'rebuild' => false,
		'dir'     => null,
	];

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 * @throws  Kohana_Exception  when no index is used
	 */
	protected function _execute(array $params): mixed
	{
		$reader = new UB_Log_Reader(is_string($params['dir']) ? $params['dir'] : null);
		$index  = $reader->index();

		if ($index === null)
			throw new Kohana_Exception('No index is used: set "index" to true in config/ub_log_reader.php (PHP needs the pdo_sqlite extension)');

		if ($params['rebuild'] !== false)
		{
			$reader->reindex();
		}
		else
		{
			// A search brings the index up to date before it looks
			$reader->search([], 1);
		}

		Minion_CLI::success(number_format($index->count()).' '.Inflector::plural('entry', $index->count()).' in '.Debug::path($index->path()));

		return null;
	}

}
