<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the task that writes the migration of the settings table into
 * the application. What the migration itself does is tested by
 * SettingsTest.
 *
 * @package    UB_Settings
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.settings')]
class UB_SettingsMigrationTaskTest extends Unittest_TestCase
{
	/**
	 * @var string  The directory the task writes to
	 */
	protected $directory;

	/**
	 * @var bool  Was that directory there before the test?
	 */
	protected $existed;

	/**
	 * @var resource  Takes what the task prints
	 */
	protected $output;

	public function setUp(): void
	{
		parent::setUp();

		$this->directory = APPPATH.'migrations';
		$this->existed   = is_dir($this->directory);
		$this->output    = fopen('php://memory', 'w+');

		if ($this->files())
		{
			$this->markTestSkipped('The application already has the migration of the settings table.');
		}

		Minion_CLI::$stdout = $this->output;
	}

	/**
	 * Removes what the task wrote.
	 */
	public function tearDown(): void
	{
		Minion_CLI::$stdout = null;

		array_map('unlink', $this->files());

		if ( ! $this->existed AND is_dir($this->directory))
		{
			rmdir($this->directory);
		}

		parent::tearDown();
	}

	/**
	 * The migrations of the settings table in the application.
	 *
	 * @return array<int, string>
	 */
	protected function files(): array
	{
		return glob($this->directory.DIRECTORY_SEPARATOR.'*_create_settings_table.php') ?: [];
	}

	public function test_task_writes_the_migration_once(): void
	{
		Minion_Task::factory(['task' => 'settings:migration'])->execute();

		$files = $this->files();

		$this->assertCount(1, $files);
		$this->assertMatchesRegularExpression('/^\d{8}_\d{6}_create_settings_table\.php$/', basename($files[0]));
		$this->assertFileEquals(dirname(__DIR__).'/ub_settings/create_settings_table.php', $files[0]);
		$this->assertInstanceOf(Migration::class, Kohana::load($files[0]));

		rewind($this->output);
		$this->assertStringContainsString('Created', stream_get_contents($this->output));

		// A second copy would fail on a table that exists: it is refused
		try
		{
			Minion_Task::factory(['task' => 'settings:migration'])->execute();
			$this->fail('The migration must not be written twice');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('is already there', $e->getMessage());
		}

		$this->assertCount(1, $this->files());
	}
}
