<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/StorageDriverTestCase.php';

/**
 * Tests the storage in a directory of the server, and what all
 * storages share: how one is made from the config.
 *
 * @package    UB_Storage
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.storage')]
class UB_StorageLocalTest extends UB_Storage_DriverTestCase
{
	protected function storage(string $root): ?UB_Storage
	{
		return UB_Storage::factory(['driver' => 'local', 'root' => $this->temp.'/storage/'.$root]);
	}

	/**
	 * The directory of the storage is made when it is needed, and a
	 * file is never seen half written.
	 */
	public function test_directory_is_made_and_no_temporary_file_is_left(): void
	{
		$root    = $this->temp.'/new/and/deeper';
		$storage = UB_Storage::factory(['driver' => 'local', 'root' => $root]);

		$this->assertDirectoryDoesNotExist($root);

		$storage->put('a/b.txt', 'content');
		file_put_contents($this->temp.'/source.txt', 'source');
		$storage->put_file('a/c.txt', $this->temp.'/source.txt');

		$this->assertSame('content', file_get_contents($root.'/a/b.txt'));
		$this->assertSame(['b.txt', 'c.txt'], array_values(array_diff(scandir($root.'/a'), ['.', '..'])));
	}

	/**
	 * A link inside the storage must not lead a file out of it, to
	 * read or to write.
	 */
	public function test_link_in_the_storage_is_not_followed(): void
	{
		$root = (new ReflectionMethod($this->storage, '_root'))->invoke($this->storage);

		mkdir($this->temp.'/outside');
		file_put_contents($this->temp.'/outside/secret.txt', 'secret');

		symlink($this->temp.'/outside', $root.'/shortcut');
		symlink($this->temp.'/outside/secret.txt', $root.'/link.txt');

		$this->assertFalse($this->storage->exists('shortcut/secret.txt'));
		$this->assertFalse($this->storage->exists('link.txt'));
		$this->assertSame([], $this->storage->files('', true));
		$this->assertSame([], $this->storage->files('shortcut'));

		foreach (['shortcut/secret.txt', 'link.txt'] as $path)
		{
			try
			{
				$this->storage->get($path);
				$this->fail('A file must not be read through a link');
			}
			catch (UB_Storage_Exception $e)
			{
				$this->assertStringContainsString('is not in the storage', $e->getMessage());
			}
		}

		try
		{
			$this->storage->put('shortcut/new/evil.txt', 'evil');
			$this->fail('A file must not be written through a link');
		}
		catch (UB_Storage_Exception $e)
		{
			$this->assertStringContainsString('leads out of the storage', $e->getMessage());
		}

		$this->assertSame(['secret.txt'], array_values(array_diff(scandir($this->temp.'/outside'), ['.', '..'])));

		unlink($root.'/shortcut');
		unlink($root.'/link.txt');
	}

	public function test_root_must_be_set(): void
	{
		$this->expectException(UB_Storage_Exception::class);
		$this->expectExceptionMessage('is not set or cannot be made');

		UB_Storage::factory(['driver' => 'local'])->put('a.txt', 'a');
	}

	/**
	 * A driver is named in the settings: only a storage driver can be made.
	 */
	public function test_unknown_driver_is_refused(): void
	{
		foreach (['nothing', '', 'Exception', '../Local', 'local/../x', null] as $driver)
		{
			try
			{
				UB_Storage::factory(['driver' => $driver]);
				$this->fail('The driver '.var_export($driver, true).' does not exist');
			}
			catch (UB_Storage_Exception $e)
			{
				$this->assertStringContainsString('There is no storage driver', $e->getMessage());
			}
		}

		$this->assertInstanceOf(UB_Storage_Local::class, UB_Storage::factory(['driver' => 'LOCAL', 'root' => $this->temp]));
		$this->assertInstanceOf(UB_Storage_S3::class, UB_Storage::factory(['driver' => 's3']));
		$this->assertInstanceOf(UB_Storage_FTP::class, UB_Storage::factory(['driver' => 'ftp']));
	}

	/**
	 * UB_Storage::instance() gives the places of config/ub_storage.php,
	 * each one once.
	 */
	public function test_instance_from_the_config(): void
	{
		$this->setEnvironment([
			'UB_Storage::$default' => null,
			'ub_storage.default'   => 'files',
			'ub_storage.disks'     => [
				'files'   => ['driver' => 'local', 'root' => $this->temp.'/files'],
				'backups' => ['driver' => 'local', 'root' => $this->temp.'/backups'],
			],
		]);

		$instances = new ReflectionProperty('UB_Storage', '_instances');
		$before    = $instances->getValue();

		$instances->setValue(null, []);

		try
		{
			$files = UB_Storage::instance();

			$this->assertSame($files, UB_Storage::instance('files'));
			$this->assertNotSame($files, UB_Storage::instance('backups'));

			$files->put('a.txt', 'a');

			$this->assertFileExists($this->temp.'/files/a.txt');
			$this->assertSame([], UB_Storage::instance('backups')->files());

			// The default can be set in code too
			UB_Storage::$default = 'backups';

			$this->assertSame(UB_Storage::instance('backups'), UB_Storage::instance());

			$this->expectException(UB_Storage_Exception::class);
			$this->expectExceptionMessage('The storage "nothing" is not in config/ub_storage.php');

			UB_Storage::instance('nothing');
		}
		finally
		{
			$instances->setValue(null, $before);
		}
	}
}
