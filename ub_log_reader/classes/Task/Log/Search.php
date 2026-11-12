<?php
/**
 * Searches the logs of the application, the newest entries first.
 *
 *     ./minion log:search --level=error
 *     ./minion log:search --text=payment --from=yesterday
 *     ./minion log:search --reference=A1B2C3D4 --trace
 *
 * What looks like a password, a token or a key is shown as "***".
 *
 * Options:
 *  --level      this level and worse: emergency, alert, critical, error, warning, notice, info, debug
 *  --text       text in the message, the file, the address or the trace
 *  --reference  the reference code of an error, as shown on the error page
 *  --request    the code of a request, to see all that was logged in it (JSON logs)
 *  --from       the earliest day or time: 2026-11-12, yesterday, "-2 hours"
 *  --to         the latest day or time
 *  --limit      how many entries at most (50)
 *  --trace      show the trace of each error
 *  --dir        directory of the logs, application/logs when left out
 *
 * @package    UB_Log_Reader
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class Task_Log_Search extends Minion_Task {

	/**
	 * @var  array<int|string, mixed>
	 */
	protected array $_options = [
		'level'     => null,
		'text'      => null,
		'reference' => null,
		'request'   => null,
		'from'      => null,
		'to'        => null,
		'limit'     => 50,
		'trace'     => false,
		'dir'       => null,
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

		foreach (['level', 'text', 'reference', 'request', 'from', 'to'] as $name)
		{
			$filters[$name] = $params[$name] ?? null;
		}

		$entries = $reader->search($filters, is_numeric($params['limit']) ? (int) $params['limit'] : 50);

		$colors = ['EMERGENCY' => 'red', 'ALERT' => 'red', 'CRITICAL' => 'red', 'ERROR' => 'red', 'WARNING' => 'yellow', 'NOTICE' => 'yellow', 'INFO' => 'green', 'DEBUG' => 'cyan'];

		foreach ($entries as $entry)
		{
			$line = date('Y-m-d H:i:s', $entry['time'])
				.'  '.Minion_CLI::color(str_pad($entry['level'], 9), $colors[$entry['level']] ?? 'green')
				.' '.$entry['message'];

			if (is_string($entry['file']))
			{
				$line .= '  ('.$entry['file'].(is_int($entry['line']) ? ':'.$entry['line'] : '').')';
			}

			if (is_string($entry['request']))
			{
				$line .= '  request '.$entry['request'];
			}

			Minion_CLI::write($line);

			if ($params['trace'] !== false AND is_string($entry['trace']))
			{
				Minion_CLI::write('    '.str_replace("\n", "\n    ", $entry['trace']));
			}
		}

		Minion_CLI::write();

		$entries
			? Minion_CLI::success(count($entries).' '.Inflector::plural('entry', count($entries)).'.')
			: Minion_CLI::info('No entry fits.');

		return null;
	}

}
