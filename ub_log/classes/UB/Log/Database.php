<?php
/**
 * Writes the messages of the framework log to the table ub_logs.
 *
 *     // application/bootstrap.php: everything
 *     Kohana::$log->attach(new UB_Log_Database);
 *
 *     // ...or warnings and worse only
 *     Kohana::$log->attach(new UB_Log_Database, Log::WARNING);
 *
 * The framework hands the messages over when the request ends, so
 * logging costs the visitor one INSERT, after the page was made.
 *
 * Each row has the time, the level, the type and the message, the
 * context as JSON, the code of the request (the same for all rows of
 * one request, and the same as in the JSON log files), the error code
 * that the error page showed, who was logged in, the address of the
 * visitor, the method and the address of the request, and the file
 * and line that logged.
 *
 * Who was logged in is what the application says. Tell the writer once,
 * in the bootstrap, how to find out:
 *
 *     UB_Log_Database::$user = fn (): ?int => My_Login::user_id();
 *
 * [!!] A log must never break the page. When the table cannot be
 * written (the database is down: often the very error being logged)
 * the reason goes to the error log of PHP and the request goes on.
 * Keep a file writer attached as well.
 *
 * @package    UB_Log
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Log_Database extends Log_Writer {

	/**
	 * @var  array<int, string>  The columns of a row, in the order they are written
	 */
	public const array COLUMNS = ['time', 'level', 'type', 'message', 'context', 'request', 'reference', 'user_id', 'ip', 'method', 'uri', 'file', 'line'];

	/**
	 * @var  (Closure(): ?int)|null  Returns the number of who is logged in, for the column user_id; null writes none
	 */
	public static ?Closure $user = null;

	/**
	 * @var  bool  Is an alert being sent? What that logs is not an alert again
	 */
	protected static bool $_alerting = false;

	/**
	 * Writes messages to the table, in one statement, and sends the
	 * alerts of the config.
	 *
	 * @param   array<int, array<string, mixed>>  $messages  messages of [Log::add]
	 * @return  void
	 */
	public function write(array $messages): void
	{
		if ($messages === [])
			return;

		try
		{
			$rows   = array_map($this->_row(...), array_values($messages));
			$insert = DB::insert(UB_Log::TABLE, static::COLUMNS);

			foreach ($rows as $row)
			{
				$insert->values(array_values($row));
			}

			$insert->execute(UB_Log::db());

			$this->_alert($rows);
		}
		catch (Throwable $e)
		{
			error_log('ub_log: the log was not written to the table: '.$e->getMessage());
		}
	}

	/**
	 * Returns the row of a message.
	 *
	 * @param   array<string, mixed>  $message  a message of [Log::add]
	 * @return  array<string, mixed>  column => value, in the order of [UB_Log_Database::COLUMNS]
	 */
	protected function _row(array $message): array
	{
		/** @var array<string, mixed> $additional */
		$additional = is_array($message['additional'] ?? null) ? $message['additional'] : [];

		$body      = is_scalar($message['body'] ?? null) ? (string) $message['body'] : '';
		$type      = $additional['type'] ?? null;
		$context   = is_array($additional['context'] ?? null) ? $additional['context'] : [];
		$exception = $additional['exception'] ?? null;
		$reference = null;

		if ($exception instanceof Throwable)
		{
			$reference = Kohana_Exception::reference($exception);

			$context['exception'] = get_class($exception);
			$context['trace']     = $exception->getTraceAsString();
		}
		elseif (preg_match('/^\[([0-9A-F]{8})\] /', $body, $match))
		{
			// The reference code that Kohana_Exception::log() puts first
			$reference = $match[1];
		}

		$request = Request::$initial;

		return [
			'time'      => is_numeric($message['time'] ?? null) ? (int) $message['time'] : time(),
			'level'     => is_int($message['level'] ?? null) ? $message['level'] : LOG_INFO,
			'type'      => (is_string($type) AND preg_match('/^[a-zA-Z0-9._-]{1,64}$/D', $type) === 1) ? $type : null,
			'message'   => mb_strcut($body, 0, 60000, 'UTF-8'),
			'context'   => $this->_json($context),
			'request'   => Log_JSON::request_id(),
			'reference' => $reference,
			'user_id'   => $this->_user($additional),
			'ip'        => Request::$client_ip,
			'method'    => ($request instanceof Request) ? substr($request->method(), 0, 10) : ((PHP_SAPI === 'cli') ? 'CLI' : ''),
			'uri'       => ($request instanceof Request) ? mb_strcut($request->uri(), 0, 255, 'UTF-8') : '',
			'file'      => is_string($message['file'] ?? null) ? mb_strcut(Debug::path($message['file']), 0, 255, 'UTF-8') : null,
			'line'      => is_int($message['line'] ?? null) ? $message['line'] : null,
		];
	}

	/**
	 * Returns a context as the JSON that is stored: without secrets,
	 * and not longer than [UB_Log::CONTEXT_MAX].
	 *
	 * @param   array<int|string, mixed>  $context  values to store
	 * @return  string|null  null for an empty context
	 */
	protected function _json(array $context): ?string
	{
		if ($context === [])
			return null;

		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
		$json  = (string) json_encode(UB_Log::mask($context), $flags);

		if (strlen($json) > UB_Log::CONTEXT_MAX)
		{
			// Cut JSON is no JSON: say what happened instead
			$json = (string) json_encode(['cut' => 'the context was longer than '.UB_Log::CONTEXT_MAX.' bytes', 'start' => mb_strcut($json, 0, 2000, 'UTF-8')], $flags);
		}

		return $json;
	}

	/**
	 * Returns who the message is about: the "user" that was logged
	 * with it, or what [UB_Log_Database::$user] returns.
	 *
	 * @param   array<string, mixed>  $additional  what was logged with the message
	 * @return  int|null
	 */
	protected function _user(array $additional): ?int
	{
		if (is_int($additional['user'] ?? null))
			return $additional['user'];

		if (static::$user === null)
			return null;

		try
		{
			return (static::$user)();
		}
		catch (Throwable $e)
		{
			// Who it was is not worth losing the message for
			return null;
		}
	}

	/**
	 * Sends one email with the rows that the config wants to hear
	 * about, when the limit allows another email.
	 *
	 * @param   array<int, array<string, mixed>>  $rows  the rows that were written
	 * @return  void
	 */
	protected function _alert(array $rows): void
	{
		$alert = (array) Kohana::$config->load('ub_log.alert');
		$to    = [];
		$level = is_int($alert['level'] ?? null) ? $alert['level'] : LOG_ERR;
		$types = (array) ($alert['types'] ?? []);
		$limit = (array) ($alert['limit'] ?? []);

		foreach ((array) ($alert['to'] ?? []) as $address)
		{
			if (is_string($address) AND Valid::email($address))
			{
				$to[] = $address;
			}
		}

		if ($to === [] OR static::$_alerting)
			return;

		$lines = [];

		foreach ($rows as $row)
		{
			$type = is_string($row['type']) ? $row['type'] : '';

			// A message about email that fails would ask for an email
			if (str_starts_with($type, 'email.'))
				continue;

			if ($row['level'] <= $level OR in_array($type, $types, true))
			{
				$lines[] = ($this->_log_levels[is_int($row['level']) ? $row['level'] : LOG_INFO] ?? 'INFO').(($type === '') ? '' : ' '.$type).': '
					.mb_strimwidth(is_string($row['message']) ? $row['message'] : '', 0, 500, '...', 'UTF-8')
					."\n".'  request '.(is_string($row['request']) ? $row['request'] : '')
					.(is_string($row['reference']) ? ', error code '.$row['reference'] : '')
					.', '.trim((is_string($row['method']) ? $row['method'] : '').' '.(is_string($row['uri']) ? $row['uri'] : ''));
			}
		}

		$max     = is_numeric($limit[0] ?? null) ? (int) $limit[0] : 10;
		$seconds = is_numeric($limit[1] ?? null) ? (int) $limit[1] : 3600;

		if ($lines === [] OR ! Throttle::allow('ub_log:alert', $max, $seconds))
			return;

		static::$_alerting = true;

		try
		{
			// One line: a subject with line breaks could add headers
			$subject = trim((string) preg_replace('/\s+/', ' ', is_string($alert['subject'] ?? null) ? $alert['subject'] : 'Log alert'));

			$this->_send($to, $subject, implode("\n\n", $lines)."\n");
		}
		finally
		{
			static::$_alerting = false;
		}
	}

	/**
	 * Sends an alert: through the ub_email module when it is enabled,
	 * otherwise with the mail() function of PHP.
	 *
	 * @param   array<int, string>  $to       addresses, already checked
	 * @param   string              $subject  the subject, one line
	 * @param   string              $text     the message
	 * @return  bool
	 */
	protected function _send(array $to, string $subject, string $text): bool
	{
		if (class_exists('UB_Email'))
			return UB_Email::factory()->to($to)->subject($subject)->text($text)->send();

		return mail(implode(', ', $to), mb_encode_mimeheader($subject, 'UTF-8'), $text, ['MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8']);
	}

}
