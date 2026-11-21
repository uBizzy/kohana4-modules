<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the log in the database, with the table of the migration that
 * ships with the module.
 *
 * Runs on SQLite in memory, so it needs no server. LogMysqlTest and
 * LogPgsqlTest run the same tests on those servers.
 *
 * @package    UB_Log
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.log')]
class UB_LogTest extends Unittest_TestCase
{
	/**
	 * @var string  The database group of these tests
	 */
	protected const string GROUP = 'ub_log_test';

	/**
	 * @var array<string, mixed>  The config of the module during a test
	 */
	protected array $environmentDefault = [
		'ub_log.database' => self::GROUP,
		'ub_log.keep'     => ['days' => 90, 'error_days' => 365],
		'ub_log.alert'    => ['to' => [], 'level' => LOG_ERR, 'types' => [], 'limit' => [10, 3600], 'subject' => 'Log alert'],
	];

	/**
	 * @var Database  The database of the log
	 */
	protected Database $db;

	/**
	 * @var Log|null  The log of the application, put back after the test
	 */
	protected ?Log $log = null;

	/**
	 * @var Closure|null  What told the writer who is logged in before the test
	 */
	protected ?Closure $user = null;

	/**
	 * @var UB_LogTest_Writer  The writer of the test
	 */
	protected UB_LogTest_Writer $writer;

	/**
	 * Returns the settings of the test database, or null when it is not
	 * available. Other test classes extend this one for another database.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function config(): ?array
	{
		if ( ! extension_loaded('pdo_sqlite'))
			return null;

		return [
			'type'         => 'PDO',
			'connection'   => ['dsn' => 'sqlite::memory:'],
			'identifier'   => '"',
			'table_prefix' => '',
		];
	}

	/**
	 * Makes an empty table and a log of its own, with the writer.
	 */
	public function setUp(): void
	{
		parent::setUp();

		$config = $this->config();

		if ($config === null)
		{
			$this->markTestSkipped('The database for '.static::class.' is not configured or its extension is missing.');
		}

		$this->disconnect();

		$this->db = Database::instance(static::GROUP, $config);
		$this->db->query(null, 'DROP TABLE IF EXISTS '.$this->db->quote_table('ub_logs'));

		Kohana::load(dirname(__DIR__).'/ub_log/create_ub_logs_table.php')->up($this->db);

		$this->log    = Kohana::$log;
		$this->writer = new UB_LogTest_Writer;

		Kohana::$log = new Log;
		Kohana::$log->attach($this->writer);

		UB_LogTest_Writer::$sent = [];

		// Another module may have told the writer who is logged in
		$this->user = UB_Log_Database::$user;

		UB_Log_Database::$user = null;

		// The limit of the alerts is counted in the cache, which outlives a test
		Throttle::reset('ub_log:alert', 3600);
	}

	/**
	 * Removes the table and the connection, and puts the log back.
	 */
	public function tearDown(): void
	{
		if (isset($this->db))
		{
			$this->db->query(null, 'DROP TABLE IF EXISTS '.$this->db->quote_table('ub_logs'));

			Kohana::$log = $this->log;

			UB_Log_Database::$user = $this->user;
		}

		$this->disconnect();

		parent::tearDown();
	}

	/**
	 * Closes the connection of the tests: the test classes share its name.
	 */
	protected function disconnect(): void
	{
		if (isset(Database::$instances[static::GROUP]))
		{
			Database::$instances[static::GROUP]->disconnect();
			unset(Database::$instances[static::GROUP]);
		}
	}

	/**
	 * Hands what was logged to the writer, as the end of a request
	 * does, and returns the rows of the table, oldest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function written(): array
	{
		Kohana::$log->write();

		return array_reverse(UB_Log::find([], 1000));
	}

	public function test_a_message_becomes_a_row(): void
	{
		Kohana::$log->add(Log::WARNING, 'The disk is :percent full', [':percent' => '91%']);

		$rows = $this->written();

		$this->assertCount(1, $rows);

		$row = $rows[0];

		$this->assertEqualsWithDelta(time(), (int) $row['time'], 5);
		$this->assertEquals(LOG_WARNING, $row['level']);
		$this->assertNull($row['type']);
		$this->assertSame('The disk is 91% full', $row['message']);
		$this->assertSame([], $row['context']);
		$this->assertSame(Log_JSON::request_id(), $row['request']);
		$this->assertNull($row['reference']);
		$this->assertNull($row['user_id']);
		$this->assertSame(Request::$client_ip, $row['ip']);
		$this->assertStringEndsWith('LogTest.php', $row['file']);
		$this->assertGreaterThan(0, (int) $row['line']);
	}

	public function test_a_message_has_a_type_and_a_context(): void
	{
		UB_Log::add('upload.failed', 'The file was too large', ['size' => 9000000, 'name' => 'holiday.mov', 'tags' => ['a', 'b']], Log::WARNING);
		UB_Log::add('upload.done', 'Stored');

		$rows = $this->written();

		$this->assertSame(['upload.failed', 'upload.done'], array_column($rows, 'type'));
		$this->assertEquals([LOG_WARNING, LOG_INFO], array_column($rows, 'level'));
		$this->assertSame(['size' => 9000000, 'name' => 'holiday.mov', 'tags' => ['a', 'b']], $rows[0]['context']);

		// All rows of a request carry the same code
		$this->assertSame($rows[0]['request'], $rows[1]['request']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $rows[0]['request']);
	}

	public function test_secrets_in_the_context_are_not_stored(): void
	{
		UB_Log::add('login', 'Somebody logged in', [
			'email'            => 'ana@example.com',
			'password'         => 'correct horse',
			'password_confirm' => 'correct horse',
			'csrf'             => 'abc',
			'Authorization'    => 'Bearer xyz',
			'post'             => ['card_number' => '4111111111111111', 'reset_token' => 'r3s3t', 'name' => 'Ana', 'deep' => ['api_key' => 'k', 'ok' => 1]],
		]);

		$row = $this->written()[0];
		$raw = DB::select('context')->from('ub_logs')->execute($this->db)->get('context');

		foreach (['correct horse', 'abc', 'Bearer', '4111', 'r3s3t', '"k"'] as $secret)
		{
			$this->assertStringNotContainsString($secret, $raw);
		}

		$this->assertSame('ana@example.com', $row['context']['email']);
		$this->assertSame('***', $row['context']['password']);
		$this->assertSame('***', $row['context']['post']['card_number']);
		$this->assertSame('Ana', $row['context']['post']['name']);
		$this->assertSame(['api_key' => '***', 'ok' => 1], $row['context']['post']['deep']);
	}

	public function test_nothing_of_the_request_is_stored_by_itself(): void
	{
		$post = $_POST;

		try
		{
			$_POST = ['password' => 'correct horse', 'comment' => 'a private note'];

			Kohana::$log->add(Log::ERROR, 'Something failed');

			$raw = json_encode(DB::select()->from('ub_logs')->execute($this->db)->as_array() + $this->written());
		}
		finally
		{
			$_POST = $post;
		}

		$this->assertStringNotContainsString('correct horse', $raw);
		$this->assertStringNotContainsString('a private note', $raw);
	}

	public function test_what_is_no_scalar_in_a_context_is_made_harmless(): void
	{
		UB_Log::add('odd', 'Odd values', [
			'object' => new ArrayObject([1]),
			'long'   => str_repeat('x', 5000),
			'null'   => null,
			'float'  => 1.5,
			'bad'    => "caf\xE9",
		]);

		$context = $this->written()[0]['context'];

		$this->assertSame('ArrayObject', $context['object']);
		$this->assertLessThan(2100, strlen($context['long']));
		$this->assertNull($context['null']);
		$this->assertSame(1.5, $context['float']);
		$this->assertArrayHasKey('bad', $context);
	}

	public function test_a_context_that_is_too_long_is_cut_and_stays_json(): void
	{
		$context = [];

		for ($i = 0; $i < 30; $i++)
		{
			$context['value'.$i] = str_repeat('y', 1500);
		}

		UB_Log::add('big', 'A lot', $context);

		$row = $this->written()[0];

		$this->assertSame(['cut', 'start'], array_keys($row['context']));
		$this->assertLessThan(UB_Log::CONTEXT_MAX, strlen(DB::select('context')->from('ub_logs')->execute($this->db)->get('context')));
	}

	public function test_a_type_that_is_no_name_is_left_out(): void
	{
		UB_Log::add('<script>alert(1)</script>', 'one');
		UB_Log::add(str_repeat('a', 65), 'two');
		UB_Log::add('', 'three');
		Kohana::$log->add(Log::INFO, 'four', null, ['type' => ['an array'], 'context' => 'no array']);

		$rows = $this->written();

		$this->assertSame([null, null, null, null], array_column($rows, 'type'));
		$this->assertSame([], $rows[3]['context']);
	}

	public function test_an_error_has_its_code_its_class_and_its_trace(): void
	{
		$error = new RuntimeException('It broke');

		Kohana::$log->add(Log::ERROR, 'It broke', null, ['exception' => $error]);
		Kohana::$log->add(Log::ERROR, '[A1B2C3D4] RuntimeException [ 0 ]: logged by the framework');

		$rows = $this->written();

		$this->assertSame(Kohana_Exception::reference($error), $rows[0]['reference']);
		$this->assertSame('RuntimeException', $rows[0]['context']['exception']);
		$this->assertStringContainsString('#0', $rows[0]['context']['trace']);
		$this->assertSame('A1B2C3D4', $rows[1]['reference']);
	}

	public function test_the_request_and_who_it_is_about_are_written(): void
	{
		$requests = [Request::$initial, Request::$current];
		$ip       = Request::$client_ip;

		try
		{
			Request::$initial = Request::$current = null;
			Request::$initial = Request::factory('invoices/view/12')->method('POST');
			Request::$client_ip = '203.0.113.9';

			Kohana::$log->add(Log::INFO, 'Seen', null, ['user' => 42]);

			$row = $this->written()[0];
		}
		finally
		{
			list(Request::$initial, Request::$current) = $requests;

			Request::$client_ip = $ip;
		}

		$this->assertSame('POST', $row['method']);
		$this->assertSame('invoices/view/12', $row['uri']);
		$this->assertSame('203.0.113.9', $row['ip']);
		$this->assertEquals(42, $row['user_id']);
	}

	public function test_the_application_tells_who_is_logged_in(): void
	{
		$before = UB_Log_Database::$user;

		try
		{
			UB_Log_Database::$user = null;

			Kohana::$log->add(Log::INFO, 'nobody says');

			UB_Log_Database::$user = fn (): ?int => 7;

			Kohana::$log->add(Log::INFO, 'the application says');
			Kohana::$log->add(Log::INFO, 'the message says', null, ['user' => 42]);
			Kohana::$log->write();

			UB_Log_Database::$user = fn (): ?int => null;

			Kohana::$log->add(Log::INFO, 'a visitor');
			Kohana::$log->write();

			UB_Log_Database::$user = fn (): ?int => throw new RuntimeException('the session is gone');

			Kohana::$log->add(Log::INFO, 'asking fails');

			$rows = $this->written();
		}
		finally
		{
			UB_Log_Database::$user = $before;
		}

		$this->assertSame(['nobody says', 'the application says', 'the message says', 'a visitor', 'asking fails'], array_column($rows, 'message'));

		// The function is asked when the rows are written
		$this->assertEquals([7, 7, 42, null, null], array_column($rows, 'user_id'));
	}

	public function test_many_messages_are_one_statement_and_keep_their_order(): void
	{
		for ($i = 1; $i <= 25; $i++)
		{
			Kohana::$log->add(Log::DEBUG, 'message '.$i);
		}

		$rows = $this->written();

		$this->assertCount(25, $rows);
		$this->assertSame('message 1', $rows[0]['message']);
		$this->assertSame('message 25', $rows[24]['message']);
	}

	public function test_text_in_any_language_and_long_text_is_stored(): void
	{
		Kohana::$log->add(Log::INFO, 'Olá, não há acentuação que falhe — 日本語 🙂');
		Kohana::$log->add(Log::INFO, str_repeat('é', 40000));

		$rows = $this->written();

		$this->assertSame('Olá, não há acentuação que falhe — 日本語 🙂', $rows[0]['message']);
		$this->assertSame(60000, strlen($rows[1]['message']));
	}

	public function test_a_table_that_cannot_be_written_does_not_break_the_request(): void
	{
		$this->db->query(null, 'DROP TABLE '.$this->db->quote_table('ub_logs'));

		$log = ini_set('error_log', '/dev/null');

		try
		{
			Kohana::$log->add(Log::ERROR, 'The database is down');
			Kohana::$log->write();
		}
		finally
		{
			ini_set('error_log', (string) $log);
		}

		// Nothing was thrown
		$this->assertSame([], $this->db->list_tables('ub_logs'));
	}

	public function test_find_filters_by_level_type_text_and_the_rest(): void
	{
		UB_Log::add('upload.failed', 'Too large: holiday.mov', [], Log::WARNING);
		UB_Log::add('upload.done', 'Stored 100% of report_2026.pdf', [], Log::INFO);
		UB_Log::add('uploader', 'Not an upload type', [], Log::INFO);
		Kohana::$log->add(Log::ERROR, '[A1B2C3D4] It broke', null, ['user' => 7]);
		Kohana::$log->add(Log::DEBUG, 'A detail');

		$this->written();

		$messages = fn (array $filters): array => array_column(UB_Log::find($filters), 'message');

		$this->assertCount(5, $messages([]));
		$this->assertSame('A detail', $messages([])[0], 'newest first');
		$this->assertCount(2, UB_Log::find([], 2));

		$this->assertSame(['[A1B2C3D4] It broke', 'Too large: holiday.mov'], $messages(['level' => Log::WARNING]));
		$this->assertSame(['[A1B2C3D4] It broke'], $messages(['level' => Log::ERROR]));

		$this->assertSame(['Too large: holiday.mov'], $messages(['type' => 'upload.failed']));
		$this->assertCount(2, $messages(['type' => 'upload.*']));
		$this->assertCount(3, $messages(['type' => 'upload*']));
		$this->assertSame([], $messages(['type' => 'upload']));

		$this->assertSame(['[A1B2C3D4] It broke'], $messages(['reference' => 'A1B2C3D4']));
		$this->assertSame(['[A1B2C3D4] It broke'], $messages(['user' => 7]));
		$this->assertCount(5, $messages(['request' => Log_JSON::request_id()]));
		$this->assertSame([], $messages(['request' => 'ffffffff']));

		$this->assertSame(['Too large: holiday.mov'], $messages(['text' => 'holiday']));

		// "%" and "_" are looked for as they are
		$this->assertSame(['Stored 100% of report_2026.pdf'], $messages(['text' => '100%']));
		$this->assertSame(['Stored 100% of report_2026.pdf'], $messages(['text' => 'report_2026']));
		$this->assertSame([], $messages(['text' => 'report%2026']));
		$this->assertSame([], $messages(['text' => "' OR 1=1 --"]));

		$this->assertCount(5, $messages(['from' => time() - 60, 'to' => time() + 60]));
		$this->assertSame([], $messages(['from' => time() + 60]));
		$this->assertSame([], $messages(['to' => time() - 60]));
	}

	public function test_clean_removes_what_is_older_than_the_config_keeps(): void
	{
		$this->setEnvironment(['ub_log.keep' => ['days' => 30, 'error_days' => 100]]);

		$day = 86400;

		foreach ([
			['a new detail', LOG_DEBUG, 1],
			['an old detail', LOG_INFO, 31],
			['an old warning', LOG_WARNING, 31],
			['an old error', LOG_ERR, 31],
			['a very old error', LOG_ERR, 101],
			['a very old emergency', LOG_EMERG, 101],
		] as list($message, $level, $days))
		{
			DB::insert('ub_logs', ['time', 'level', 'message', 'request', 'ip', 'method', 'uri'])
				->values([time() - $days * $day, $level, $message, 'abcdef12', '127.0.0.1', 'GET', ''])
				->execute($this->db);
		}

		$this->assertSame(4, UB_Log::clean());
		$this->assertSame(['an old error', 'a new detail'], array_column(UB_Log::find(), 'message'));
		$this->assertSame(0, UB_Log::clean());
	}

	public function test_no_alert_is_sent_without_addresses(): void
	{
		Kohana::$log->add(Log::EMERGENCY, 'Everything is on fire');

		$this->written();

		$this->assertSame([], UB_LogTest_Writer::$sent);
	}

	public function test_errors_and_listed_types_are_sent_in_one_email(): void
	{
		$this->setEnvironment(['ub_log.alert' => ['to' => ['dev@example.com', 'not an address'], 'level' => LOG_ERR, 'types' => ['upload.security'], 'limit' => [10, 3600], 'subject' => "My Site\r\nBcc: x@example.com"]]);

		Kohana::$log->add(Log::ERROR, '[A1B2C3D4] It broke');
		Kohana::$log->add(Log::WARNING, 'Only a warning');
		UB_Log::add('upload.security', 'A script was uploaded', ['password' => 'correct horse', 'file' => 'evil.php'], Log::NOTICE);
		Kohana::$log->add(Log::CRITICAL, 'Worse');

		$this->written();

		$this->assertCount(1, UB_LogTest_Writer::$sent);

		list($to, $subject, $text) = UB_LogTest_Writer::$sent[0];

		$this->assertSame(['dev@example.com'], $to);
		// One line: the subject cannot add headers
		$this->assertSame('My Site Bcc: x@example.com', $subject);

		$this->assertStringContainsString('ERROR: [A1B2C3D4] It broke', $text);
		$this->assertStringContainsString('error code A1B2C3D4', $text);
		$this->assertStringContainsString('NOTICE upload.security: A script was uploaded', $text);
		$this->assertStringContainsString('CRITICAL: Worse', $text);
		$this->assertStringContainsString('request '.Log_JSON::request_id(), $text);

		// Not what was not asked for, and nothing of the context
		$this->assertStringNotContainsString('Only a warning', $text);
		$this->assertStringNotContainsString('evil.php', $text);
		$this->assertStringNotContainsString('correct horse', $text);
	}

	public function test_only_so_many_alerts_are_sent(): void
	{
		$this->setEnvironment(['ub_log.alert' => ['to' => ['dev@example.com'], 'level' => LOG_ERR, 'types' => [], 'limit' => [2, 3600]]]);

		for ($i = 0; $i < 5; $i++)
		{
			Kohana::$log->add(Log::ERROR, 'It broke again');
			Kohana::$log->write();
		}

		$this->assertCount(2, UB_LogTest_Writer::$sent);
		$this->assertCount(5, UB_Log::find());
	}

	public function test_a_message_about_email_asks_for_no_email(): void
	{
		$this->setEnvironment(['ub_log.alert' => ['to' => ['dev@example.com'], 'level' => LOG_ERR, 'types' => ['email.failed'], 'limit' => [10, 3600]]]);

		UB_Log::add('email.failed', 'The mail server is down', [], Log::ERROR);

		$this->written();

		$this->assertSame([], UB_LogTest_Writer::$sent);
	}

	public function test_the_task_removes_old_messages(): void
	{
		$output = fopen('php://memory', 'w+');

		Minion_CLI::$stdout = $output;

		try
		{
			DB::insert('ub_logs', ['time', 'level', 'message', 'request', 'ip', 'method', 'uri'])
				->values([time() - 400 * 86400, LOG_ERR, 'ancient', 'abcdef12', '127.0.0.1', 'GET', ''])
				->execute($this->db);

			Minion_Task::factory(['task' => 'ublog:clean'])->execute();

			rewind($output);

			$this->assertStringContainsString('Removed 1 old message(s)', (string) stream_get_contents($output));
			$this->assertSame([], UB_Log::find());
		}
		finally
		{
			Minion_CLI::$stdout = null;
		}
	}
}

/**
 * The writer, with alerts that are kept instead of sent.
 */
class UB_LogTest_Writer extends UB_Log_Database
{
	/**
	 * @var array<int, array{0: array<int, string>, 1: string, 2: string}>  The alerts: to, subject, text
	 */
	public static array $sent = [];

	protected function _send(array $to, string $subject, string $text): bool
	{
		static::$sent[] = [$to, $subject, $text];

		return true;
	}
}
