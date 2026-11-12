<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the debug bar: when it is shown, where it is put, what the panels
 * collect and that secrets stay hidden.
 *
 * The tests run on the command line, where the bar is never shown, so
 * they use a class that only leaves that one check out.
 *
 * @package    UB_Debug
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.ub_debug')]
class UB_DebugTest extends Unittest_TestCase
{
	protected int $environment;
	protected bool $profiling;
	protected array $config;

	public function setUp(): void
	{
		parent::setUp();

		$this->environment = Kohana::$environment;
		$this->profiling   = Kohana::$profiling;
		$this->config      = Kohana::$config->load('ub_debug')->as_array();

		Kohana::$environment = Kohana::DEVELOPMENT;
		Kohana::$profiling   = true;

		UB_DebugTest_Bar::reset();
	}

	public function tearDown(): void
	{
		Kohana::$environment = $this->environment;
		Kohana::$profiling   = $this->profiling;

		foreach ($this->config as $key => $value)
		{
			Kohana::$config->load('ub_debug')->set($key, $value);
		}

		Request::$initial = null;

		parent::tearDown();
	}

	/**
	 * A whole HTML page.
	 */
	protected function page(): string
	{
		return '<!doctype html><html><body><h1>Page</h1></body></html>';
	}

	public static function provider_environments(): array
	{
		return [
			'production' => [Kohana::PRODUCTION],
			'staging'    => [Kohana::STAGING],
			'testing'    => [Kohana::TESTING],
		];
	}

	/**
	 * Only development shows the bar, whatever the settings say.
	 */
	#[DataProvider('provider_environments')]
	public function test_never_enabled_outside_development(int $environment): void
	{
		Kohana::$config->load('ub_debug')->set('enabled', true);
		Kohana::$environment = $environment;

		$this->assertFalse(UB_DebugTest_Bar::enabled());
		$this->assertSame($this->page(), UB_DebugTest_Bar::inject($this->page()));
	}

	public function test_real_class_is_off_on_the_command_line(): void
	{
		$this->assertFalse(UB_Debug::enabled());
	}

	public function test_enabled_in_development_unless_switched_off(): void
	{
		$this->assertTrue(UB_DebugTest_Bar::enabled());

		Kohana::$config->load('ub_debug')->set('enabled', false);
		$this->assertFalse(UB_DebugTest_Bar::enabled());

		Kohana::$config->load('ub_debug')->set('enabled', true);
		UB_DebugTest_Bar::disable();
		$this->assertFalse(UB_DebugTest_Bar::enabled());
	}

	public function test_bar_is_put_before_the_closing_body_tag(): void
	{
		$page = UB_DebugTest_Bar::inject($this->page());

		$this->assertStringContainsString('id="ub-debug"', $page);
		$this->assertLessThan(strripos($page, '</body>'), strpos($page, 'id="ub-debug"'));
		$this->assertStringEndsWith('</body></html>', $page);
	}

	public function test_output_without_body_tag_is_left_alone(): void
	{
		$this->assertSame('<p>A piece</p>', UB_DebugTest_Bar::inject('<p>A piece</p>'));
		$this->assertSame('{"ok":true}', UB_DebugTest_Bar::inject('{"ok":true}'));
	}

	public function test_ajax_request_gets_no_bar(): void
	{
		Request::$initial = Request::factory('welcome/index');
		Request::$initial->requested_with('XMLHttpRequest');

		$this->assertSame($this->page(), UB_DebugTest_Bar::inject($this->page()));
	}

	public function test_excluded_route_gets_no_bar(): void
	{
		Request::$initial = Request::factory('welcome/index');
		Request::$initial->route(Route::get('default'));

		Kohana::$config->load('ub_debug')->set('exclude_routes', ['default']);
		$this->assertSame($this->page(), UB_DebugTest_Bar::inject($this->page()));

		Kohana::$config->load('ub_debug')->set('exclude_routes', []);
		$this->assertStringContainsString('ub-debug', UB_DebugTest_Bar::inject($this->page()));
	}

	public function test_panels_follow_the_config(): void
	{
		Kohana::$config->load('ub_debug')->set('panels', ['server' => true, 'files' => false, 'modules' => true, 'no_such_panel' => true]);

		$this->assertSame(['server', 'modules'], array_keys(UB_DebugTest_Bar::panels()));

		$html = UB_DebugTest_Bar::render();

		$this->assertStringContainsString('id="k-db-panel-server"', $html);
		$this->assertStringNotContainsString('id="k-db-panel-files"', $html);
	}

	public function test_every_panel_renders(): void
	{
		Request::$initial = Request::factory('welcome/index');
		Request::$initial->route(Route::get('default'));

		$panels = $this->config['panels'];
		$panels['config'] = true;

		Kohana::$config->load('ub_debug')->set('panels', array_fill_keys(array_keys($panels), true));
		Kohana::$config->load('ub_debug')->set('config_groups', ['ub_debug']);

		Profiler::add('UB_Debug test', ['a' => 1]);

		// The Cache panel is only there once the Cache class was used
		class_exists('Cache');

		$html = UB_DebugTest_Bar::render();

		foreach (array_keys($panels) as $name)
		{
			$this->assertStringContainsString('id="k-db-panel-'.$name.'"', $html, 'Panel '.$name);
		}

		$this->assertStringNotContainsString('This panel could not be made', $html);
	}

	/**
	 * The bar has no inline handlers or style attributes, and its style
	 * and script carry the nonce: it works under a strict policy.
	 */
	public function test_bar_is_safe_for_a_strict_content_security_policy(): void
	{
		Request::$initial = Request::factory('welcome/index');
		Request::$initial->route(Route::get('default'));

		$html = UB_DebugTest_Bar::render();

		// Only the markup: the script may hold these words in its code
		$markup = preg_replace('#<(script|style)\b.*?</\1>#s', '', $html);

		$this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $markup);
		$this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $markup);

		$nonce = HTML::chars(Security::nonce());

		$this->assertStringContainsString('<style nonce="'.$nonce.'">', $html);
		$this->assertStringContainsString('<script nonce="'.$nonce.'">', $html);
	}

	public function test_secret_values_are_hidden(): void
	{
		$this->assertStringContainsString('***', UB_DebugTest_Bar::value('password', 'hunter2'));
		$this->assertStringNotContainsString('hunter2', UB_DebugTest_Bar::value('password', 'hunter2'));

		// Header names are written with "-"
		$this->assertStringNotContainsString('key123', UB_DebugTest_Bar::value('X-Api-Key', 'key123'));
		$this->assertStringNotContainsString('Bearer abc', UB_DebugTest_Bar::value('authorization', 'Bearer abc'));

		// Deeper inside a value
		$dump = UB_DebugTest_Bar::value('user', ['name' => 'Ana', 'password' => 'hunter2', 'card' => ['card_number' => '4111']]);

		$this->assertStringContainsString('Ana', $dump);
		$this->assertStringNotContainsString('hunter2', $dump);
		$this->assertStringNotContainsString('4111', $dump);

		// An ordinary value is shown
		$this->assertStringContainsString('3', UB_DebugTest_Bar::value('page', 3));
	}

	public function test_request_panel_hides_secrets(): void
	{
		$request = Request::factory('welcome/index');
		$request->route(Route::get('default'));
		$request->query(['page' => '2', 'access_token' => 'tok_SECRET']);
		$request->post(['email' => 'ana@example.com', 'password' => 'hunter2']);
		$request->headers('authorization', 'Bearer SECRET_BEARER');
		$request->headers('x-api-key', 'SECRET_KEY');
		$request->headers('accept', 'text/html');

		Request::$initial = $request;

		$cookies = $_COOKIE;
		$_COOKIE = ['session' => 'SECRET_SESSION_ID', 'theme' => 'dark-theme-value'];

		try
		{
			Kohana::$config->load('ub_debug')->set('panels', ['request' => true]);

			$html = UB_DebugTest_Bar::render();
		}
		finally
		{
			$_COOKIE = $cookies;
		}

		foreach (['tok_SECRET', 'hunter2', 'SECRET_BEARER', 'SECRET_KEY', 'SECRET_SESSION_ID', 'dark-theme-value'] as $secret)
		{
			$this->assertStringNotContainsString($secret, $html);
		}

		// What is not secret is there: names of cookies, plain input, plain headers
		$this->assertStringContainsString('ana@example.com', $html);
		$this->assertStringContainsString('text/html', $html);
		$this->assertStringContainsString('theme', $html);
		$this->assertStringContainsString('17 characters', $html);
	}

	public function test_text_values_in_sql_are_hidden(): void
	{
		$sql = "SELECT * FROM users WHERE email = 'ana@example.com' AND name = 'O''Brien' AND note = 'a \\' b' AND id = 5";

		$this->assertSame("SELECT * FROM users WHERE email = '***' AND name = '***' AND note = '***' AND id = 5", UB_DebugTest_Bar::sql($sql));

		// A prepared statement holds no values
		$this->assertSame('SELECT * FROM users WHERE email = ?', UB_DebugTest_Bar::sql('SELECT * FROM users WHERE email = ?'));

		Kohana::$config->load('ub_debug')->set('mask_sql', false);
		$this->assertSame($sql, UB_DebugTest_Bar::sql($sql));
	}

	public function test_database_panel_counts_repeated_queries(): void
	{
		foreach (['SELECT 1', 'SELECT 2', 'SELECT 2', 'SELECT 2'] as $sql)
		{
			Profiler::stop(Profiler::start('Database (ub_debug_test)', $sql));
		}

		$data = UB_DebugTest_Bar::panels()['database']['data'];
		$rows = array_column($data['connections']['database (ub_debug_test)'], null, 'sql');

		$this->assertFalse($rows['SELECT 1']['repeated']);
		$this->assertTrue($rows['SELECT 2']['repeated']);
		$this->assertSame(3, $rows['SELECT 2']['count']);
		$this->assertGreaterThanOrEqual(3, $data['duplicates']);
	}

	public function test_database_panel_tells_where_a_query_ran(): void
	{
		foreach ([10, 10, 25] as $line)
		{
			Profiler::stop(Profiler::start('Database (ub_debug_where)', 'SELECT 9', APPPATH.'classes/Controller/Shop.php', $line));
		}

		$rows = UB_DebugTest_Bar::panels()['database']['data']['connections']['database (ub_debug_where)'];

		$this->assertSame(['APPPATH/classes/Controller/Shop.php [ 10 ]' => 2, 'APPPATH/classes/Controller/Shop.php [ 25 ]' => 1], $rows[0]['callers']);

		Kohana::$config->load('ub_debug')->set('panels', ['database' => true]);

		$this->assertStringContainsString('APPPATH/classes/Controller/Shop.php [ 10 ] × 2', UB_DebugTest_Bar::render());
	}

	public function test_database_panel_shows_rows(): void
	{
		foreach ([3, 4] as $rows)
		{
			$token = Profiler::start('Database (ub_debug_rows)', 'SELECT 7');
			Profiler::stop($token);
			Profiler::note($token, $rows.' rows');
		}

		$row = UB_DebugTest_Bar::panels()['database']['data']['connections']['database (ub_debug_rows)'][0];

		$this->assertSame(7, $row['rows']);
	}

	public function test_files_panel_lists_what_was_not_found(): void
	{
		foreach (['found', 'missing', 'missing'] as $kind)
		{
			$token = Profiler::start('Find file', 'views/ub_debug_test_'.$kind.'.php');
			Profiler::stop($token);
			Profiler::note($token, ($kind === 'missing') ? 'not found' : APPPATH.'views/ub_debug_test_found.php');
		}

		$panel = UB_DebugTest_Bar::panels()['files'];

		$this->assertSame(2, $panel['data']['missing']['views/ub_debug_test_missing.php']);
		$this->assertArrayNotHasKey('views/ub_debug_test_found.php', $panel['data']['missing']);
		$this->assertStringContainsString('not found', $panel['badge']);

		// Loaded files are grouped by where they come from
		$this->assertArrayHasKey('System', $panel['data']['groups']);
		$this->assertArrayHasKey('Module ub_debug', $panel['data']['groups']);
		$this->assertContains('SYSPATH/classes/Kohana/Core.php', $panel['data']['groups']['System']);

		Kohana::$config->load('ub_debug')->set('panels', ['files' => true]);

		$this->assertStringContainsString('views/ub_debug_test_missing.php', UB_DebugTest_Bar::render());
	}

	public function test_timeline_shows_notes_and_a_filter(): void
	{
		Kohana::$config->load('ub_debug')->set('timeline_shortest', 0);
		Kohana::$config->load('ub_debug')->set('timeline_off', ['ub_debug hidden']);

		foreach (['UB_Debug shown' => 'first note', 'UB_Debug hidden' => 'second note'] as $group => $note)
		{
			$token = Profiler::start($group, 'step');
			Profiler::stop($token);
			Profiler::note($token, $note);
		}

		Kohana::$config->load('ub_debug')->set('panels', ['timeline' => true]);

		$data = UB_DebugTest_Bar::panels()['timeline']['data'];

		$this->assertGreaterThanOrEqual(1, $data['groups']['ub_debug shown']);
		$this->assertContains('first note', array_column($data['rows'], 'note'));

		$html = UB_DebugTest_Bar::render();

		$this->assertStringContainsString('data-k-db-group="ub_debug shown" aria-pressed="true"', $html);
		$this->assertStringContainsString('data-k-db-group="ub_debug hidden" aria-pressed="false"', $html);
		$this->assertStringContainsString('<tr data-k-db-row="ub_debug hidden" hidden>', $html);
		$this->assertStringContainsString('second note', $html);
	}

	public function test_views_panel_shows_names_of_variables(): void
	{
		View::factory('kohana/error_production', ['title' => 'SECRET_TITLE', 'status' => 500, 'reference' => 'abc12345'])->render();

		Kohana::$config->load('ub_debug')->set('panels', ['views' => true]);

		$html = UB_DebugTest_Bar::render();

		// The names of the first time the view was rendered in this run
		$this->assertStringContainsString('$title', $html);
		$this->assertStringContainsString('error_production', $html);
		$this->assertStringNotContainsString('SECRET_TITLE', $html);
	}

	public function test_cache_panel_counts_what_was_found(): void
	{
		Kohana::$config->load('cache')->set('ub_debug_test', [
			'driver'         => 'file',
			'cache_dir'      => APPPATH.'cache',
			'default_expire' => 60,
		]);

		$cache = Cache::instance('ub_debug_test');

		$stats = Cache::$stats;
		$reads = Cache::$reads;

		Cache::$stats = ['hits' => 0, 'misses' => 0];
		Cache::$reads = [];

		try
		{
			$cache->set('ub_debug_test_id', 'value', 60);
			$cache->get('ub_debug_test_id');
			$cache->get('ub_debug_test_id');
			$cache->get('ub_debug_test_missing');

			$panel = UB_DebugTest_Bar::panels()['cache'];

			$this->assertSame('2 / 3', $panel['badge']);
			$this->assertSame(2, $panel['data']['hits']);
			$this->assertSame(1, $panel['data']['misses']);

			$rows = array_column($panel['data']['rows'], null, 'id');

			$this->assertSame(2, $rows['ub_debug_test_id']['hits']);
			$this->assertSame(1, $rows['ub_debug_test_missing']['misses']);
		}
		finally
		{
			$cache->delete('ub_debug_test_id');

			Cache::$stats = $stats;
			Cache::$reads = $reads;
		}
	}

	public function test_config_panel_shows_only_listed_groups(): void
	{
		Kohana::$config->load('ub_debug')->set('panels', ['config' => true]);
		Kohana::$config->load('ub_debug')->set('config_groups', []);

		$this->assertSame([], UB_DebugTest_Bar::panels()['config']['data']['groups']);

		Kohana::$config->load('ub_debug')->set('config_groups', ['ub_debug']);

		$this->assertSame(['ub_debug'], array_keys(UB_DebugTest_Bar::panels()['config']['data']['groups']));
	}

	public function test_server_panel_shows_no_environment_variables(): void
	{
		putenv('UB_DEBUG_TEST_SECRET=env_SECRET_value');
		$_SERVER['UB_DEBUG_TEST_SECRET'] = 'env_SECRET_value';

		try
		{
			Kohana::$config->load('ub_debug')->set('panels', ['server' => true]);

			$this->assertStringNotContainsString('env_SECRET_value', UB_DebugTest_Bar::render());
		}
		finally
		{
			putenv('UB_DEBUG_TEST_SECRET');
			unset($_SERVER['UB_DEBUG_TEST_SECRET']);
		}
	}

	public function test_custom_panel_shows_data_of_the_profiler(): void
	{
		Profiler::add('UB_Debug cart', ['items' => 3, 'api_key' => 'key_SECRET']);

		Kohana::$config->load('ub_debug')->set('panels', ['custom' => true]);

		$html = UB_DebugTest_Bar::render();

		$this->assertStringContainsString('UB_Debug cart', $html);
		$this->assertStringContainsString('items', $html);
		$this->assertStringNotContainsString('key_SECRET', $html);
	}

	public function test_server_timing_header_value(): void
	{
		$this->assertMatchesRegularExpression('/^total;dur=[\d.]+, db;dur=[\d.]+;desc="\d+ queries", memory;desc="[\d.]+ MB"$/', UB_DebugTest_Bar::server_timing());
	}

	public function test_log_messages_are_collected(): void
	{
		$before = count(UB_Debug_Log::$messages);

		(new UB_Debug_Log)->write([['level' => Log::WARNING, 'body' => 'Stock is low', 'file' => __FILE__, 'line' => 10]]);

		$message = UB_Debug_Log::$messages[$before];

		$this->assertSame('WARNING', $message['level']);
		$this->assertSame('Stock is low', $message['body']);

		array_pop(UB_Debug_Log::$messages);
	}
}

/**
 * The bar, without the check for the command line.
 */
class UB_DebugTest_Bar extends UB_Debug
{
	public static function enabled(): bool
	{
		if (Kohana::$environment !== Kohana::DEVELOPMENT OR static::$_disabled)
			return false;

		return (bool) Kohana::$config->load('ub_debug')->get('enabled', true);
	}

	public static function reset(): void
	{
		static::$_disabled = false;
	}
}
