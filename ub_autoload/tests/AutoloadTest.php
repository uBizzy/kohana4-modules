<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the loader with small made-up libraries (tests/test_data): the
 * four ways to say where a class is, the vendor directories, and what
 * happens when the config is wrong.
 *
 * A class that was loaded stays loaded, so every test uses classes of
 * its own.
 *
 * @package    UB_Autoload
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.autoload')]
class UB_AutoloadTest extends Unittest_TestCase
{
	/**
	 * @var string  The directory of the made-up libraries
	 */
	protected string $data;

	/**
	 * Empties the loader and gives it the two vendor directories of the tests.
	 */
	public function setUp(): void
	{
		parent::setUp();

		$this->data = __DIR__.DIRECTORY_SEPARATOR.'test_data'.DIRECTORY_SEPARATOR;

		$this->reset([
			$this->data.'vendor'.DIRECTORY_SEPARATOR,
			$this->data.'vendor2'.DIRECTORY_SEPARATOR,
		]);
	}

	/**
	 * Leaves the loader as the module starts it.
	 */
	public function tearDown(): void
	{
		$this->reset(null);

		UB_Autoload::init();

		parent::tearDown();
	}

	/**
	 * Makes the loader forget what it was told.
	 *
	 * @param string[]|null $vendor_paths  the vendor directories; null to look for the real ones
	 */
	protected function reset(?array $vendor_paths): void
	{
		foreach (['_psr4' => [], '_psr0' => [], '_classmap' => [], '_vendor_paths' => $vendor_paths] as $name => $value)
		{
			(new ReflectionProperty(UB_Autoload::class, $name))->setValue(null, $value);
		}
	}

	public function test_the_loader_is_registered_once(): void
	{
		UB_Autoload::init();

		$loaders = count(spl_autoload_functions());

		UB_Autoload::init();

		$this->assertCount($loaders, spl_autoload_functions());
	}

	public function test_psr4(): void
	{
		UB_Autoload::add_psr4('UBTest\\Psr4', 'psr4lib/src');

		$this->assertTrue(class_exists('UBTest\\Psr4\\Deep\\Down\\Thing'));
		$this->assertFalse(class_exists('UBTest\\Psr4\\Not\\There'));

		// A name that only starts like the namespace is not part of it
		$this->assertFalse(class_exists('UBTest\\Psr4Other\\Thing'));
	}

	public function test_the_first_vendor_directory_wins_and_the_others_are_searched_too(): void
	{
		UB_Autoload::add_psr4('\\UBTest\\Psr4\\', ['psr4lib/src']);

		$this->assertSame('hello from the first vendor directory', (new UBTest\Psr4\Greeter)->greet());
		$this->assertTrue(class_exists('UBTest\\Psr4\\OnlyHere'));
	}

	public function test_psr0(): void
	{
		UB_Autoload::add_psr0('UBTestLegacy', 'psr0lib/library/');

		$this->assertTrue(class_exists('UBTestLegacy'));
		$this->assertTrue(class_exists('UBTestLegacy_Some_Part'));
		$this->assertFalse(class_exists('UBTestLegacy_Not_There'));
	}

	public function test_psr0_with_a_namespace(): void
	{
		UB_Autoload::add_psr0('UBTestOld\\', 'psr0lib/library');

		$this->assertTrue(class_exists('UBTestOld\\Space\\Some_Name'));
	}

	public function test_class_map(): void
	{
		UB_Autoload::add_class_map('UBTestSingleHelper', 'single/everything.php');

		$this->assertTrue(class_exists('UBTestSingleHelper'));

		// Its file is included once, even when a second class of it is asked for
		UB_Autoload::add_class_map('\\UBTestSingle', 'single/everything.php');

		$this->assertTrue(UB_Autoload::load('UBTestSingle'));
	}

	public function test_an_absolute_path_needs_no_vendor_directory(): void
	{
		$this->reset([]);

		UB_Autoload::add_psr4('UBTest\\Elsewhere\\', $this->data.'elsewhere/lib');

		$this->assertTrue(class_exists('UBTest\\Elsewhere\\Far'));
	}

	public function test_files_are_included_once(): void
	{
		UB_Autoload::add_file('helpers/functions.php');

		$this->assertSame('helped', ubtest_autoload_helper());

		$count = $GLOBALS['ubtest_autoload_included'];

		// By the same name and by its full path: not included again
		UB_Autoload::add_file('helpers/functions.php');
		UB_Autoload::add_file($this->data.'vendor'.DIRECTORY_SEPARATOR.'helpers/functions.php');

		$this->assertSame($count, $GLOBALS['ubtest_autoload_included']);
	}

	public function test_a_file_that_is_not_there_is_an_error_at_once(): void
	{
		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('the file helpers/missing.php was not found');

		UB_Autoload::add_file('helpers/missing.php');
	}

	public function test_a_directory_that_is_not_there_is_an_error_when_a_class_is_asked_for(): void
	{
		// Nothing is looked for yet
		UB_Autoload::add_psr4('UBTest\\Wrong\\', 'not/there');

		// Other classes are not touched by the mistake
		$this->assertFalse(class_exists('UBTest\\Other\\Thing'));

		try
		{
			class_exists('UBTest\\Wrong\\Thing');

			$this->fail('A directory that is not there was accepted');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('the directory not/there of UBTest\\Wrong\\ was not found', $e->getMessage());
			$this->assertStringContainsString('test_data', $e->getMessage());
		}
	}

	public function test_a_mapped_file_that_is_not_there_is_an_error(): void
	{
		UB_Autoload::add_class_map('UBTestNowhere', 'single/nowhere.php');

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('the file single/nowhere.php of the class UBTestNowhere was not found');

		class_exists('UBTestNowhere');
	}

	/**
	 * Names that are no class names: code may pass on what a visitor sent.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provider_no_class_names(): array
	{
		return [
			'up the tree'       => ['UBTest\\Psr4\\..\\..\\..\\elsewhere\\lib\\Far'],
			'a slash'           => ['UBTest\\Psr4\\Deep/Down/Thing'],
			'a dot'             => ['UBTest\\Psr4\\Greeter.php'],
			'two separators'    => ['UBTest\\Psr4\\\\Greeter'],
			'ends in separator' => ['UBTest\\Psr4\\'],
			'a null byte'       => ["UBTest\\Psr4\\Greeter\0"],
			'a line end'        => ["UBTest\\Psr4\\Greeter\n"],
			'starts with digit' => ['UBTest\\Psr4\\1Greeter'],
			'empty'             => [''],
		];
	}

	#[DataProvider('provider_no_class_names')]
	public function test_what_is_no_class_name_is_not_looked_for(string $name): void
	{
		UB_Autoload::add_psr4('UBTest\\Psr4\\', 'psr4lib/src');
		UB_Autoload::add_psr0('UBTest', $this->data);

		$this->assertFalse(UB_Autoload::load($name));
	}

	public function test_the_config_is_read_by_init(): void
	{
		Kohana::$config->load('ub_autoload')->exchangeArray([
			'psr4'     => ['UBTest\\Psr4\\' => ['psr4lib/src', '']],
			'psr0'     => ['UBTestLegacy' => 'psr0lib/library'],
			'classmap' => ['UBTestSingle' => 'single/everything.php'],
			'files'    => ['helpers/functions.php'],
			'unknown'  => 'is ignored',
		]);

		try
		{
			UB_Autoload::init();

			$this->assertTrue(UB_Autoload::load('UBTest\\Psr4\\Greeter') OR class_exists('UBTest\\Psr4\\Greeter', false));
			$this->assertTrue(class_exists('UBTestLegacy'));
			$this->assertTrue(class_exists('UBTestSingle'));
			$this->assertTrue(function_exists('ubtest_autoload_helper'));
		}
		finally
		{
			Kohana::$config->load('ub_autoload')->exchangeArray(['psr4' => [], 'psr0' => [], 'classmap' => [], 'files' => []]);
		}
	}

	public function test_vendor_directories_are_those_of_the_cascade(): void
	{
		$this->reset(null);

		$paths = UB_Autoload::get_vendor_paths();

		foreach ($paths as $path)
		{
			$this->assertDirectoryExists($path);
			$this->assertStringEndsWith('vendor'.DIRECTORY_SEPARATOR, $path);
			$this->assertContains(dirname($path).DIRECTORY_SEPARATOR, Kohana::include_paths());
		}

		// The vendor directory of Composer is not one of them
		$this->assertNotContains(DOCROOT.'vendor'.DIRECTORY_SEPARATOR, $paths);
	}

	public function test_the_old_name_of_the_class_works(): void
	{
		UB_Autoloader::add_psr4('UBTest\\Psr4\\', 'psr4lib/src');

		$this->assertTrue(UB_Autoloader::load('UBTest\\Psr4\\Greeter') OR class_exists('UBTest\\Psr4\\Greeter', false));
	}
}
