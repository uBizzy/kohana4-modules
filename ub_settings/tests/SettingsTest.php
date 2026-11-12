<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the settings against a real database, with the table of the
 * migration that ships with the module.
 *
 * Runs on SQLite in memory, so it needs no server. SettingsMysqlTest
 * and SettingsPgsqlTest run the same tests on those servers.
 *
 * @package    UB_Settings
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.settings')]
class UB_SettingsTest extends Unittest_TestCase
{
	/**
	 * @var string  The database group of these tests
	 */
	protected const string GROUP = 'ub_settings_test';

	/**
	 * @var array<string, mixed>  The config of the module during a test
	 */
	protected array $environmentDefault = [
		'ub_settings.database' => self::GROUP,
		'ub_settings.table'    => 'settings',
		'ub_settings.cache'    => 0,
	];

	/**
	 * @var Database  The database of the settings
	 */
	protected Database $db;

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
	 * The migration of the table, as shipped.
	 */
	protected function migration(): Migration
	{
		return Kohana::load(dirname(__DIR__).'/ub_settings/create_settings_table.php');
	}

	/**
	 * Connects to the test database and makes an empty table.
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
		$this->db->query(null, 'DROP TABLE IF EXISTS '.$this->db->quote_table('settings'));
		$this->migration()->up($this->db);

		UB_Settings::reload();
	}

	/**
	 * Removes the table and the connection.
	 */
	public function tearDown(): void
	{
		if (isset($this->db))
		{
			$this->db->query(null, 'DROP TABLE IF EXISTS '.$this->db->quote_table('settings'));
		}

		$this->disconnect();

		// With the config of the test still in place
		UB_Settings::reload();
		Kohana::cache(UB_Settings::CACHE, null, 0);

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
	 * How many rows the table has.
	 */
	protected function rows(): int
	{
		return DB::select('name')->from('settings')->execute($this->db)->count();
	}

	public function test_a_setting_that_was_never_set_is_the_default(): void
	{
		$this->assertNull(UB_Settings::get('site.name'));
		$this->assertSame('Untitled', UB_Settings::get('site.name', 'Untitled'));
		$this->assertFalse(UB_Settings::has('site.name'));
		$this->assertSame([], UB_Settings::all());
	}

	/**
	 * Values of every kind a setting can be.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function provider_values(): array
	{
		return [
			'text'           => ['My Site'],
			'empty text'     => [''],
			'text of others' => ['Olá, 世界 <b>"quoted"</b> \\ / \' ; DROP TABLE settings'],
			'whole number'   => [42],
			'zero'           => [0],
			'float'          => [1.5],
			'float like int' => [2.0],
			'true'           => [true],
			'false'          => [false],
			'null'           => [null],
			'list'           => [['a', 'b', 3]],
			'map'            => [['from' => 'ana@example.com', 'copies' => ['x', 'y'], 'on' => true]],
			'empty array'    => [[]],
		];
	}

	#[DataProvider('provider_values')]
	public function test_a_value_comes_back_as_it_was_set(mixed $value): void
	{
		UB_Settings::set('some.setting', $value);

		$this->assertSame($value, UB_Settings::get('some.setting', 'the default'));
		$this->assertTrue(UB_Settings::has('some.setting'));

		// Also for a request that reads the table for the first time
		UB_Settings::reload();

		$this->assertSame($value, UB_Settings::get('some.setting', 'the default'));
	}

	public function test_setting_again_changes_the_row(): void
	{
		UB_Settings::set('site.name', 'First');
		UB_Settings::set('site.name', 'Second');
		UB_Settings::set('site.name', 'Second');

		$this->assertSame('Second', UB_Settings::get('site.name'));
		$this->assertSame(1, $this->rows());
	}

	public function test_delete_brings_the_default_back(): void
	{
		UB_Settings::set('site.name', 'First');
		UB_Settings::delete('site.name');
		UB_Settings::delete('never.there');

		$this->assertSame('Untitled', UB_Settings::get('site.name', 'Untitled'));
		$this->assertSame(0, $this->rows());
	}

	public function test_all_returns_them_sorted_and_by_the_start_of_the_name(): void
	{
		UB_Settings::set('site.open', true);
		UB_Settings::set('mail.from', 'ana@example.com');
		UB_Settings::set('site.name', 'My Site');
		UB_Settings::set('sitemap', false);

		$this->assertSame(
			['mail.from' => 'ana@example.com', 'site.name' => 'My Site', 'site.open' => true, 'sitemap' => false],
			UB_Settings::all()
		);
		$this->assertSame(['site.name' => 'My Site', 'site.open' => true], UB_Settings::all('site.'));
		$this->assertSame([], UB_Settings::all('shop.'));
	}

	/**
	 * Names that are refused.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provider_bad_names(): array
	{
		return [
			'empty'       => [''],
			'a space'     => ['site name'],
			'a quote'     => ["site'name"],
			'a line end'  => ["site.name\n"],
			'not ASCII'   => ['sítio'],
			'101 letters' => [str_repeat('a', 101)],
		];
	}

	#[DataProvider('provider_bad_names')]
	public function test_a_bad_name_is_refused(string $name): void
	{
		try
		{
			UB_Settings::set($name, 'value');

			$this->fail('The name was accepted');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('name of a setting', $e->getMessage());
		}

		$this->assertSame(0, $this->rows());
	}

	public function test_a_name_can_be_a_number(): void
	{
		UB_Settings::set('2026', 'the year');
		UB_Settings::reload();

		$this->assertSame('the year', UB_Settings::get('2026'));
		$this->assertTrue(UB_Settings::has('2026'));
		$this->assertSame(['2026' => 'the year'], UB_Settings::all('20'));
	}

	public function test_the_longest_name_is_stored(): void
	{
		$name = str_repeat('a', 100);

		UB_Settings::set($name, 1);
		UB_Settings::reload();

		$this->assertSame(1, UB_Settings::get($name));
	}

	/**
	 * Values that are refused.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function provider_bad_values(): array
	{
		return [
			'an object'           => [new stdClass],
			'an object in a list' => [['fine', ['deeper' => new ArrayObject]]],
			'a function'          => [fn (): int => 1],
			'not UTF-8'           => ["\xff\xfe"],
			'not a number'        => [NAN],
		];
	}

	#[DataProvider('provider_bad_values')]
	public function test_a_value_that_cannot_be_stored_is_refused(mixed $value): void
	{
		UB_Settings::set('some.setting', 'before');

		try
		{
			UB_Settings::set('some.setting', $value);

			$this->fail('The value was accepted');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('some.setting', $e->getMessage());
		}

		$this->assertSame('before', UB_Settings::get('some.setting'));
	}

	public function test_the_table_is_read_once_in_a_request(): void
	{
		UB_Settings::set('site.name', 'First');
		UB_Settings::get('site.name');

		// Changed behind the back of the class
		DB::update('settings')->set(['value' => '"Second"'])->execute($this->db);

		$this->assertSame('First', UB_Settings::get('site.name'));

		UB_Settings::reload();

		$this->assertSame('Second', UB_Settings::get('site.name'));
	}

	public function test_a_row_that_is_not_json_is_an_error_that_names_it(): void
	{
		DB::insert('settings', ['name', 'value'])->values(['broken.one', 'O:8:"stdClass":0:{}'])->execute($this->db);

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('broken.one');

		UB_Settings::get('anything');
	}

	public function test_an_object_of_json_becomes_an_array(): void
	{
		DB::insert('settings', ['name', 'value'])->values(['by.hand', '{"a": {"b": 1}}'])->execute($this->db);

		$this->assertSame(['a' => ['b' => 1]], UB_Settings::get('by.hand'));
	}

	public function test_the_cache_is_used_and_cleared_by_a_change(): void
	{
		Kohana::$config->load('ub_settings')->set('cache', 60);

		UB_Settings::set('site.name', 'First');

		// Reads the table and fills the cache
		$this->assertSame('First', UB_Settings::get('site.name'));
		$this->assertSame(['site.name' => '"First"'], Kohana::cache(UB_Settings::CACHE, null, 60));

		// A new request, after the table changed behind the back of the
		// class: the cache answers
		DB::update('settings')->set(['value' => '"Second"'])->execute($this->db);
		(new ReflectionProperty(UB_Settings::class, '_values'))->setValue(null, null);

		$this->assertSame('First', UB_Settings::get('site.name'));

		// A change through the class clears it
		UB_Settings::set('site.open', true);

		$this->assertNull(Kohana::cache(UB_Settings::CACHE, null, 60));
		$this->assertSame('Second', UB_Settings::get('site.name'));
		$this->assertTrue(UB_Settings::get('site.open'));
	}

	public function test_what_is_not_text_in_the_cache_is_not_used(): void
	{
		Kohana::$config->load('ub_settings')->set('cache', 60);
		Kohana::cache(UB_Settings::CACHE, ['good' => '1', 'bad' => new stdClass, 'worse' => ['x']], 60);

		$this->assertSame(['good' => 1], UB_Settings::all());
	}

	public function test_the_migration_can_be_undone(): void
	{
		$this->migration()->down($this->db);

		$this->assertNotContains('settings', $this->db->list_tables());

		// For tearDown()
		$this->migration()->up($this->db);
	}
}
