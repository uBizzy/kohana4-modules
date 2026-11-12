<?php
/**
 * Settings that are kept in the database, to be changed while the
 * application runs: the name of the site, how many items a page shows,
 * whether registration is open.
 *
 *     UB_Settings::set('site.name', 'My Site');
 *
 *     echo UB_Settings::get('site.name', 'Untitled');
 *
 * What a setting can be: text, a number, true or false, null, or an
 * array of these. Values are stored as JSON, so they come back with the
 * type they had and no object is ever made from what the table holds.
 *
 * All settings are read with one query, the first time one is asked for
 * in a request. They can be kept in the cache between requests: see
 * config/ub_settings.php.
 *
 * Passwords and keys do not belong here: they stay in the config files,
 * out of reach of whoever can change a setting.
 *
 * @package    UB_Settings
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Settings {

	/**
	 * @var  string  The name of the settings in the cache
	 */
	public const string CACHE = 'ub_settings';

	/**
	 * @var  array<string, mixed>|null  All settings, once they were read
	 */
	protected static ?array $_values = null;

	/**
	 * Returns a setting.
	 *
	 *     $per_page = UB_Settings::get('articles.per_page', 10);
	 *
	 * @param   string  $name     name of the setting
	 * @param   mixed   $default  what to return when it was never set
	 * @return  mixed
	 */
	public static function get(string $name, mixed $default = null): mixed
	{
		$values = static::_values();

		return array_key_exists($name, $values) ? $values[$name] : $default;
	}

	/**
	 * Tells whether a setting was set, to any value.
	 *
	 * @param   string  $name  name of the setting
	 * @return  bool
	 */
	public static function has(string $name): bool
	{
		return array_key_exists($name, static::_values());
	}

	/**
	 * Returns all settings, or those whose names start with something,
	 * in the order of their names.
	 *
	 *     // ['site.name' => 'My Site', 'site.open' => true]
	 *     $site = UB_Settings::all('site.');
	 *
	 * @param   string  $prefix  the start of the names
	 * @return  array<string, mixed>  name => value
	 */
	public static function all(string $prefix = ''): array
	{
		return array_filter(
			static::_values(),
			fn (string $name): bool => str_starts_with($name, $prefix),
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Saves a setting, new or changed.
	 *
	 *     UB_Settings::set('site.open', false);
	 *
	 * @param   string  $name   letters, digits, "_", "." and "-", up to 100
	 * @param   mixed   $value  text, a number, true, false, null, or an array of these
	 * @return  void
	 * @throws  Kohana_Exception  for a name or a value that cannot be stored
	 */
	public static function set(string $name, mixed $value): void
	{
		static::_check_name($name);

		$json  = static::_encode($name, $value);
		$table = static::_table();
		$db    = static::_db();

		$exists = DB::select('name')->from($table)->where('name', '=', $name)->execute($db)->count() > 0;

		if ($exists)
		{
			DB::update($table)->set(['value' => $json])->where('name', '=', $name)->execute($db);
		}
		else
		{
			DB::insert($table, ['name', 'value'])->values([$name, $json])->execute($db);
		}

		static::reload();
	}

	/**
	 * Removes a setting: [UB_Settings::get] returns the default again.
	 *
	 * @param   string  $name  name of the setting
	 * @return  void
	 */
	public static function delete(string $name): void
	{
		DB::delete(static::_table())->where('name', '=', $name)->execute(static::_db());

		static::reload();
	}

	/**
	 * Forgets what was read, in this request and in the cache: the next
	 * call reads the table again. Done by itself after every change made
	 * through this class; call it when the table was changed another way.
	 *
	 * @return  void
	 */
	public static function reload(): void
	{
		static::$_values = null;

		if (static::_cache_life() > 0)
		{
			// A lifetime of 0 deletes the entry
			Kohana::cache(static::CACHE, null, 0);
		}
	}

	/**
	 * Returns all settings, reading them the first time.
	 *
	 * @return  array<string, mixed>  name => value
	 * @throws  Kohana_Exception  when a row does not hold JSON
	 */
	protected static function _values(): array
	{
		if (static::$_values === null)
		{
			$values = [];

			foreach (static::_rows() as $name => $json)
			{
				$values[$name] = static::_decode($name, $json);
			}

			ksort($values);

			static::$_values = $values;
		}

		return static::$_values;
	}

	/**
	 * Returns the rows of the table as they are stored, from the cache
	 * when it is used and has them.
	 *
	 * @return  array<string, string>  name => JSON
	 */
	protected static function _rows(): array
	{
		$life = static::_cache_life();
		$rows = ($life > 0) ? Kohana::cache(static::CACHE, null, $life) : null;

		if ( ! is_array($rows))
		{
			$rows = DB::select('name', 'value')
				->from(static::_table())
				->execute(static::_db())
				->as_array('name', 'value');

			if ($life > 0)
			{
				Kohana::cache(static::CACHE, $rows, $life);
			}
		}

		$texts = [];

		foreach ($rows as $name => $json)
		{
			// The cache is a file or a server that others may write to
			// as well: only text is taken from it
			if (is_string($json))
			{
				$texts[ (string) $name] = $json;
			}
		}

		return $texts;
	}

	/**
	 * Refuses a name that cannot be stored.
	 *
	 * @param   string  $name  name of a setting
	 * @return  void
	 * @throws  Kohana_Exception
	 */
	protected static function _check_name(string $name): void
	{
		if ( ! preg_match('/^[a-zA-Z0-9_.-]{1,100}$/D', $name))
			throw new Kohana_Exception(
				'The name of a setting is made of letters, digits, "_", "." and "-", 100 at most: :name',
				[':name' => $name]
			);
	}

	/**
	 * Turns a value into the JSON that is stored.
	 *
	 * @param   string  $name   name of the setting, for the error text
	 * @param   mixed   $value  the value
	 * @return  string
	 * @throws  Kohana_Exception  for objects, resources and text that is not UTF-8
	 */
	protected static function _encode(string $name, mixed $value): string
	{
		$parts = [$value];

		array_walk_recursive($parts, function (mixed $part) use ($name): void
		{
			if ($part !== null AND ! is_scalar($part))
				throw new Kohana_Exception(
					'The setting :name can hold text, numbers, true, false, null and arrays of these, not :type',
					[':name' => $name, ':type' => get_debug_type($part)]
				);
		});

		try
		{
			// A float stays a float (1.0 is not stored as 1)
			return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
		}
		catch (JsonException $e)
		{
			throw new Kohana_Exception(
				'The setting :name cannot be stored: :error',
				[':name' => $name, ':error' => $e->getMessage()],
				0,
				$e
			);
		}
	}

	/**
	 * Turns stored JSON into the value. Objects of JSON become arrays.
	 *
	 * @param   string  $name  name of the setting, for the error text
	 * @param   string  $json  what the table holds
	 * @return  mixed
	 * @throws  Kohana_Exception  when it is not JSON
	 */
	protected static function _decode(string $name, string $json): mixed
	{
		try
		{
			return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			throw new Kohana_Exception(
				'The setting :name does not hold JSON in the table: :error',
				[':name' => $name, ':error' => $e->getMessage()],
				0,
				$e
			);
		}
	}

	/**
	 * Returns the database of the settings.
	 *
	 * @return  Database
	 */
	protected static function _db(): Database
	{
		$group = Kohana::$config->load('ub_settings.database');

		return Database::instance(is_string($group) ? $group : null);
	}

	/**
	 * Returns the name of the table, without the prefix of the connection.
	 *
	 * @return  string
	 */
	protected static function _table(): string
	{
		$table = Kohana::$config->load('ub_settings.table');

		return (is_string($table) AND $table !== '') ? $table : 'settings';
	}

	/**
	 * Returns for how many seconds the settings stay in the cache;
	 * 0 when the cache is not used.
	 *
	 * @return  int
	 */
	protected static function _cache_life(): int
	{
		$life = Kohana::$config->load('ub_settings.cache');

		return is_int($life) ? max(0, $life) : 0;
	}

}
