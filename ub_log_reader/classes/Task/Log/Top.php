<?php
/**
 * Lists the messages that were logged most often: "which errors does
 * this site have?"
 *
 *     ./minion log:top --level=error --from="-1 week"
 *
 * Messages that differ only in their numbers and in the reference code
 * are counted as one. What looks like a password, a token or a key is
 * shown as "***".
 *
 * Options:
 *  --level      this level and worse: emergency, alert, critical, error, warning, notice, info, debug
 *  --text       text in the message, the file, the address or the trace
 *  --from       the earliest day or time: 2026-11-12, yesterday, "-2 hours"
 *  --to         the latest day or time
 *  --limit      how many messages at most (10)
 *  --dir        directory of the logs, application/logs when left out
 *
 * @package    UB_Log_Reader
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class Task_Log_Top extends Minion_Task {

	/**
	 * @var  array<int|string, mixed>
	 */
	protected array $_options = [
		'level' => null,
		'text'  => null,
		'from'  => null,
		'to'    => null,
		'limit' => 10,
		'dir'   => null,
	];

	/**
	 * Adds the rules for the options.
	 *
	 * @param   Validation  $validation
	 * @return  Validation
	 */
	public function build_validation(Validation $validation): Validation
	{
		return parent::build_validation($validation)
			->rule('limit', 'digit')
			->rule('level', 'in_array', [':value', array_merge(array_keys(UB_Log_Reader::LEVELS), array_map('strtolower', array_keys(UB_Log_Reader::LEVELS)))]);
	}

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 */
	protected function _execute(array $params): mixed
	{
		$reader  = new UB_Log_Reader(is_string($params['dir']) ? $params['dir'] : null);
		$filters = [];

		foreach (['level', 'text', 'from', 'to'] as $name)
		{
			$filters[$name] = $params[$name] ?? null;
		}

		$rows = [];

		foreach ($reader->top($filters, is_numeric($params['limit']) ? (int) $params['limit'] : 10) as $group)
		{
			$rows[] = [
				(string) $group['count'],
				$group['level'],
				date('Y-m-d H:i', $group['last']),
				// One line of the message is enough to know it
				Text::limit_chars( (string) strtok($group['message'], "\n"), 100, '...'),
			];
		}

		if ($rows === [])
		{
			Minion_CLI::info('No entry fits.');

			return null;
		}

		Minion_CLI::table($rows, ['Times', 'Level', 'Last', 'Message']);

		return null;
	}

}
