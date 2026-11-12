<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the log reader: reading both kinds of log file, the filters,
 * the hiding of secrets, the Minion task and the page.
 *
 * @package    UB_Log_Reader
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.log_reader')]
class UB_LogReaderTest extends Unittest_TestCase
{
	/**
	 * @var string  Directory of the logs of one test
	 */
	protected $directory;

	/**
	 * @var resource  Takes what the task writes
	 */
	protected $output;

	public function setUp(): void
	{
		parent::setUp();

		$this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ub_log_reader_'.bin2hex(random_bytes(6));

		mkdir($this->directory);

		$this->output = fopen('php://memory', 'w+');

		Minion_CLI::$stdout = $this->output;
		Minion_CLI::$stderr = $this->output;

		$this->setEnvironment([
			'Kohana::$environment'    => Kohana::DEVELOPMENT,
			'Kohana::$base_url'       => '/',
			'Kohana::$index_file'     => false,
			'Request::$initial'       => null,
			'ub_log_reader.directory' => $this->directory,
			'ub_log_reader.page'      => true,
			'ub_log_reader.production'  => false,
			'ub_log_reader.allowed_ips' => [],
			'Request::$client_ip'       => '203.0.113.7',
		]);
	}

	public function tearDown(): void
	{
		Minion_CLI::$stdout = null;
		Minion_CLI::$stderr = null;

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file)
		{
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($this->directory);

		parent::tearDown();
	}

	/**
	 * Writes the log file of a day: text, or JSON when the entries are arrays.
	 *
	 * @param array<int, string|array> $entries
	 */
	protected function day(string $day, array $entries): void
	{
		$json = is_array($entries[0] ?? null);
		$path = $this->directory.'/'.str_replace('-', '/', $day).($json ? '.json' : '').'.php';

		is_dir(dirname($path)) or mkdir(dirname($path), 0777, true);

		$lines = array_map(fn ($entry) => $json ? json_encode($entry) : $entry, $entries);

		file_put_contents($path, Log_File::FILE_SECURITY.PHP_EOL.($json ? '' : PHP_EOL).implode($json ? PHP_EOL : PHP_EOL.PHP_EOL, $lines).PHP_EOL);
	}

	/**
	 * Writes three days of logs: two as text, the newest as JSON.
	 */
	protected function logs(): void
	{
		$this->day('2026-11-10', [
			'2026-11-10 08:00:00 --- INFO: Nightly export done in /app/classes/Task/Export.php:40',
			'2026-11-10 09:30:00 --- WARNING: Disk almost full in /app/classes/Monitor.php:12',
		]);

		$this->day('2026-11-11', [
			'2026-11-11 10:00:00 --- INFO: User ana logged in in /app/classes/Controller/Login.php:25',
			"2026-11-11 10:05:00 --- ERROR: [A1B2C3D4] Kohana_Exception [ 0 ]: The payment failed ~ APPPATH/classes/Payment.php [ 88 ]\nCaused by: RuntimeException [ 0 ]: gateway said no in /app/classes/Payment.php:88",
			"2026-11-11 10:05:00 --- DEBUG: #0 /app/classes/Controller/Shop.php(31): Payment->charge()\n#1 {main} in /app/classes/Payment.php:88",
			'2026-11-11 11:00:00 --- NOTICE: Login failed: password=hunter2 user=ana in /app/classes/Controller/Login.php:30',
			'2026-11-11 12:00:00 --- DEBUG: Cache miss for menu in /app/classes/Menu.php:9',
		]);

		$this->day('2026-11-12', [
			['time' => '2026-11-12T09:00:00+00:00', 'level' => 'INFO', 'message' => 'Page shown', 'file' => 'APPPATH/classes/Controller/Home.php', 'line' => 14, 'request' => 'aaaa1111', 'method' => 'GET', 'uri' => 'home'],
			['time' => '2026-11-12T09:00:01+00:00', 'level' => 'CRITICAL', 'message' => '[FFEE0011] Database_Exception [ 0 ]: The database is gone', 'file' => 'MODPATH/database/classes/Kohana/Database/PDO.php', 'line' => 90, 'request' => 'bbbb2222', 'method' => 'POST', 'uri' => 'orders/save?token=abc123', 'reference' => 'FFEE0011', 'exception' => 'Database_Exception', 'trace' => "#0 APPPATH/classes/Controller/Orders.php(12): Database->connect()\n#1 {main}"],
			['time' => '2026-11-12T09:00:02+00:00', 'level' => 'INFO', 'message' => 'Order not saved', 'file' => 'APPPATH/classes/Controller/Orders.php', 'line' => 20, 'request' => 'bbbb2222', 'method' => 'POST', 'uri' => 'orders/save?token=abc123'],
		]);
	}

	/**
	 * The messages of a search, the newest first.
	 *
	 * @return array<int, string>
	 */
	protected function messages(array $filters = [], int $limit = 100): array
	{
		return array_column((new UB_Log_Reader)->search($filters, $limit), 'message');
	}

	public function test_both_kinds_of_file_are_read_newest_first(): void
	{
		$this->logs();

		$reader = new UB_Log_Reader;

		$this->assertSame(['2026-11-12', '2026-11-11', '2026-11-10'], $reader->days());

		$entries = $reader->search();

		// The trace of the error is part of the error, not an entry of its own
		$this->assertCount(9, $entries);
		$this->assertSame('Order not saved', $entries[0]['message']);
		$this->assertSame('Nightly export done', $entries[8]['message']);

		// An entry of the JSON log
		$this->assertSame('CRITICAL', $entries[1]['level']);
		$this->assertSame('FFEE0011', $entries[1]['reference']);
		$this->assertSame('bbbb2222', $entries[1]['request']);
		$this->assertSame('Database_Exception', $entries[1]['exception']);
		$this->assertSame(90, $entries[1]['line']);
		$this->assertStringStartsWith('#0 APPPATH', $entries[1]['trace']);
		$this->assertSame(strtotime('2026-11-12T09:00:01+00:00'), $entries[1]['time']);

		// An entry of the text log, with a message of two lines and its trace
		$error = $entries[5];

		$this->assertSame('ERROR', $error['level']);
		$this->assertSame('A1B2C3D4', $error['reference']);
		$this->assertSame("[A1B2C3D4] Kohana_Exception [ 0 ]: The payment failed ~ APPPATH/classes/Payment.php [ 88 ]\nCaused by: RuntimeException [ 0 ]: gateway said no", $error['message']);
		$this->assertSame('/app/classes/Payment.php', $error['file']);
		$this->assertSame(88, $error['line']);
		$this->assertSame("#0 /app/classes/Controller/Shop.php(31): Payment->charge()\n#1 {main}", $error['trace']);
		$this->assertNull($error['request']);
	}

	public static function provider_filters(): array
	{
		return [
			// filters => the messages that fit, the newest first
			'errors and worse' => [
				['level' => 'error'],
				['[FFEE0011] Database_Exception [ 0 ]: The database is gone', "[A1B2C3D4] Kohana_Exception [ 0 ]: The payment failed ~ APPPATH/classes/Payment.php [ 88 ]\nCaused by: RuntimeException [ 0 ]: gateway said no"],
			],
			'warnings and worse of two days' => [
				['level' => 'WARNING', 'to' => '2026-11-11'],
				["[A1B2C3D4] Kohana_Exception [ 0 ]: The payment failed ~ APPPATH/classes/Payment.php [ 88 ]\nCaused by: RuntimeException [ 0 ]: gateway said no", 'Disk almost full'],
			],
			'text, whatever its case' => [['text' => 'DISK'], ['Disk almost full']],
			'text in the trace' => [['text' => 'Shop.php'], ["[A1B2C3D4] Kohana_Exception [ 0 ]: The payment failed ~ APPPATH/classes/Payment.php [ 88 ]\nCaused by: RuntimeException [ 0 ]: gateway said no"]],
			'text in the file' => [['text' => 'Monitor.php'], ['Disk almost full']],
			'text in the address' => [['text' => 'orders/save'], ['Order not saved', '[FFEE0011] Database_Exception [ 0 ]: The database is gone']],
			'an error by its code' => [['reference' => 'ffee0011'], ['[FFEE0011] Database_Exception [ 0 ]: The database is gone']],
			'a code of the text log' => [['reference' => 'A1B2C3D4', 'level' => 'ERROR'], ["[A1B2C3D4] Kohana_Exception [ 0 ]: The payment failed ~ APPPATH/classes/Payment.php [ 88 ]\nCaused by: RuntimeException [ 0 ]: gateway said no"]],
			'all of one request' => [['request' => 'bbbb2222'], ['Order not saved', '[FFEE0011] Database_Exception [ 0 ]: The database is gone']],
			'one day' => [['from' => '2026-11-10', 'to' => '2026-11-10'], ['Disk almost full', 'Nightly export done']],
			'a part of a day' => [['from' => '2026-11-11 10:30:00', 'to' => '2026-11-11 11:30:00'], ['Login failed: password=*** user=ana']],
			'from a day on' => [['from' => '2026-11-12', 'level' => 'INFO', 'text' => 'page'], ['Page shown']],
			'nothing fits' => [['text' => 'no such text anywhere'], []],
			'a code that is not there' => [['reference' => '00000000'], []],
			'a level that does not exist keeps nothing out' => [['level' => 'LOUD', 'text' => 'Disk'], ['Disk almost full']],
			'filters that are no text are passed over' => [['text' => ['a'], 'level' => null, 'from' => [], 'reference' => 5, 'request' => 'aaaa1111'], ['Page shown']],
			'a date that cannot be read is passed over' => [['from' => 'not a date', 'text' => 'Disk'], ['Disk almost full']],
		];
	}

	#[DataProvider('provider_filters')]
	public function test_filters(array $filters, array $expected): void
	{
		$this->logs();

		$this->assertSame($expected, $this->messages($filters));
	}

	/**
	 * The limit gives the newest entries, across days.
	 */
	public function test_limit_gives_the_newest(): void
	{
		$this->logs();

		$this->assertSame(['Order not saved'], $this->messages([], 1));
		$this->assertSame(['Order not saved', '[FFEE0011] Database_Exception [ 0 ]: The database is gone', 'Page shown', 'Cache miss for menu'], $this->messages([], 4));
		$this->assertSame(['Cache miss for menu', 'Login failed: password=*** user=ana'], $this->messages(['to' => '2026-11-11'], 2));
		$this->assertCount(9, $this->messages([], 0 + 5000), 'More than the most is the most');
		$this->assertCount(1, $this->messages([], -3), 'Less than one is one');
	}

	public static function provider_secrets(): array
	{
		return [
			// text of a log => what is shown
			['password=hunter2', 'password=***'],
			['Login failed: password=hunter2 user=ana', 'Login failed: password=*** user=ana'],
			['{"user":"ana","password":"hunter2","age":3}', '{"user":"ana","password":***,"age":3}'],
			["'db_password' => 'hunter2',", "'db_password' => ***,"],
			['token: abc.def-123', 'token: ***'],
			['?page=2&access_token=abc123&sort=name', '?page=2&access_token=***&sort=name'],
			['Authorization: Bearer eyJhbGciOi.payload.sig', 'Authorization: ***'],
			['authorization: Basic YW5hOmh1bnRlcjI=', 'authorization: ***'],
			['Cookie: session=abcdef', 'Cookie: ***'],
			['api_key=K123 apikey=K456 API-KEY: K789', 'api_key=*** apikey=*** API-KEY: ***'],
			['client_secret = "s3cr3t value"', 'client_secret = ***'],
			['cookie salt=xyz', 'cookie salt=***'],
			['X-Amz-Signature=abcdef0123', 'X-Amz-Signature=***'],
			['PASSWD=root', 'PASSWD=***'],
			['Nothing secret here: name=ana page=2', 'Nothing secret here: name=ana page=2'],
			['The password was wrong', 'The password was wrong'],
			['', ''],
		];
	}

	/**
	 * What looks like a secret is not shown.
	 */
	#[DataProvider('provider_secrets')]
	public function test_secrets_are_hidden(string $text, string $expected): void
	{
		$this->assertSame($expected, UB_Log_Reader::mask($text));
	}

	public function test_hiding_covers_message_address_and_trace_and_can_be_switched_off(): void
	{
		$this->day('2026-11-12', [
			['time' => '2026-11-12T09:00:00+00:00', 'level' => 'ERROR', 'message' => 'Login with password=hunter2', 'uri' => 'login?token=abc123', 'trace' => '#0 Auth->login(password=hunter2)'],
		]);

		$reader = new UB_Log_Reader;
		$entry  = $reader->search()[0];

		$this->assertSame('Login with password=***', $entry['message']);
		$this->assertSame('login?token=***', $entry['uri']);
		$this->assertSame('#0 Auth->login(password=***)', $entry['trace']);

		// A search for the secret still finds the entry: it is hidden, not gone
		$this->assertCount(1, $reader->search(['text' => 'hunter2']));

		$reader->mask = false;

		$this->assertSame('Login with password=hunter2', $reader->search()[0]['message']);
	}

	/**
	 * A day that has both files is read from the JSON one, so nothing
	 * is listed twice. Lines that are no entries are passed over.
	 */
	public function test_day_with_both_files_and_broken_lines(): void
	{
		$this->day('2026-11-12', ['2026-11-12 09:00:00 --- INFO: From the text file in /app/a.php:1']);
		$this->day('2026-11-12', [['time' => '2026-11-12T09:00:00+00:00', 'level' => 'INFO', 'message' => 'From the JSON file']]);

		file_put_contents($this->directory.'/2026/11/12.json.php', "this is not JSON\n{\"level\":\"LOUD\",\"message\":\"no such level\"}\n[1,2]\n", FILE_APPEND);

		$this->assertSame(['From the JSON file'], $this->messages());

		// Files that are no logs of a day are not looked at
		file_put_contents($this->directory.'/2026/11/notes.php', 'x');
		file_put_contents($this->directory.'/readme.php', 'x');

		$this->assertSame(['2026-11-12'], (new UB_Log_Reader)->days());
	}

	public function test_no_logs_and_no_directory(): void
	{
		$this->assertSame([], (new UB_Log_Reader)->search());
		$this->assertSame([], (new UB_Log_Reader)->days());

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('The directory of the logs is not there');

		new UB_Log_Reader($this->directory.'/nothing');
	}

	/**
	 * What the real log writers write is read back.
	 */
	public function test_reads_what_the_log_writers_write(): void
	{
		foreach (['Log_File', 'Log_JSON'] as $writer)
		{
			$directory = $this->directory.'/'.$writer;

			mkdir($directory);

			$log = new Log;
			$log->attach(new $writer($directory));

			$this->setEnvironment(['Kohana::$log' => $log]);

			$error = new Kohana_Exception('The payment of :who failed', [':who' => 'Ana']);

			$log->add(Log::INFO, 'A plain message');
			Kohana_Exception::log($error);

			$entries = (new UB_Log_Reader($directory))->search();

			$this->assertCount(2, $entries, $writer);
			// Errors that nothing caught are logged at the worst level
			$this->assertSame('EMERGENCY', $entries[0]['level'], $writer);
			$this->assertSame(Kohana_Exception::reference($error), $entries[0]['reference'], $writer);
			$this->assertStringContainsString('The payment of Ana failed', $entries[0]['message'], $writer);
			$this->assertStringStartsWith('#0 ', (string) $entries[0]['trace'], $writer);
			$this->assertSame('A plain message', $entries[1]['message'], $writer);
			$this->assertEqualsWithDelta(time(), $entries[1]['time'], 5, $writer);

			// Found by the code a visitor would report
			$this->assertCount(1, (new UB_Log_Reader($directory))->search(['reference' => Kohana_Exception::reference($error)]), $writer);
		}
	}

	/**
	 * Runs the task and returns what it wrote.
	 */
	protected function task(array $options = []): string
	{
		ftruncate($this->output, 0);
		rewind($this->output);

		Minion_Task::factory($options + ['task' => 'log:search'])->execute();

		rewind($this->output);

		return stream_get_contents($this->output);
	}

	public function test_task(): void
	{
		$this->logs();

		$all = $this->task();

		// Times are shown in the time zone of the server
		$this->assertStringContainsString(date('Y-m-d H:i:s', strtotime('2026-11-12T09:00:02+00:00')), $all);
		$this->assertStringContainsString('Order not saved', $all);
		$this->assertStringContainsString('(/app/classes/Monitor.php:12)', $all);
		$this->assertStringContainsString('request bbbb2222', $all);
		$this->assertStringContainsString('9 entries.', $all);
		$this->assertStringContainsString('password=***', $all);
		$this->assertStringNotContainsString('hunter2', $all);
		$this->assertStringNotContainsString('#0 ', $all, 'Traces are shown when asked for');

		$errors = $this->task(['level' => 'error', 'trace' => null]);

		$this->assertStringContainsString('2 entries.', $errors);
		$this->assertStringContainsString('#0 /app/classes/Controller/Shop.php(31)', $errors);
		$this->assertStringNotContainsString('Disk almost full', $errors);

		$this->assertStringContainsString('1 entry.', $this->task(['reference' => 'A1B2C3D4']));
		$this->assertStringContainsString('1 entry.', $this->task(['limit' => '1']));
		$this->assertStringContainsString('No entry fits.', $this->task(['text' => 'no such text']));

		// Another directory
		mkdir($this->directory.'/other');

		$this->assertStringContainsString('No entry fits.', $this->task(['dir' => $this->directory.'/other']));
	}

	public function test_top_counts_messages_that_differ_in_numbers_as_one(): void
	{
		$this->day('2026-11-10', [
			'2026-11-10 08:00:00 --- ERROR: [AAAA0001] Kohana_Exception [ 0 ]: Order 17 not saved ~ APPPATH/classes/Order.php [ 40 ] in /app/classes/Order.php:40',
			'2026-11-10 08:00:00 --- DEBUG: #0 /app/classes/Order.php(40): save() in /app/classes/Order.php:40',
			'2026-11-10 09:00:00 --- WARNING: Disk almost full in /app/classes/Monitor.php:12',
			'2026-11-10 10:00:00 --- ERROR: [AAAA0002] Kohana_Exception [ 0 ]: Order 18 not saved ~ APPPATH/classes/Order.php [ 40 ] in /app/classes/Order.php:40',
		]);
		$this->day('2026-11-11', [
			'2026-11-11 07:00:00 --- ERROR: Login failed: password=hunter2 in /app/classes/Login.php:3',
			'2026-11-11 08:00:00 --- WARNING: Disk almost full in /app/classes/Monitor.php:12',
			'2026-11-11 09:00:00 --- ERROR: [AAAA0003] Kohana_Exception [ 0 ]: Order 2051 not saved ~ APPPATH/classes/Order.php [ 40 ] in /app/classes/Order.php:40',
			'2026-11-11 10:00:00 --- INFO: Order 5 not saved in /app/classes/Order.php:44',
		]);

		$top = (new UB_Log_Reader)->top();

		$this->assertSame([3, 2, 1, 1], array_column($top, 'count'));

		// The newest wording, and the code of the newest error, to look it up
		$this->assertSame('ERROR', $top[0]['level']);
		$this->assertSame('[AAAA0003] Kohana_Exception [ 0 ]: Order 2051 not saved ~ APPPATH/classes/Order.php [ 40 ]', $top[0]['message']);
		$this->assertSame('AAAA0003', $top[0]['reference']);
		$this->assertSame(strtotime('2026-11-10 08:00:00'), $top[0]['first']);
		$this->assertSame(strtotime('2026-11-11 09:00:00'), $top[0]['last']);

		$this->assertSame('Disk almost full', $top[1]['message']);

		// Equally frequent: the newest first. The same words at another level are another message.
		$this->assertSame('Order 5 not saved', $top[2]['message']);
		$this->assertSame('Login failed: password=***', $top[3]['message']);

		// The filters and the limit of a search
		$this->assertSame([1, 1], array_column((new UB_Log_Reader)->top(['level' => 'ERROR', 'from' => '2026-11-11']), 'count'));
		$this->assertCount(1, (new UB_Log_Reader)->top([], 1));
		$this->assertSame([], (new UB_Log_Reader)->top(['text' => 'no such text']));
	}

	public function test_top_task_and_page(): void
	{
		$this->logs();

		$output = $this->task(['task' => 'log:top', 'level' => 'notice']);

		$this->assertStringContainsString('Times', $output);
		$this->assertStringContainsString('The payment failed', $output);
		$this->assertStringContainsString('password=***', $output);
		$this->assertStringNotContainsString('Nightly export', $output);
		$this->assertStringNotContainsString('Caused by', $output, 'One line of each message');

		$this->assertStringContainsString('No entry fits.', $this->task(['task' => 'log:top', 'text' => 'no such text']));

		$html = $this->page(['show' => 'top'])->body();

		$this->assertStringContainsString('9 messages, the most frequent first', $html);
		$this->assertStringContainsString('<strong>Most frequent</strong>', $html);
		$this->assertStringContainsString('name="show" value="top"', $html);
		$this->assertStringContainsString('reference=FFEE0011', $html);
		$this->assertStringNotContainsString('hunter2', $html);
		$this->assertStringNotContainsString('<details>', $html);

		$this->assertStringContainsString('show=top', $this->page()->body());
	}

	public function test_task_refuses_a_level_that_does_not_exist(): void
	{
		$this->expectException(Minion_Exception_InvalidOptions::class);

		$this->task(['level' => 'loud']);
	}

	/**
	 * Asks for the page as a browser would.
	 */
	protected function page(array $query = []): Response
	{
		$routes = new ReflectionProperty('Route', '_routes');
		$before = $routes->getValue();

		// The route as the module sets it
		$routes->setValue(null, []);

		Route::set('ub-log-reader', 'ub-log-reader')->defaults(['directory' => 'UB/Log', 'controller' => 'Reader', 'action' => 'index']);

		// The first request of a page reads the address of the visitor from the server
		$address = $_SERVER['REMOTE_ADDR'] ?? null;

		$_SERVER['REMOTE_ADDR'] = Request::$client_ip;

		try
		{
			return Request::factory('ub-log-reader')->query($query)->execute();
		}
		finally
		{
			if ($address === null)
			{
				unset($_SERVER['REMOTE_ADDR']);
			}
			else
			{
				$_SERVER['REMOTE_ADDR'] = $address;
			}

			$routes->setValue(null, $before);
		}
	}

	public function test_page_shows_and_filters(): void
	{
		$this->logs();

		$response = $this->page();
		$html     = $response->body();

		$this->assertSame(200, $response->status());
		$this->assertSame('no-store', (string) $response->headers('cache-control'));
		$this->assertStringContainsString('9 entries', $html);
		$this->assertStringContainsString('Order not saved', $html);
		$this->assertStringContainsString('ub-log-CRITICAL', $html);
		$this->assertStringContainsString('from 2026-11-10 to 2026-11-12', $html);
		$this->assertStringContainsString('password=***', $html);
		$this->assertStringNotContainsString('hunter2', $html);

		// A link to everything of the request, and one to the error by its code
		$this->assertStringContainsString('request=bbbb2222', $html);
		$this->assertStringContainsString('reference=FFEE0011', $html);

		$filtered = $this->page(['level' => 'ERROR', 'text' => 'payment'])->body();

		$this->assertStringContainsString('1 entry', $filtered);
		$this->assertStringContainsString('The payment failed', $filtered);
		$this->assertStringNotContainsString('Order not saved', $filtered);
		$this->assertStringContainsString('value="payment"', $filtered);
		$this->assertStringContainsString('<option value="ERROR" selected>', $filtered);
	}

	/**
	 * What is in a log, and what is typed in the form, is shown as text:
	 * it never becomes markup. The page has no script but that of the theme.
	 */
	public function test_page_writes_everything_as_text(): void
	{
		$this->day('2026-11-12', [
			['time' => '2026-11-12T09:00:00+00:00', 'level' => 'ERROR', 'message' => '<script>alert(1)</script>', 'file' => '"><img src=x>', 'uri' => '<b>uri</b>', 'request' => '"><i>', 'reference' => '"onmouseover="x', 'trace' => '</pre><script>alert(2)</script>'],
		]);

		$html = $this->page(['text' => '"><script>alert(3)</script>', 'from' => "'><b>", 'level' => '<x>', 'limit' => '<y>'])->body()
			.$this->page()->body();

		// The one script of the page is that of the theme, with the nonce of the request
		$theme = '<script nonce="'.HTML::chars(Security::nonce()).'">'.Theme::script().'</script>';

		$this->assertSame(2, substr_count($html, $theme));

		$html = str_replace($theme, '', $html);

		$this->assertStringNotContainsString('<script', $html);
		$this->assertStringNotContainsString('<img', $html);
		$this->assertStringNotContainsString('<b>', $html);
		$this->assertStringNotContainsString('<i>', $html);
		$this->assertStringNotContainsString('"onmouseover="x', $html);
		$this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
		$this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=|style\s*=/i', $html, 'No inline handlers or styles');
	}

	/**
	 * Outside development the address is not found, and neither when
	 * the page is switched off.
	 */
	public function test_page_is_not_there_outside_development(): void
	{
		$this->logs();

		foreach ([Kohana::PRODUCTION, Kohana::STAGING, Kohana::TESTING] as $environment)
		{
			$this->setEnvironment(['Kohana::$environment' => $environment]);

			$response = $this->page(['text' => 'payment']);

			$this->assertSame(404, $response->status());
			$this->assertStringNotContainsString('The payment failed', (string) $response->body());
		}

		$this->setEnvironment(['Kohana::$environment' => Kohana::DEVELOPMENT, 'ub_log_reader.page' => false]);

		$this->assertSame(404, $this->page()->status());

		// The task is not bound to development
		$this->setEnvironment(['Kohana::$environment' => Kohana::PRODUCTION]);

		$this->assertStringContainsString('9 entries.', $this->task());
	}

	/**
	 * What config/ub_log_reader.php says, the address of the visitor,
	 * and whether the page is there in production.
	 *
	 * @return array<string, array{mixed, mixed, string, bool}>
	 */
	public static function provider_production(): array
	{
		return [
			'nothing set'              => [false, [], '203.0.113.7', false],
			'only the switch'          => [true, [], '203.0.113.7', false],
			'only the list'            => [false, ['203.0.113.7'], '203.0.113.7', false],
			'the switch is not true'   => [1, ['203.0.113.7'], '203.0.113.7', false],
			'the switch is "true"'     => ['true', ['203.0.113.7'], '203.0.113.7', false],
			'both, the address fits'   => [true, ['198.51.100.1', '203.0.113.7'], '203.0.113.7', true],
			'both, another address'    => [true, ['203.0.113.7'], '203.0.113.8', false],
			'a network'                => [true, ['10.0.0.0/8'], '10.200.3.4', true],
			'outside the network'      => [true, ['10.0.0.0/8'], '11.0.0.1', false],
			'a small network'          => [true, ['203.0.113.0/30'], '203.0.113.3', true],
			'just outside it'          => [true, ['203.0.113.0/30'], '203.0.113.4', false],
			'IPv6'                     => [true, ['2001:db8::/32'], '2001:db8:1::7', true],
			'IPv6 outside'             => [true, ['2001:db8::/32'], '2001:db9::7', false],
			'IPv4 in an IPv6 list'     => [true, ['::/0'], '203.0.113.7', false],
			'the list is not a list'   => [true, '203.0.113.7', '203.0.113.7', false],
			'an entry is not text'     => [true, [true, null, ['203.0.113.7']], '203.0.113.7', false],
			'no address is known'      => [true, ['203.0.113.7'], '', false],
			'part of an address'       => [true, ['203.0.113.7'], '203.0.113.70', false],
		];
	}

	#[DataProvider('provider_production')]
	public function test_page_in_production_needs_the_switch_and_the_address(mixed $switch, mixed $list, string $ip, bool $expected): void
	{
		$this->logs();

		$this->setEnvironment([
			'Kohana::$environment'      => Kohana::PRODUCTION,
			'ub_log_reader.production'  => $switch,
			'ub_log_reader.allowed_ips' => $list,
			'Request::$client_ip'       => $ip,
		]);

		$this->assertSame($expected, UB_Log_Reader::allowed($ip));

		$response = $this->page(['text' => 'payment']);

		$this->assertSame($expected ? 200 : 404, $response->status());
		$this->assertSame($expected, str_contains( (string) $response->body(), 'The payment failed'));
	}

	public function test_reading_the_logs_in_production_is_logged(): void
	{
		$this->logs();

		$writer = new UB_LogReaderTest_Writer;

		Kohana::$log->attach($writer);

		try
		{
			$this->setEnvironment([
				'Kohana::$environment'      => Kohana::PRODUCTION,
				'ub_log_reader.production'  => true,
				'ub_log_reader.allowed_ips' => ['203.0.113.7'],
			]);

			$this->assertSame(200, $this->page()->status());

			Kohana::$log->write();

			$this->assertContains('ub_log_reader: the logs were read from 203.0.113.7', $writer->messages);

			// In development nobody is counted, and a refused visitor is not either
			$writer->messages = [];

			$this->setEnvironment(['Request::$client_ip' => '198.51.100.9']);
			$this->assertSame(404, $this->page()->status());

			$this->setEnvironment(['Kohana::$environment' => Kohana::DEVELOPMENT]);
			$this->assertSame(200, $this->page()->status());

			Kohana::$log->write();

			$this->assertNotContains('ub_log_reader: the logs were read from 198.51.100.9', $writer->messages);
		}
		finally
		{
			Kohana::$log->detach($writer);
		}
	}

	public function test_the_page_is_switched_off_in_every_environment(): void
	{
		$this->setEnvironment([
			'Kohana::$environment'      => Kohana::PRODUCTION,
			'ub_log_reader.page'        => false,
			'ub_log_reader.production'  => true,
			'ub_log_reader.allowed_ips' => ['203.0.113.7'],
		]);

		$this->assertFalse(UB_Log_Reader::allowed('203.0.113.7'));
	}
}

/**
 * A log writer that keeps the messages it is given.
 */
class UB_LogReaderTest_Writer extends Log_Writer
{
	/**
	 * @var array<int, string>
	 */
	public array $messages = [];

	public function write(array $messages): void
	{
		foreach ($messages as $message)
		{
			$this->messages[] = $message['body'];
		}
	}
}
