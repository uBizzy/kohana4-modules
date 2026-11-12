<?php
/**
 * An index of the log files in a SQLite database, for [UB_Log_Reader]:
 * a search asks the database and does not read every file again.
 *
 * The log files stay the truth. The index is a copy of their entries
 * that can be deleted at any time: it is made again from the files.
 * Each search first adds what was written to the logs since the last
 * one, so there is nothing to run by hand or by cron.
 *
 * [!!] The index holds what the logs hold. Its file is kept beside
 * them, and must be as unreachable from the web as they are.
 *
 * @phpstan-import-type Entry from UB_Log_Reader
 *
 * @package    UB_Log_Reader
 * @category   Base
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Log_Index {

	/**
	 * @var  string  Name of the index file, in the directory of the logs
	 */
	public const string FILE = 'ub_log_reader.sqlite';

	/**
	 * @var  int  Changes when the tables change: an index of another version is made again
	 */
	public const int VERSION = 2;

	/**
	 * @var  string[]  The columns of an entry, as [UB_Log_Reader] names them
	 */
	protected const array COLUMNS = ['time', 'level', 'message', 'file', 'line', 'reference', 'request', 'method', 'uri', 'exception', 'trace'];

	/**
	 * @var  PDO  The database
	 */
	protected PDO $_db;

	/**
	 * @var  string  Path of the index file
	 */
	protected string $_path;

	/**
	 * Tells whether an index can be kept here: PHP must have SQLite.
	 *
	 * @return  bool
	 */
	public static function available(): bool
	{
		return (extension_loaded('pdo_sqlite') AND in_array('sqlite', PDO::getAvailableDrivers(), true));
	}

	/**
	 * Opens the index of a directory of logs, making it when it is not there.
	 *
	 * @param   string  $directory  the directory of the log files, with a separator at its end
	 * @throws  Kohana_Exception  when PHP has no SQLite, or the file cannot be made
	 */
	public function __construct(string $directory)
	{
		if ( ! static::available())
			throw new Kohana_Exception('The index of the logs needs the pdo_sqlite extension of PHP');

		$this->_path = $directory.static::FILE;

		try
		{
			$this->_db = new PDO('sqlite:'.$this->_path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

			// Two requests can look at the same moment: the second waits
			$this->_db->exec('PRAGMA busy_timeout = 5000');

			$this->_tables();
		}
		catch (PDOException $e)
		{
			throw new Kohana_Exception('The index of the logs cannot be opened at :path: :error', [
				':path'  => Debug::path($this->_path),
				':error' => $e->getMessage(),
			], 0, $e);
		}

		// Only the server reads it: it holds what the logs hold
		@chmod($this->_path, 0600);
	}

	/**
	 * Returns the path of the index file.
	 *
	 * @return  string
	 */
	public function path(): string
	{
		return $this->_path;
	}

	/**
	 * Brings the index up to date with the log files: adds what was
	 * written since the last time, and forgets files that are gone.
	 *
	 * @param   array<string, string>                        $files    day ("2026-11-12") => path of its log file
	 * @param   callable(string, int): iterable<int, Entry>  $entries  gives the entries of a file from a place on,
	 *                                                                 each under the place where it starts
	 * @return  int  how many entries were added
	 */
	public function sync(array $files, callable $entries): int
	{
		$added = 0;

		// One writer at a time, and all or nothing
		$this->_db->exec('BEGIN IMMEDIATE');

		try
		{
			$known = [];

			$query = $this->_run('SELECT file, day, size, last FROM files', []);

			while (is_array($row = $query->fetch(PDO::FETCH_NUM)))
			{
				list($file, $day, $size, $last) = array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $row);

				$known[$file] = ['day' => $day, 'size' => (int) $size, 'last' => (int) $last];
			}

			foreach (array_diff_key($known, array_flip($files)) as $file => $state)
			{
				// Deleted, or a day that now has a JSON file in its place
				$this->_forget($state['day'], 0);
				$this->_run('DELETE FROM files WHERE file = ?', [$file]);
			}

			foreach ($files as $day => $file)
			{
				clearstatcache(true, $file);

				$size  = (int) @filesize($file);
				$state = $known[$file] ?? null;

				if ($state !== null AND $state['size'] === $size)
					continue;

				// A file only grows. One that became smaller is another
				// file under the same name: it is read from its start.
				// Otherwise from the last entry on, which may have got
				// more lines (its trace) since it was read.
				$start = ($state !== null AND $size > $state['size']) ? $state['last'] : 0;
				$last  = $start;

				$this->_forget($day, $start);

				foreach ($entries($file, $start) as $offset => $entry)
				{
					$this->_add($day, $offset, $entry);

					$last = $offset;
					$added++;
				}

				$this->_run('INSERT OR REPLACE INTO files (file, day, size, last) VALUES (?, ?, ?, ?)', [$file, $day, $size, $last]);
			}

			$this->_db->exec('COMMIT');
		}
		catch (Throwable $e)
		{
			$this->_db->exec('ROLLBACK');

			throw $e;
		}

		return $added;
	}

	/**
	 * Empties the index: the next [UB_Log_Index::sync] reads every file again.
	 *
	 * @return  void
	 */
	public function clear(): void
	{
		$this->_db->exec('DELETE FROM entries');
		$this->_db->exec('DELETE FROM files');
		$this->_db->exec('VACUUM');
	}

	/**
	 * Returns how many entries the index holds.
	 *
	 * @return  int
	 */
	public function count(): int
	{
		return $this->_number('SELECT COUNT(*) FROM entries');
	}

	/**
	 * Goes through the entries that can fit a search, the newest first.
	 *
	 * The database keeps out what it can tell exactly (dates, level,
	 * codes) and what cannot hold the text. Whether an entry really fits
	 * is still decided by the reader, the same way as without an index.
	 *
	 * @param   int|null     $from       the earliest time
	 * @param   int|null     $to         the latest time
	 * @param   int|null     $level      the least bad level that is wanted, as a number of [UB_Log_Reader::LEVELS]
	 * @param   string|null  $reference  the reference code of an error
	 * @param   string|null  $request    the code of a request
	 * @param   string|null  $text       text that an entry must hold
	 * @return  Generator<int, Entry>
	 */
	public function entries(?int $from, ?int $to, ?int $level, ?string $reference, ?string $request, ?string $text): Generator
	{
		$where  = [];
		$values = [];

		// Whole days outside the dates are not looked at: the days are how the table is sorted
		$days = [
			['day >= ?', ($from === null) ? null : date('Y-m-d', $from)],
			['day <= ?', ($to === null) ? null : date('Y-m-d', $to)],
		];

		foreach ([...$days, ['time >= ?', $from], ['time <= ?', $to], ['severity <= ?', $level], ['reference = ? COLLATE NOCASE', $reference], ['request = ? COLLATE NOCASE', $request]] as list($condition, $value))
		{
			if ($value !== null)
			{
				$where[]  = $condition;
				$values[] = $value;
			}
		}

		// LIKE knows the large and small letters of ASCII only: other
		// texts are left to the reader
		if ($text !== null AND preg_match('/^[\x20-\x7e]+$/D', $text))
		{
			$like = '%'.addcslashes($text, '\\%_').'%';

			$parts = [];

			foreach (['message', 'file', 'uri', 'exception', 'trace'] as $column)
			{
				$parts[]  = $column." LIKE ? ESCAPE '\\'";
				$values[] = $like;
			}

			$where[] = '('.implode(' OR ', $parts).')';
		}

		$query = $this->_run(
			'SELECT '.implode(', ', static::COLUMNS).' FROM entries'
			.(($where === []) ? '' : ' WHERE '.implode(' AND ', $where))
			.' ORDER BY day DESC, place DESC',
			$values
		);

		try
		{
			while (is_array($row = $query->fetch(PDO::FETCH_ASSOC)))
			{
				yield $this->_entry($row);
			}
		}
		finally
		{
			// The caller may stop early: a query left open would keep
			// the tables from being changed
			$query->closeCursor();
		}
	}

	/**
	 * Makes the tables, or makes them again when they are of another version.
	 *
	 * @return  void
	 */
	protected function _tables(): void
	{
		if ($this->_number('PRAGMA user_version') === static::VERSION)
			return;

		$this->_db->exec('DROP TABLE IF EXISTS entries');
		$this->_db->exec('DROP TABLE IF EXISTS files');

		// What was read of each file: its day, its size then, and where its last entry starts
		$this->_db->exec('CREATE TABLE files (file TEXT PRIMARY KEY, day TEXT NOT NULL, size INTEGER NOT NULL, last INTEGER NOT NULL)');

		// A day has one log file. "place" is where the entry starts in
		// it: day and place are the order of the entries, and how the
		// table is stored. "severity" is the level as a number, the worst is 0.
		$this->_db->exec('CREATE TABLE entries ('
			.'day TEXT NOT NULL, place INTEGER NOT NULL, severity INTEGER NOT NULL, '
			.'time INTEGER NOT NULL, level TEXT NOT NULL, message TEXT NOT NULL, file TEXT, line INTEGER, '
			.'reference TEXT, request TEXT, method TEXT, uri TEXT, exception TEXT, trace TEXT, '
			.'PRIMARY KEY (day, place)) WITHOUT ROWID');

		// Most entries have neither code: only those that have one are listed
		$this->_db->exec('CREATE INDEX entries_reference ON entries (reference COLLATE NOCASE) WHERE reference IS NOT NULL');
		$this->_db->exec('CREATE INDEX entries_request ON entries (request COLLATE NOCASE) WHERE request IS NOT NULL');

		$this->_db->exec('PRAGMA user_version = '.static::VERSION);
	}

	/**
	 * Removes the entries of a day from a place on.
	 *
	 * @param   string  $day    the day of the log file
	 * @param   int     $place  where to start
	 * @return  void
	 */
	protected function _forget(string $day, int $place): void
	{
		$this->_run('DELETE FROM entries WHERE day = ? AND place >= ?', [$day, $place]);
	}

	/**
	 * Adds an entry.
	 *
	 * @param   string  $day    the day of its log file
	 * @param   int     $place  where it starts in the file
	 * @param   Entry   $entry  the entry
	 * @return  void
	 */
	protected function _add(string $day, int $place, array $entry): void
	{
		$values = [$day, $place, UB_Log_Reader::LEVELS[$entry['level']] ?? 7];

		foreach (static::COLUMNS as $column)
		{
			$values[] = $entry[$column];
		}

		$this->_run(
			'INSERT OR REPLACE INTO entries (day, place, severity, '.implode(', ', static::COLUMNS).') VALUES (?'.str_repeat(', ?', count($values) - 1).')',
			$values
		);
	}

	/**
	 * Turns a row of the database into an entry.
	 *
	 * @param   array<int|string, mixed>  $row  a row of the table "entries"
	 * @return  Entry
	 */
	protected function _entry(array $row): array
	{
		$text = fn (string $key): ?string => is_scalar($row[$key] ?? null) ? (string) $row[$key] : null;

		return [
			'time'      => (int) $text('time'),
			'level'     => (string) $text('level'),
			'message'   => (string) $text('message'),
			'file'      => $text('file'),
			'line'      => isset($row['line']) ? (int) $text('line') : null,
			'reference' => $text('reference'),
			'request'   => $text('request'),
			'method'    => $text('method'),
			'uri'       => $text('uri'),
			'exception' => $text('exception'),
			'trace'     => $text('trace'),
		];
	}

	/**
	 * Returns the one number a statement gives.
	 *
	 * @param   string  $sql  the statement
	 * @return  int
	 */
	protected function _number(string $sql): int
	{
		$query  = $this->_run($sql, []);
		$number = (int) $query->fetchColumn();

		$query->closeCursor();

		return $number;
	}

	/**
	 * Runs a statement with its values sent apart from the SQL.
	 *
	 * @param   string                        $sql     the statement, with "?" for each value
	 * @param   array<int, int|string|null>   $values  the values
	 * @return  PDOStatement
	 */
	protected function _run(string $sql, array $values): PDOStatement
	{
		$statement = $this->_db->prepare($sql);
		$statement->execute($values);

		return $statement;
	}

}
