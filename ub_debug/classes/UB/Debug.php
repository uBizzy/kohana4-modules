<?php
/**
 * The debug bar: a bar at the bottom of every HTML page that shows what
 * the request did. Time, memory, database queries, views, the route, the
 * session, log messages and more, each in a panel that opens on a click.
 *
 * Enable the module and it works; there is nothing to call:
 *
 *     // application/bootstrap.php
 *     Kohana::modules([
 *         'ub_debug' => MODPATH.'ub_debug',
 *     ]);
 *
 * [!!] The bar is only shown in the DEVELOPMENT environment. This is
 * fixed in [UB_Debug::enabled] and cannot be changed by a setting.
 *
 * Values with a secret name (password, token, key, salt...) are shown as
 * "***", cookie values are never shown, and nothing of the server's
 * environment variables is shown. See [UB_Debug::value].
 *
 * Which panels are shown is set in config/ub_debug.php. Data of your own
 * is added with [Profiler::add].
 *
 * @package    UB_Debug
 * @category   Base
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Debug {

	/**
	 * @var  bool  Switched off for this request by [UB_Debug::disable]?
	 */
	protected static bool $_disabled = false;

	/**
	 * @var  int|null  Level of the output buffer that holds the page, null when the bar was not started
	 */
	protected static ?int $_buffer = null;

	/**
	 * Tests if the bar is shown for this request.
	 *
	 *     if (UB_Debug::enabled()) ...
	 *
	 * @return  bool
	 */
	public static function enabled(): bool
	{
		// Never outside development, whatever the settings say
		if (Kohana::$environment !== Kohana::DEVELOPMENT)
			return false;

		// The command line has no page to put a bar on
		if (PHP_SAPI === 'cli' OR static::$_disabled)
			return false;

		return (bool) static::_setting('enabled', true);
	}

	/**
	 * A setting of the ub_debug config file.
	 *
	 * @param   string  $key      name of the setting
	 * @param   mixed   $default  value when it is not set
	 * @return  mixed
	 */
	protected static function _setting(string $key, mixed $default = null): mixed
	{
		return Kohana::$config->load('ub_debug.'.$key) ?? $default;
	}

	/**
	 * Switches the bar off for this request, for a page that must be
	 * sent exactly as it is.
	 *
	 *     UB_Debug::disable();
	 *
	 * @return  void
	 */
	public static function disable(): void
	{
		static::$_disabled = true;
	}

	/**
	 * Starts collecting: called once, when the module is loaded.
	 *
	 * From here on the page is kept in an output buffer, so the bar can
	 * be put before its closing "body" tag at the end of the request.
	 *
	 * @return  void
	 */
	public static function start(): void
	{
		if (static::$_buffer !== null OR ! static::enabled())
			return;

		// Everything the bar shows is measured by the profiler
		Kohana::$profiling = true;

		// Notices get their own panel in the bar
		Kohana::$show_notices = false;

		if (is_object(Kohana::$log))
		{
			// Keep the log messages of this request for the "Logs" panel
			Kohana::$log->attach(new UB_Debug_Log);
		}

		ob_start();

		static::$_buffer = ob_get_level();

		register_shutdown_function([UB_Debug::class, 'finish']);
	}

	/**
	 * Ends the request: sends the page, with the bar in it when the page
	 * is one a bar belongs on. Called at shutdown.
	 *
	 * @return  void
	 */
	public static function finish(): void
	{
		// When the buffer is gone the response was sent in another way
		// (a download, for example): there is nothing left to do
		if (static::$_buffer === null OR ob_get_level() < static::$_buffer)
			return;

		while (ob_get_level() > static::$_buffer)
		{
			// Buffers that were left open inside the page
			ob_end_flush();
		}

		$page = (string) ob_get_clean();

		static::$_buffer = null;

		try
		{
			$page = static::inject($page);
		}
		catch (Throwable $e)
		{
			// The bar is a help for the developer: it must never break the page
			is_object(Kohana::$log) AND Kohana::$log->add(Log::ERROR, 'Debug bar: '.Kohana_Exception::text($e));
		}

		echo $page;
	}

	/**
	 * Returns the page with the bar put in, or the page as it is when no
	 * bar belongs on it: a response that is not HTML, a redirect, an AJAX
	 * request, a piece of HTML without a "body" tag.
	 *
	 * Every response gets a "Server-Timing" header with the main numbers,
	 * which the developer tools of the browser show, and which the "AJAX"
	 * panel reads for background requests.
	 *
	 * @param   string  $page  the output of the request
	 * @return  string
	 */
	public static function inject(string $page): string
	{
		if ( ! static::enabled() OR static::_excluded_route())
			return $page;

		if ( ! headers_sent())
		{
			header('Server-Timing: '.static::server_timing());
		}

		// A redirect has no page; an AJAX request wants only its own data
		if (http_response_code() >= 300 AND http_response_code() < 400)
			return $page;

		if (Request::initial() !== null AND Request::initial()->is_ajax())
			return $page;

		foreach (headers_list() as $header)
		{
			// A response that says it is something other than HTML is left alone
			if (stripos($header, 'content-type:') === 0 AND stripos($header, 'text/html') === false)
				return $page;
		}

		$position = strripos($page, '</body>');

		// No closing tag: not a whole page
		if ($position === false)
			return $page;

		if ( ! headers_sent())
		{
			// The page is longer now
			header_remove('Content-Length');
		}

		return substr_replace($page, static::render(), $position, 0);
	}

	/**
	 * Returns the HTML of the bar with all its panels.
	 *
	 *     echo UB_Debug::render();
	 *
	 * @return  string
	 */
	public static function render(): string
	{
		return View::factory('ub_debug/bar', [
			'summary' => static::summary(),
			'panels'  => static::panels(),
		])->render();
	}

	/**
	 * Returns the value of the "Server-Timing" header: total time, time
	 * in the database with the number of queries, and memory.
	 *
	 * @return  string
	 */
	public static function server_timing(): string
	{
		$summary = Profiler::summary();

		return sprintf('total;dur=%.1f, db;dur=%.1f;desc="%d queries", memory;desc="%.1f MB"',
			$summary['time'] * 1000,
			$summary['query_time'] * 1000,
			$summary['queries'],
			$summary['memory'] / 1048576
		);
	}

	/**
	 * Returns the numbers and names that are always visible on the bar.
	 *
	 * @return  array<string, bool|float|int|string>
	 */
	public static function summary(): array
	{
		$request = Request::initial();
		$route   = ($request !== null) ? $request->route() : null;

		return Profiler::summary() + [
			'status'     => http_response_code() ?: 200,
			'method'     => is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET',
			'route'      => ($route !== null) ? (string) Route::name($route) : '',
			'controller' => ($route !== null) ? trim($request->directory().'/'.$request->controller(), '/') : '',
			'action'     => ($route !== null) ? (string) $request->action() : '',
			'php'        => PHP_VERSION,
			'kohana'     => Kohana::VERSION,
		];
	}

	/**
	 * Returns the data of every panel that is switched on in
	 * config/ub_debug.php, in that order.
	 *
	 * A panel "name" is made by the method `_panel_name()` and drawn by
	 * the view `ub_debug/panels/name`. To add a panel of your own, add
	 * both in your application and list the name in the config file.
	 *
	 * @return  array<string, array<string, mixed>>  by panel name: "title", "badge" (a short text beside the title) and "data"
	 */
	public static function panels(): array
	{
		$panels = [];

		foreach ( (array) static::_setting('panels', []) as $name => $enabled)
		{
			$method = '_panel_'.$name;

			$callback = [static::class, $method];

			if ( ! $enabled OR ! is_callable($callback))
				continue;

			try
			{
				$panel = call_user_func($callback);
			}
			catch (Throwable $e)
			{
				// One panel that fails must not take the others with it
				$panel = ['title' => ucfirst($name), 'badge' => '!', 'data' => ['error' => $e->getMessage()]];
			}

			if (is_array($panel))
			{
				/** @var array<string, mixed> $panel */
				$panels[ (string) $name] = $panel;
			}
		}

		return $panels;
	}

	/**
	 * Returns the HTML of a value for a panel. A value whose name marks
	 * it as secret is shown as "***", and so are secret values deeper
	 * inside arrays and objects (see [Debug::$sensitive]).
	 *
	 *     echo UB_Debug::value('password', 'abc');   // hidden ***
	 *     echo UB_Debug::value('page', 3);           // int 3
	 *
	 * @param   mixed  $name   name the value is known under
	 * @param   mixed  $value  the value
	 * @return  string
	 */
	public static function value(mixed $name, mixed $value): string
	{
		// Header names are written with "-", the list of secret names with "_"
		$name = is_string($name) ? str_replace('-', '_', $name) : $name;

		return Debug::dump_named($name, $value, 4);
	}

	/**
	 * Returns a SQL query without the text values written in it, when
	 * "mask_sql" is on: 'ana@example.com' becomes '***'.
	 *
	 * @param   string  $sql
	 * @return  string
	 */
	public static function sql(string $sql): string
	{
		if ( ! static::_setting('mask_sql', true))
			return $sql;

		// Text between single quotes; a quote inside is written '' or \'
		return (string) preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/s", "'***'", $sql);
	}

	/**
	 * Tests if the page belongs to a route that gets no bar.
	 *
	 * @return  bool
	 */
	protected static function _excluded_route(): bool
	{
		$request = Request::initial();

		if ($request === null OR $request->route() === null)
			return false;

		return in_array(Route::name($request->route()), (array) static::_setting('exclude_routes', []), true);
	}

	/**
	 * Timeline: every benchmark of the profiler, by the moment it started.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_timeline(): ?array
	{
		$total = max(microtime(true) - KOHANA_START_TIME, 0.000001);
		$rows  = [];

		// Benchmarks shorter than this (in ms) would only crowd the list
		$shortest = static::_setting('timeline_shortest');
		$shortest = is_numeric($shortest) ? (float) $shortest : 0.05;
		$hidden   = 0;

		// The groups that are listed, with the number of their benchmarks: for the filter
		$groups = [];

		foreach (Profiler::marks() as $mark)
		{
			if ($mark['stop_time'] === false)
				continue;

			if (($mark['stop_time'] - $mark['start_time']) * 1000 < $shortest)
			{
				$hidden++;
				continue;
			}

			$groups[$mark['group']] = ($groups[$mark['group']] ?? 0) + 1;

			$rows[] = [
				'group'    => $mark['group'],
				'name'     => str_starts_with($mark['group'], 'database') ? static::sql($mark['name']) : $mark['name'],
				'note'     => static::_note($mark),
				'start'    => ($mark['start_time'] - KOHANA_START_TIME) * 1000,
				'duration' => ($mark['stop_time'] - $mark['start_time']) * 1000,

				// Position and length of the bar, in percent of the request
				'left'     => max(0, min(100, ($mark['start_time'] - KOHANA_START_TIME) / $total * 100)),
				'width'    => max(0.3, min(100, ($mark['stop_time'] - $mark['start_time']) / $total * 100)),
			];
		}

		return [
			'title' => 'Timeline',
			'badge' => number_format($total * 1000).' ms',
			'data'  => [
				'rows'     => $rows,
				'total'    => $total * 1000,
				'hidden'   => $hidden,
				'shortest' => $shortest,
				'groups'   => $groups,
				'off'      => (array) static::_setting('timeline_off', []),
			],
		];
	}

	/**
	 * Database: the queries of every connection, the repeated ones marked.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_database(): ?array
	{
		$limit = static::_setting('duplicate_queries');
		$limit = max(2, is_numeric($limit) ? (int) $limit : 2);

		$connections = [];
		$count = $duplicates = 0;
		$time  = 0.0;
		$marks = Profiler::marks();

		foreach (Profiler::groups() as $group => $queries)
		{
			if ( ! str_starts_with($group, 'database'))
				continue;

			$rows = [];

			foreach ($queries as $sql => $tokens)
			{
				$spent = 0.0;

				// The places in the code that ran this query, with how often
				$callers = [];

				// Rows returned or changed by all runs of this query together
				$found = 0;

				foreach ($tokens as $token)
				{
					$spent += Profiler::total($token)[0];
					$found += (int) ($marks[$token]['note'] ?? 0);

					if ( ! empty($marks[$token]['file']))
					{
						$place = Debug::path($marks[$token]['file']).' [ '.$marks[$token]['line'].' ]';

						$callers[$place] = ($callers[$place] ?? 0) + 1;
					}
				}

				$repeated = (count($tokens) >= $limit);

				$rows[] = [
					'sql'      => static::sql( (string) $sql),
					'count'    => count($tokens),
					'time'     => $spent * 1000,
					'repeated' => $repeated,
					'rows'     => $found,
					'callers'  => $callers,
				];

				$count += count($tokens);
				$time  += $spent;

				if ($repeated)
				{
					$duplicates += count($tokens);
				}
			}

			$connections[$group] = $rows;
		}

		return [
			'title' => 'Database',
			'badge' => $count.($duplicates ? ' ('.$duplicates.' repeated)' : ''),
			'data'  => ['connections' => $connections, 'count' => $count, 'time' => $time * 1000, 'duplicates' => $duplicates],
		];
	}

	/**
	 * Cache: what was found in the cache and what was not. Only there
	 * when the request used the Cache module.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_cache(): ?array
	{
		// Not loaded: the request did not touch the cache
		if ( ! class_exists('Cache', false))
			return null;

		$rows = [];

		foreach (Cache::$reads as [$driver, $id, $found])
		{
			// The same id of the same driver on one row
			$key = $driver.' '.$id;

			$rows[$key] ??= ['driver' => $driver, 'id' => $id, 'hits' => 0, 'misses' => 0];
			$rows[$key][$found ? 'hits' : 'misses']++;
		}

		return [
			'title' => 'Cache',
			'badge' => Cache::$stats['hits'].' / '.(Cache::$stats['hits'] + Cache::$stats['misses']),
			'data'  => ['rows' => array_values($rows), 'hits' => Cache::$stats['hits'], 'misses' => Cache::$stats['misses']],
		];
	}

	/**
	 * Request: route, controller, parameters, input, headers and cookies.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_request(): ?array
	{
		$request = Request::initial();

		if ($request === null)
			return null;

		$summary = static::summary();

		// Only the names of the cookies and the length of their values:
		// a cookie value is a session or a login
		$cookies = [];

		foreach ($_COOKIE as $name => $value)
		{
			$cookies[$name] = is_string($value) ? strlen($value).' characters' : 'array';
		}

		$response = [];

		foreach (headers_list() as $header)
		{
			[$name, $value] = array_map('trim', explode(':', $header, 2)) + [1 => ''];

			$response[$name] = $value;
		}

		return [
			'title' => 'Request',
			'badge' => $summary['method'].' '.$summary['status'],
			'data'  => [
				'route' => [
					'Method'     => $summary['method'],
					'URI'        => $request->uri(),
					'Status'     => $summary['status'],
					'Route'      => $summary['route'],
					'Routing'    => implode('; ', array_column(array_filter(Profiler::marks(), fn ($mark) => $mark['group'] === 'routing'), 'note')),
					'Controller' => $summary['controller'],
					'Action'     => $summary['action'],
				],
				'params'           => (array) $request->param(),
				'get'              => (array) $request->query(),
				'post'             => (array) $request->post(),
				'cookies'          => $cookies,
				'request_headers'  => $request->headers()->getArrayCopy(),
				'response_headers' => $response,
			],
		];
	}

	/**
	 * Views: every view that was rendered, how often and for how long.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_views(): ?array
	{
		$rows = [];
		$count = 0;

		foreach (Profiler::groups()['views'] ?? [] as $file => $tokens)
		{
			$time = 0.0;

			foreach ($tokens as $token)
			{
				$time += Profiler::total($token)[0];
			}

			$rows[] = [
				'file'      => $file,
				'count'     => count($tokens),
				'time'      => $time * 1000,

				// Names of the variables of the first time it was rendered
				'variables' => (string) (Profiler::marks()[$tokens[0]]['note'] ?? ''),
			];

			$count += count($tokens);
		}

		return ['title' => 'Views', 'badge' => (string) $count, 'data' => ['rows' => $rows]];
	}

	/**
	 * Session: the data of every session that was opened in this request.
	 * A request that used no session shows none, and opens none.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_session(): ?array
	{
		$sessions = [];

		foreach (Session::$instances as $type => $session)
		{
			$sessions[$type] = $session->as_array();
		}

		return [
			'title' => 'Session',
			'badge' => (string) array_sum(array_map('count', $sessions)),
			'data'  => ['sessions' => $sessions],
		];
	}

	/**
	 * Logs: the log messages and the notices (deprecations) of the request.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_logs(): ?array
	{
		if (is_object(Kohana::$log))
		{
			// Hand the messages that are still waiting to the writers
			Kohana::$log->write();
		}

		$rows = UB_Debug_Log::$messages;

		foreach (Kohana::$notices as $notice)
		{
			$rows[] = ['level' => $notice['code'], 'body' => $notice['message'], 'file' => $notice['file'], 'line' => $notice['line']];
		}

		return ['title' => 'Logs', 'badge' => (string) count($rows), 'data' => ['rows' => $rows]];
	}

	/**
	 * Routes: every route in the order they are tried, the matched one marked.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_routes(): ?array
	{
		$request = Request::initial();
		$current = ($request !== null AND $request->route() !== null) ? Route::name($request->route()) : null;

		// The pattern of a route is kept in a protected property
		$uri = new ReflectionProperty('Route', '_uri');

		$rows = [];

		foreach (Route::all() as $name => $route)
		{
			$rows[] = ['name' => $name, 'uri' => is_string($pattern = $uri->getValue($route)) ? $pattern : '', 'current' => ($name === $current)];
		}

		return ['title' => 'Routes', 'badge' => (string) count($rows), 'data' => ['rows' => $rows]];
	}

	/**
	 * Files: the PHP files that were loaded, grouped by where they come
	 * from; the classes that were loaded, in order; and the files that
	 * were looked for but do not exist.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_files(): ?array
	{
		$groups = [];

		foreach (get_included_files() as $file)
		{
			$groups[static::_origin($file)][] = Debug::path($file);
		}

		foreach ($groups as & $files)
		{
			sort($files);
		}

		unset($files);

		$classes = $missing = [];

		foreach (Profiler::marks() as $mark)
		{
			if ($mark['group'] === 'classes')
			{
				$classes[] = ['class' => $mark['name'], 'file' => Debug::path($mark['note']), 'origin' => static::_origin($mark['note'])];
			}
			elseif ($mark['group'] === 'find file' AND $mark['note'] === 'not found')
			{
				// How often each missing file was looked for
				$missing[$mark['name']] = ($missing[$mark['name']] ?? 0) + 1;
			}
		}

		arsort($missing);

		return [
			'title' => 'Files',
			'badge' => count(get_included_files()).($missing ? ' ('.count($missing).' not found)' : ''),
			'data'  => ['groups' => $groups, 'classes' => $classes, 'missing' => $missing],
		];
	}

	/**
	 * Tells where a file comes from: the application, a module (with its
	 * name), the system, or a library in "vendor".
	 *
	 * @param   string  $file  full path
	 * @return  string
	 */
	protected static function _origin(string $file): string
	{
		if (str_starts_with($file, APPPATH))
			return 'Application';

		foreach (Kohana::modules() as $name => $path)
		{
			if (str_starts_with($file, $path))
				return 'Module '.$name;
		}

		if (str_starts_with($file, SYSPATH))
			return 'System';

		return str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR) ? 'Vendor' : 'Other';
	}

	/**
	 * Returns the note of a benchmark for the timeline. Notes that are
	 * paths of files are shortened.
	 *
	 * @param   array<string, mixed>  $mark  a benchmark of [Profiler::marks]
	 * @return  string
	 */
	protected static function _note(array $mark): string
	{
		$note = is_string($mark['note'] ?? null) ? $mark['note'] : '';

		if (in_array($mark['group'], ['find file', 'classes'], true) AND $note !== 'not found')
		{
			$note = implode(', ', array_map([Debug::class, 'path'], explode(', ', $note)));
		}

		return $note;
	}

	/**
	 * Modules: the enabled modules, in the order they are searched.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_modules(): ?array
	{
		$modules = array_map([Debug::class, 'path'], Kohana::modules());

		return ['title' => 'Modules', 'badge' => (string) count($modules), 'data' => ['modules' => $modules]];
	}

	/**
	 * Server: versions, limits and extensions. No environment variables
	 * and no phpinfo(): they hold passwords and keys.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_server(): ?array
	{
		$extensions = get_loaded_extensions();

		natcasesort($extensions);

		return [
			'title' => 'Server',
			'badge' => 'PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
			'data'  => [
				'values' => [
					'Kohana'              => Kohana::version(),
					'Environment'         => 'development',
					'PHP'                 => PHP_VERSION,
					'Server API'          => PHP_SAPI,
					'Operating system'    => PHP_OS_FAMILY,
					'Time zone'           => date_default_timezone_get(),
					'Memory limit'        => ini_get('memory_limit'),
					'Max execution time'  => ini_get('max_execution_time').' s',
					'Largest upload'      => ini_get('upload_max_filesize'),
					'Largest POST'        => ini_get('post_max_size'),
					'OPcache'             => (function_exists('opcache_get_status') AND ini_get('opcache.enable')) ? 'on' : 'off',
				],
				'extensions' => array_values($extensions),
			],
		];
	}

	/**
	 * AJAX: filled in by the browser, which lists the background requests
	 * of the page as they happen.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_ajax(): ?array
	{
		return ['title' => 'AJAX', 'badge' => '0', 'data' => []];
	}

	/**
	 * Custom: the data added with [Profiler::add].
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_custom(): ?array
	{
		$data = Profiler::data();

		// Without data there is nothing to show
		if ( ! $data)
			return null;

		return ['title' => 'Custom', 'badge' => (string) count($data), 'data' => ['items' => $data]];
	}

	/**
	 * Config: only the groups listed in "config_groups". Values with a
	 * secret name are hidden when they are shown.
	 *
	 * @return  array<string, mixed>|null
	 */
	protected static function _panel_config(): ?array
	{
		$groups = [];

		foreach ( (array) static::_setting('config_groups', []) as $group)
		{
			$config = is_string($group) ? Kohana::$config->load($group) : null;

			if (is_string($group) AND $config instanceof Config_Group)
			{
				$groups[$group] = $config->as_array();
			}
		}

		return ['title' => 'Config', 'badge' => (string) count($groups), 'data' => ['groups' => $groups]];
	}

}
