<?php
/**
 * Searches the log files of the application: by level, text, date,
 * the reference code of an error, or the request it happened in.
 *
 *     $reader = new UB_Log_Reader;
 *
 *     // The newest 50 errors of today that mention "payment"
 *     $entries = $reader->search(['level' => 'ERROR', 'text' => 'payment', 'from' => 'today'], 50);
 *
 *     // The error a visitor reported by its code
 *     $entries = $reader->search(['reference' => 'A1B2C3D4']);
 *
 * Both kinds of log file are read: the text files of [Log_File] and
 * the JSON files of [Log_JSON]. For a day that has both, the JSON file
 * is used: it holds the same messages with more detail.
 *
 * [!!] Logs hold private data. What looks like a password, a token or
 * a key is replaced by "***" in what this class returns, unless
 * [UB_Log_Reader::$mask] is switched off.
 *
 * @phpstan-type Entry array{time: int, level: string, message: string, file: string|null, line: int|null, reference: string|null, request: string|null, method: string|null, uri: string|null, exception: string|null, trace: string|null}
 *
 * @package    UB_Log_Reader
 * @category   Base
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Log_Reader {

	/**
	 * @var  array<string, int>  The levels, the worst first
	 */
	public const array LEVELS = [
		'EMERGENCY' => 0,
		'ALERT'     => 1,
		'CRITICAL'  => 2,
		'ERROR'     => 3,
		'WARNING'   => 4,
		'NOTICE'    => 5,
		'INFO'      => 6,
		'DEBUG'     => 7,
	];

	/**
	 * @var  int  The most entries one search gives
	 */
	public const int MAX = 1000;

	/**
	 * @var  bool  Replace what looks like a secret by "***"
	 */
	public bool $mask = true;

	/**
	 * @var  string  The directory of the log files, with a separator at its end
	 */
	protected string $_directory;

	/**
	 * @var  UB_Log_Index|null  The index, when one is used
	 */
	protected ?UB_Log_Index $_index = null;

	/**
	 * @param   string|null  $directory  the directory of the log files; the one of
	 *                                   config/ub_log_reader.php, or application/logs, when null
	 * @throws  Kohana_Exception  when the directory is not there
	 */
	public function __construct(?string $directory = null)
	{
		$configured = Kohana::$config->load('ub_log_reader.directory');

		$directory ??= is_string($configured) ? $configured : APPPATH.'logs';

		if ( ! is_dir($directory))
			throw new Kohana_Exception('The directory of the logs is not there: :dir', [':dir' => Debug::path($directory)]);

		$this->_directory = realpath($directory).DIRECTORY_SEPARATOR;

		// Without SQLite in PHP the files are read, as without an index
		if (Kohana::$config->load('ub_log_reader.index') === true AND UB_Log_Index::available())
		{
			$this->_index = new UB_Log_Index($this->_directory);
		}
	}

	/**
	 * Returns the index of the logs, or null when none is used: see
	 * "index" in config/ub_log_reader.php.
	 *
	 * @return  UB_Log_Index|null
	 */
	public function index(): ?UB_Log_Index
	{
		return $this->_index;
	}

	/**
	 * Makes the index again from the log files.
	 *
	 * @return  int  how many entries it holds
	 * @throws  Kohana_Exception  when no index is used
	 */
	public function reindex(): int
	{
		if ($this->_index === null)
			throw new Kohana_Exception('No index is used: set "index" to true in config/ub_log_reader.php (PHP needs the pdo_sqlite extension)');

		$this->_index->clear();

		return $this->_index->sync($this->_files(), $this->_entries(...));
	}

	/**
	 * Tells whether the page that shows the logs may be read from an
	 * address.
	 *
	 * - Never, when the page is switched off ("page" is false).
	 * - In the DEVELOPMENT environment: always.
	 * - In every other environment: only when "production" is true
	 *   **and** the address is in "allowed_ips", a list of addresses and
	 *   networks ("203.0.113.7", "10.0.0.0/8", "2001:db8::/32").
	 *
	 * Pass [Request::$client_ip]. Behind a proxy or a load balancer it is
	 * only the address of the visitor when [Request::$trusted_proxies]
	 * names the proxy; otherwise it is the proxy itself, for everybody.
	 *
	 * @param   string|null  $ip  the address of the visitor
	 * @return  bool
	 */
	public static function allowed(?string $ip): bool
	{
		$config = Kohana::$config->load('ub_log_reader');
		$config = ($config instanceof Config_Group) ? $config->as_array() : [];

		if (($config['page'] ?? true) === false)
			return false;

		if (Kohana::$environment === Kohana::DEVELOPMENT)
			return true;

		// Both must be said: a list alone, or the switch alone, opens nothing
		if (($config['production'] ?? false) !== true)
			return false;

		$list = $config['allowed_ips'] ?? [];

		return (is_array($list) AND Request::ip_in_list($ip, array_values($list)));
	}

	/**
	 * Returns the days there are logs of, the newest first.
	 *
	 *     // ['2026-11-12', '2026-11-11', ...]
	 *     $days = $reader->days();
	 *
	 * @return  array<int, string>
	 */
	public function days(): array
	{
		return array_keys($this->_files());
	}

	/**
	 * Returns the entries that fit the filters, the newest first.
	 *
	 * Filter      | Keeps
	 * ------------|--------------------------------------------------------------
	 * level       | entries of that level and worse: "ERROR" also gives CRITICAL
	 * text        | entries with this text in the message, the file, the address or the trace
	 * reference   | the error with this reference code
	 * request     | everything that was logged in the request with this code (JSON logs)
	 * from, to    | entries of these days: "2026-11-12", "yesterday", "-1 week", or a timestamp
	 *
	 * An entry is an array with the keys time (a timestamp), level,
	 * message, file, line, reference, request, method, uri, exception and
	 * trace; what a log file does not tell is null.
	 *
	 * @param   array<string, mixed>  $filters  see the table
	 * @param   int                   $limit    how many entries at most, up to [UB_Log_Reader::MAX]
	 * @return  array<int, Entry>
	 */
	public function search(array $filters = [], int $limit = 100): array
	{
		$limit = max(1, min(static::MAX, $limit));
		$found = [];

		foreach ($this->_matching($filters, $limit) as $entry)
		{
			$found[] = $entry;

			if (count($found) >= $limit)
				break;
		}

		return $this->mask ? array_map([$this, '_masked'], $found) : $found;
	}

	/**
	 * Returns the messages that were logged most often, the most
	 * frequent first: "which errors does this site have?"
	 *
	 *     // The ten most frequent errors of the last week
	 *     $top = $reader->top(['level' => 'ERROR', 'from' => '-1 week']);
	 *
	 * Messages that differ only in their numbers and in the reference
	 * code are counted as one: "Order 17 not saved" and "Order 18 not
	 * saved" are the same problem.
	 *
	 * Every entry that fits is looked at, so this is the search an
	 * index helps most.
	 *
	 * @param   array<string, mixed>  $filters  as for [UB_Log_Reader::search]
	 * @param   int                   $limit    how many messages at most
	 * @return  array<int, array{count: int, level: string, message: string, first: int, last: int, reference: string|null}>
	 *          for each message: how often, its level, its newest wording, when it was
	 *          logged first and last, and the reference code of its newest error
	 */
	public function top(array $filters = [], int $limit = 10): array
	{
		$groups = [];

		foreach ($this->_matching($filters) as $entry)
		{
			$key = $entry['level'].' '.static::_signature($entry['message']);

			if (isset($groups[$key]))
			{
				// The newest come first: this one is older
				$groups[$key]['count']++;
				$groups[$key]['first'] = min($groups[$key]['first'], $entry['time']);
				$groups[$key]['last']  = max($groups[$key]['last'], $entry['time']);
			}
			else
			{
				$groups[$key] = [
					'count'     => 1,
					'level'     => $entry['level'],
					'message'   => $this->mask ? static::mask($entry['message']) : $entry['message'],
					'first'     => $entry['time'],
					'last'      => $entry['time'],
					'reference' => $entry['reference'],
				];
			}
		}

		// The most frequent first; of equally frequent ones, the newest
		usort($groups, fn (array $a, array $b): int => [$b['count'], $b['last']] <=> [$a['count'], $a['last']]);

		return array_slice($groups, 0, max(1, $limit));
	}

	/**
	 * Goes through the entries that fit some filters, the newest first:
	 * from the index when there is one, from the log files otherwise.
	 * Both give the same entries in the same order.
	 *
	 * @param   array<string, mixed>  $filters  the filters of search()
	 * @param   int|null              $most     how many entries the caller takes at most, when it is known:
	 *                                          no more than that are kept in memory of one file
	 * @return  Generator<int, Entry>
	 */
	protected function _matching(array $filters, ?int $most = null): Generator
	{
		$from = $this->_time($filters['from'] ?? null, false);
		$to   = $this->_time($filters['to'] ?? null, true);

		if ($this->_index !== null)
		{
			$this->_index->sync($this->_files(), $this->_entries(...));

			$level = is_string($filters['level'] ?? null) ? strtoupper($filters['level']) : '';
			$code  = fn (string $key): ?string => (is_string($filters[$key] ?? null) AND trim($filters[$key]) !== '') ? trim($filters[$key]) : null;
			$text  = (is_string($filters['text'] ?? null) AND $filters['text'] !== '') ? $filters['text'] : null;

			foreach ($this->_index->entries($from, $to, static::LEVELS[$level] ?? null, $code('reference'), $code('request'), $text) as $entry)
			{
				// The database keeps out most; this decides, as for the files
				if ($this->_fits($entry, $filters, $from, $to))
				{
					yield $entry;
				}
			}

			return;
		}

		foreach ($this->_files() as $day => $file)
		{
			// Whole days outside the dates are not opened at all
			if (($from !== null AND strtotime($day.' 23:59:59') < $from) OR ($to !== null AND strtotime($day) > $to))
				continue;

			$matches = [];

			foreach ($this->_entries($file) as $entry)
			{
				if ($this->_fits($entry, $filters, $from, $to))
				{
					$matches[] = $entry;

					if ($most !== null AND count($matches) > $most)
					{
						// More than is wanted: the oldest goes
						array_shift($matches);
					}
				}
			}

			// A file has the oldest first
			yield from array_reverse($matches);
		}
	}

	/**
	 * Returns what makes two messages "the same": the first line,
	 * without the reference code of the error and with every number
	 * replaced.
	 *
	 * @param   string  $message  a message of the log
	 * @return  string
	 */
	protected static function _signature(string $message): string
	{
		$line = (string) strtok($message, "\n");
		$line = (string) preg_replace('/^\[[0-9A-F]{8}\] /', '', $line);

		return mb_substr( (string) preg_replace('/\d+/', '0', $line), 0, 200);
	}

	/**
	 * Replaces what looks like a secret in a text by "***": the value
	 * after a name such as password, token, secret, key, authorization
	 * or cookie.
	 *
	 *     // "login failed: password=*** user=ana"
	 *     UB_Log_Reader::mask('login failed: password=hunter2 user=ana');
	 *
	 * @param   string  $text  text of a log
	 * @return  string
	 */
	public static function mask(string $text): string
	{
		// name, then "=", ":" or "=>" (with quotes and spaces around), then the value
		$masked = preg_replace(
			'/\b((?:[a-z0-9_-]*(?:passw(?:or)?d|passwd|secret|token|api[_-]?key|salt|signature)[a-z0-9_-]*|authorization|cookie|set-cookie)["\']?\s*(?:=>|[:=])\s*)(?:(?:Bearer|Basic)\s+)?("[^"]*"|\'[^\']*\'|[^\s"\'&,;)\]}]+)/i',
			'$1***',
			$text
		);

		return $masked ?? $text;
	}

	/**
	 * Returns the log file of each day, by day, the newest first. A
	 * day with a JSON file is read from that one.
	 *
	 * @return  array<string, string>
	 */
	protected function _files(): array
	{
		$files = [];

		// <directory>/<year>/<month>/<day>.php and <day>.json.php
		foreach (glob($this->_directory.'[0-9][0-9][0-9][0-9]'.DIRECTORY_SEPARATOR.'[0-9][0-9]'.DIRECTORY_SEPARATOR.'[0-9][0-9]*'.EXT) ?: [] as $file)
		{
			if (preg_match('#(\d{4})[/\\\\](\d\d)[/\\\\](\d\d)(\.json)?\.php$#', $file, $match))
			{
				$day = $match[1].'-'.$match[2].'-'.$match[3];

				if ( ! isset($files[$day]) OR isset($match[4]))
				{
					$files[$day] = $file;
				}
			}
		}

		krsort($files);

		return $files;
	}

	/**
	 * Goes through the entries of a log file, the oldest first, without
	 * holding the file in memory.
	 *
	 * @param   string  $file   path of the log file
	 * @param   int     $start  where to start reading: 0, or the place of an entry
	 * @return  Generator<int, Entry>  each entry under the place (in bytes) where it starts
	 */
	protected function _entries(string $file, int $start = 0): Generator
	{
		$handle = @fopen($file, 'rb');

		if ($handle === false)
			return;

		fseek($handle, $start);

		$json  = str_ends_with($file, '.json'.EXT);
		$entry = null;
		$place = $start;
		$begun = $start;

		while (true)
		{
			// Where the line that is read next starts
			$place = (int) ftell($handle);

			if (($line = fgets($handle)) === false)
				break;

			$line = rtrim($line, "\r\n");

			// The first line stops PHP: it is no entry
			if ($line === '' OR str_starts_with($line, '<?php'))
				continue;

			if ($json)
			{
				if (($decoded = $this->_json_entry($line)) !== null)
				{
					yield $place => $decoded;
				}

				continue;
			}

			$started = $this->_text_entry($line);

			if ($started === null)
			{
				// No start of an entry: more text of the entry before it
				if ($entry !== null)
				{
					$key = ($entry['trace'] === null) ? 'message' : 'trace';

					$entry[$key] .= "\n".$line;
				}
			}
			elseif ($entry !== null AND $started['level'] === 'DEBUG' AND str_starts_with($started['message'], '#0 ') AND $entry['trace'] === null)
			{
				// The trace of an error is logged as an entry of its own, right after it
				$entry['trace'] = $started['message'];
			}
			else
			{
				if ($entry !== null)
				{
					yield $begun => $this->_finished($entry);
				}

				$entry = $started;
				$begun = $place;
			}
		}

		fclose($handle);

		if ($entry !== null)
		{
			yield $begun => $this->_finished($entry);
		}
	}

	/**
	 * Finishes an entry of a text log once all its lines are read: the
	 * text log ends an entry with " in /path/file.php:42", which is
	 * where it was logged and no part of the message or the trace.
	 *
	 * @param   Entry  $entry  the entry as it was read
	 * @return  Entry
	 */
	protected function _finished(array $entry): array
	{
		foreach (['message', 'trace'] as $key)
		{
			if (is_string($entry[$key]) AND preg_match('/^(.*) in (\S[^\n]*?):(\d+)$/s', $entry[$key], $where))
			{
				$entry[$key] = $where[1];

				// The place of the message itself is the one that counts
				if ($key === 'message')
				{
					$entry['file'] = $where[2];
					$entry['line'] = (int) $where[3];
				}
			}
		}

		return $entry;
	}

	/**
	 * Reads the line that starts an entry of a text log:
	 * "2026-11-12 10:15:02 --- ERROR: message in /path/file.php:42".
	 *
	 * @param   string  $line  a line of the file
	 * @return  Entry|null  null when the line starts no entry
	 */
	protected function _text_entry(string $line): ?array
	{
		if ( ! preg_match('/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d) --- ([A-Z]+): (.*)$/s', $line, $match) OR ! isset(static::LEVELS[$match[2]]))
			return null;

		return [
			'time'      => (int) strtotime($match[1]),
			'level'     => $match[2],
			'message'   => $match[3],
			'file'      => null,
			'line'      => null,
			'reference' => preg_match('/^\[([0-9A-F]{8})\] /', $match[3], $code) ? $code[1] : null,
			'request'   => null,
			'method'    => null,
			'uri'       => null,
			'exception' => null,
			'trace'     => null,
		];
	}

	/**
	 * Reads a line of a JSON log.
	 *
	 * @param   string  $line  a line of the file
	 * @return  Entry|null  null when the line is no entry
	 */
	protected function _json_entry(string $line): ?array
	{
		$data = json_decode($line, true);

		if ( ! is_array($data) OR ! is_string($data['level'] ?? null) OR ! isset(static::LEVELS[$data['level']]))
			return null;

		$text = fn (string $key): ?string => is_scalar($data[$key] ?? null) ? (string) $data[$key] : null;

		return [
			'time'      => (int) strtotime((string) $text('time')),
			'level'     => $data['level'],
			'message'   => (string) $text('message'),
			'file'      => $text('file'),
			'line'      => is_int($data['line'] ?? null) ? $data['line'] : null,
			'reference' => $text('reference'),
			'request'   => $text('request'),
			'method'    => $text('method'),
			'uri'       => $text('uri'),
			'exception' => $text('exception'),
			'trace'     => $text('trace'),
		];
	}

	/**
	 * Tells whether an entry fits the filters.
	 *
	 * @param   Entry                 $entry    the entry
	 * @param   array<string, mixed>  $filters  the filters of search()
	 * @param   int|null              $from     the earliest time, when there is one
	 * @param   int|null              $to       the latest time, when there is one
	 * @return  bool
	 */
	protected function _fits(array $entry, array $filters, ?int $from, ?int $to): bool
	{
		if (($from !== null AND $entry['time'] < $from) OR ($to !== null AND $entry['time'] > $to))
			return false;

		$level = is_string($filters['level'] ?? null) ? strtoupper($filters['level']) : '';

		// A level that does not exist keeps nothing out
		if (isset(static::LEVELS[$level]) AND static::LEVELS[$entry['level']] > static::LEVELS[$level])
			return false;

		foreach (['reference', 'request'] as $code)
		{
			if (is_string($filters[$code] ?? null) AND $filters[$code] !== '' AND strcasecmp((string) $entry[$code], trim($filters[$code])) !== 0)
				return false;
		}

		$text = is_string($filters['text'] ?? null) ? $filters['text'] : '';

		if ($text !== '')
		{
			$all = implode("\n", array_filter([$entry['message'], $entry['file'], $entry['uri'], $entry['exception'], $entry['trace']], 'is_string'));

			if (mb_stripos($all, $text) === false)
				return false;
		}

		return true;
	}

	/**
	 * Returns a time of a filter as a timestamp.
	 *
	 * @param   mixed  $value  a timestamp, or a date or time as text
	 * @param   bool   $end    for a day without a time: its last second, not its first
	 * @return  int|null  null when there is no such filter, or it cannot be read
	 */
	protected function _time(mixed $value, bool $end): ?int
	{
		if (is_int($value))
			return $value;

		if ( ! is_string($value) OR trim($value) === '')
			return null;

		$time = strtotime($value);

		if ($time === false)
			return null;

		// "2026-11-12" as the end of a search means the whole day
		return ($end AND preg_match('/^\d{4}-\d\d-\d\d$/', trim($value))) ? $time + 86399 : $time;
	}

	/**
	 * Returns an entry with what looks like a secret replaced.
	 *
	 * @param   Entry  $entry  the entry
	 * @return  Entry
	 */
	protected function _masked(array $entry): array
	{
		foreach (['message', 'uri', 'trace'] as $key)
		{
			if (is_string($entry[$key]))
			{
				$entry[$key] = static::mask($entry[$key]);
			}
		}

		return $entry;
	}

}
