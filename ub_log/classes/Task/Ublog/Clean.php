<?php
/**
 * Removes old messages from the table of the log: those that are older
 * than "keep" in config/ub_log.php allows.
 *
 *     ./minion ublog:clean
 *
 * Run it once a day, from the scheduler of the server (cron).
 *
 * @package    UB_Log
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class Task_Ublog_Clean extends Minion_Task {

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 */
	protected function _execute(array $params): mixed
	{
		Minion_CLI::success('Removed '.UB_Log::clean().' old message(s) from the log');

		return null;
	}

}
