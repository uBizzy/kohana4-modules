<?php
/**
 * The log in a table of the database, to search it with SQL.
 *
 * The table is written by a writer of the framework log: attach it in
 * the bootstrap, and everything that is logged goes there too.
 *
 *     Kohana::$log->attach(new UB_Log_Database);
 *
 * A message has one of the eight levels of the framework, and can
 * have a type: a name of your own, like "upload.failed", that says
 * what kind of event it is. Levels tell how bad, types tell what.
 *
 *     UB_Log::add('upload.failed', 'The file was too large', ['size' => $size], Log::WARNING);
 *
 *     $rows = UB_Log::find(['type' => 'upload.*', 'from' => strtotime('-1 day')]);
 *
 * [!!] Nothing of the request is stored by itself: no form fields, no
 * cookies, no headers. What you pass as context is stored, and values
 * under names like "password" or "token" are replaced by "***". Still:
 * do not log what must stay secret.
 *
 * @package    UB_Log
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Log {

	/**
	 * @var  string  The table of the log
	 */
	public const string TABLE = 'ub_logs';

	/**
	 * @var  string  Names of context values that are never stored
	 */
	public const string SECRET = '/pass|pwd|secret|token|csrf|auth|cookie|session|api_?key|private|card|cvv|iban/i';

	/**
	 * @var  int  The longest context, in bytes of JSON
	 */
	public const int CONTEXT_MAX = 16000;

	/**
	 * Logs a message with a type. It goes to every writer of the
	 * framework log: the files as well as the table.
	 *
	 * @param   string                $type     what kind of event: letters, digits, ".", "_" and "-", up to 64
	 * @param   string                $message  what happened
	 * @param   array<string, mixed>  $context  values that help to understand it
	 * @param   int                   $level    one of the levels of [Log]
	 * @return  void
	 */
	public static function add(string $type, string $message, array $context = [], int $level = Log::INFO): void
	{
		Kohana::$log?->add($level, $message, null, ['type' => $type, 'context' => $context]);
	}

	/**
	 * Returns messages of the table, newest first.
	 *
	 * Filter | What it keeps
	 * --- | ---
	 * level | this level and worse: Log::WARNING keeps warnings, errors, ...
	 * type | this type; with "*" at the end, the types that start so: "upload.*"
	 * request | the messages of one request, by its code
	 * reference | the error with this code (what the error page showed)
	 * user | the messages of one account, by its number
	 * from, to | between these times (seconds since 1970)
	 * text | the messages that hold this text
	 *
	 * @param   array<string, mixed>  $filters  see above; all are optional
	 * @param   int                   $limit    how many at most
	 * @return  array<int, array<string, mixed>>  rows of the table, with "context" as an array
	 */
	public static function find(array $filters = [], int $limit = 100): array
	{
		$db    = static::db();
		$query = DB::select()->from(static::TABLE)->order_by('id', 'DESC')->limit(max(1, $limit));

		if (is_int($filters['level'] ?? null))
		{
			// Worse is lower: 0 is an emergency, 7 is debug
			$query->where('level', '<=', $filters['level']);
		}

		$type = $filters['type'] ?? null;

		if (is_string($type) AND str_ends_with($type, '*'))
		{
			$query->where('type', 'LIKE', static::_like($db, substr($type, 0, -1), '', '%'));
		}
		elseif (is_string($type))
		{
			$query->where('type', '=', $type);
		}

		foreach (['request' => 'request', 'reference' => 'reference', 'user' => 'user_id'] as $filter => $column)
		{
			if (is_scalar($filters[$filter] ?? null))
			{
				$query->where($column, '=', $filters[$filter]);
			}
		}

		if (is_int($filters['from'] ?? null))
		{
			$query->where('time', '>=', $filters['from']);
		}

		if (is_int($filters['to'] ?? null))
		{
			$query->where('time', '<=', $filters['to']);
		}

		if (is_string($filters['text'] ?? null) AND $filters['text'] !== '')
		{
			$query->where('message', 'LIKE', static::_like($db, $filters['text'], '%', '%'));
		}

		/** @var array<int, array<string, mixed>> $rows */
		$rows = $query->execute($db)->as_array();

		foreach ($rows as & $row)
		{
			$context = is_string($row['context']) ? json_decode($row['context'], true) : null;

			$row['context'] = is_array($context) ? $context : [];
		}

		return $rows;
	}

	/**
	 * Removes the messages that are older than "keep" of the config
	 * allows. The task "./minion ublog:clean" calls it.
	 *
	 * @return  int  how many rows were removed
	 */
	public static function clean(): int
	{
		$keep   = (array) Kohana::$config->load('ub_log.keep');
		$days   = is_numeric($keep['days'] ?? null) ? (int) $keep['days'] : 90;
		$errors = is_numeric($keep['error_days'] ?? null) ? (int) $keep['error_days'] : 365;
		$db     = static::db();

		// Warnings and lighter
		$removed = DB::delete(static::TABLE)
			->where('level', '>', LOG_ERR)
			->where('time', '<', time() - $days * 86400)
			->execute($db);

		// Everything, errors too
		$removed += DB::delete(static::TABLE)
			->where('time', '<', time() - $errors * 86400)
			->execute($db);

		return (int) $removed;
	}

	/**
	 * Returns a context without what must not be stored: the value of
	 * every name that looks like a secret ([UB_Log::SECRET]) is "***",
	 * at any depth. Objects become their class name.
	 *
	 * @param   array<int|string, mixed>  $context  values to store
	 * @param   int                       $depth    how deep this call is
	 * @return  array<int|string, mixed>
	 */
	public static function mask(array $context, int $depth = 0): array
	{
		$clean = [];

		foreach ($context as $name => $value)
		{
			if (is_string($name) AND preg_match(static::SECRET, $name) === 1)
			{
				$clean[$name] = '***';
			}
			elseif (is_array($value))
			{
				$clean[$name] = ($depth < 5) ? static::mask($value, $depth + 1) : '...';
			}
			elseif (is_object($value))
			{
				$clean[$name] = get_class($value);
			}
			elseif (is_string($value))
			{
				$clean[$name] = mb_strimwidth($value, 0, 2000, '...', 'UTF-8');
			}
			elseif (is_scalar($value) OR $value === null)
			{
				$clean[$name] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Returns the database of the log.
	 *
	 * @return  Database
	 */
	public static function db(): Database
	{
		$group = Kohana::$config->load('ub_log.database');

		return Database::instance(is_string($group) ? $group : null);
	}

	/**
	 * Returns what a LIKE compares with, to find a text as it is: its
	 * "%" and "_" are those characters, not wildcards. The escape
	 * character is named, because the databases do not agree on one.
	 *
	 * @param   Database  $db      the database of the query
	 * @param   string    $text    what to look for
	 * @param   string    $before  "%" when anything may come before it
	 * @param   string    $after   "%" when anything may come after it
	 * @return  Database_Expression
	 */
	protected static function _like(Database $db, string $text, string $before, string $after): Database_Expression
	{
		$text = strtr($text, ['!' => '!!', '%' => '!%', '_' => '!_']);

		return DB::expr($db->quote($before.$text.$after)." ESCAPE '!'");
	}

}
