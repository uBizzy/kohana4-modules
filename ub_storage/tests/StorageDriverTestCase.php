<?php

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The tests every storage driver must pass. Each driver has a test
 * class that extends this one and says how its storage is made.
 *
 * @package    UB_Storage
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
abstract class UB_Storage_DriverTestCase extends Unittest_TestCase
{
	/**
	 * @var UB_Storage  The storage of the test: empty when the test starts
	 */
	protected $storage;

	/**
	 * @var string  Directory of the server for the files of one test
	 */
	protected $temp;

	/**
	 * Returns the storage to test, or null when its server is not
	 * configured. Every test gets a root of its own, named by $root.
	 */
	abstract protected function storage(string $root): ?UB_Storage;

	public function setUp(): void
	{
		parent::setUp();

		$this->temp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ub_storage_'.bin2hex(random_bytes(6));

		mkdir($this->temp);

		$storage = $this->storage('test-'.bin2hex(random_bytes(6)));

		if ($storage === null)
		{
			$this->markTestSkipped('The server for '.static::class.' is not configured or its extension is missing.');
		}

		$this->storage = $storage;
	}

	public function tearDown(): void
	{
		if ($this->storage !== null)
		{
			// Leave nothing on the server
			foreach ($this->storage->files('', true) as $file)
			{
				$this->storage->delete($file);
			}
		}

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file)
		{
			($file->isDir() AND ! $file->isLink()) ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($this->temp);

		parent::tearDown();
	}

	public function test_put_get_and_delete(): void
	{
		$path = 'invoices/2026/17.txt';

		$this->assertFalse($this->storage->exists($path));
		$this->assertSame($this->storage, $this->storage->put($path, 'the first content'));

		$this->assertTrue($this->storage->exists($path));
		$this->assertSame('the first content', $this->storage->get($path));
		$this->assertSame(17, $this->storage->size($path));
		$this->assertEqualsWithDelta(time(), $this->storage->modified($path), 120);

		// A file that is there is replaced
		$this->storage->put($path, 'second');

		$this->assertSame('second', $this->storage->get($path));
		$this->assertSame(6, $this->storage->size($path));

		$this->assertTrue($this->storage->delete($path));
		$this->assertFalse($this->storage->exists($path));
		$this->assertFalse($this->storage->delete($path), 'There is nothing to remove the second time');
	}

	/**
	 * What is stored comes back byte for byte, under any name.
	 */
	public function test_content_and_names_are_kept_as_they_are(): void
	{
		$files = [
			'empty.txt'                 => '',
			'bytes.bin'                 => random_bytes(2000),
			'with spaces and (more).txt' => 'spaces',
			'olá/ação & co.txt'         => 'Olá, mundo!',
			'a/b/c/d/deep.txt'          => 'deep',
			'looks.like.many.dots'      => 'dots',
			'plus+and=equals.txt'       => 'signs',
		];

		foreach ($files as $path => $content)
		{
			$this->storage->put($path, $content);
		}

		foreach ($files as $path => $content)
		{
			$this->assertTrue($this->storage->exists($path), $path);
			$this->assertSame($content, $this->storage->get($path), $path);
			$this->assertSame(strlen($content), $this->storage->size($path), $path);
		}

		$expected = array_keys($files);

		sort($expected);

		$this->assertSame($expected, $this->storage->files('', true));
	}

	/**
	 * Files of the server go in and out without passing through memory.
	 */
	public function test_files_of_the_server(): void
	{
		$content = str_repeat('0123456789abcdef', 20000);

		file_put_contents($this->temp.'/source.bin', $content);

		$this->assertSame($this->storage, $this->storage->put_file('uploads/large.bin', $this->temp.'/source.bin'));
		$this->assertSame(320000, $this->storage->size('uploads/large.bin'));
		$this->assertFileExists($this->temp.'/source.bin', 'The file of the server stays');

		$target = $this->storage->get_to('uploads/large.bin', $this->temp.'/copy.bin');

		$this->assertSame($this->temp.'/copy.bin', $target);
		$this->assertSame(md5($content), md5_file($target));
	}

	public function test_listing(): void
	{
		foreach (['top.txt', 'another.txt', 'docs/a.txt', 'docs/b.txt', 'docs/old/c.txt', 'docsmore/d.txt'] as $path)
		{
			$this->storage->put($path, 'x');
		}

		$this->assertSame(['another.txt', 'top.txt'], $this->storage->files());
		$this->assertSame(['docs/a.txt', 'docs/b.txt'], $this->storage->files('docs'));
		$this->assertSame(['docs/a.txt', 'docs/b.txt'], $this->storage->files('docs/'), 'A "/" at the end makes no difference');
		$this->assertSame(['docs/a.txt', 'docs/b.txt', 'docs/old/c.txt'], $this->storage->files('docs', true));
		$this->assertSame(['docs/old/c.txt'], $this->storage->files('docs/old'));
		$this->assertSame(['another.txt', 'docs/a.txt', 'docs/b.txt', 'docs/old/c.txt', 'docsmore/d.txt', 'top.txt'], $this->storage->files('', true));

		// A directory that is not there has no files
		$this->assertSame([], $this->storage->files('nothing'));
		$this->assertSame([], $this->storage->files('nothing/here', true));
	}

	public function test_file_that_is_not_there(): void
	{
		$this->assertFalse($this->storage->exists('nothing.txt'));
		$this->assertFalse($this->storage->delete('nothing.txt'));

		$calls = [
			'get'      => fn () => $this->storage->get('nothing.txt'),
			'get_to'   => fn () => $this->storage->get_to('nothing.txt', $this->temp.'/nothing.txt'),
			'size'     => fn () => $this->storage->size('nothing.txt'),
			'modified' => fn () => $this->storage->modified('nothing.txt'),
		];

		foreach ($calls as $method => $call)
		{
			try
			{
				$call();
				$this->fail($method.'() of a file that is not there must fail');
			}
			catch (UB_Storage_Exception $e)
			{
				$this->assertStringContainsString('nothing.txt', $e->getMessage(), $method);
			}
		}

		$this->assertFileDoesNotExist($this->temp.'/nothing.txt', 'A failed download leaves no file');

		// A file of the server that is not there is not stored as an empty one
		try
		{
			$this->storage->put_file('copy.txt', $this->temp.'/no-such-file');
			$this->fail('A missing file must not be stored');
		}
		catch (UB_Storage_Exception $e)
		{
			$this->assertFalse($this->storage->exists('copy.txt'));
		}
	}

	public static function provider_paths_that_are_refused(): array
	{
		return [
			'up one directory'      => ['../secret.txt'],
			'up from a directory'   => ['docs/../../secret.txt'],
			'up and down again'     => ['docs/../other.txt'],
			'from the root'         => ['/etc/passwd'],
			'the Windows way'       => ['..\\secret.txt'],
			'a Windows drive'       => ['C:\\secret.txt'],
			'a control character'   => ["file\x00.txt"],
			'a line break'          => ["file\n.txt"],
			'an empty part'         => ['docs//file.txt'],
			'the directory itself'  => ['./file.txt'],
			'only dots'             => ['..'],
			'nothing'               => [''],
		];
	}

	/**
	 * A path can never lead out of the storage, whatever is done with it.
	 */
	#[DataProvider('provider_paths_that_are_refused')]
	public function test_paths_that_could_leave_the_storage_are_refused(string $path): void
	{
		file_put_contents($this->temp.'/file.txt', 'x');

		$calls = [
			'put'      => fn () => $this->storage->put($path, 'evil'),
			'put_file' => fn () => $this->storage->put_file($path, $this->temp.'/file.txt'),
			'get'      => fn () => $this->storage->get($path),
			'get_to'   => fn () => $this->storage->get_to($path, $this->temp.'/out.txt'),
			'exists'   => fn () => $this->storage->exists($path),
			'delete'   => fn () => $this->storage->delete($path),
			'size'     => fn () => $this->storage->size($path),
			'modified' => fn () => $this->storage->modified($path),
		];

		if ($path !== '')
		{
			// For a directory, nothing is the root
			$calls['files'] = fn () => $this->storage->files($path);
		}

		foreach ($calls as $method => $call)
		{
			try
			{
				$call();
				$this->fail($method.'() must refuse the path');
			}
			catch (UB_Storage_Exception $e)
			{
				$this->assertStringContainsString('is not allowed', $e->getMessage(), $method);
			}
		}

		$this->assertSame([], $this->storage->files('', true), 'Nothing was stored');
	}

	/**
	 * Keys and passwords are not shown when the storage is dumped.
	 */
	public function test_settings_are_not_shown(): void
	{
		$config = (new ReflectionProperty('UB_Storage', '_config'))->getValue($this->storage);
		$dump   = print_r($this->storage, true).var_export($this->storage->__debugInfo(), true);

		foreach (['password', 'secret', 'key'] as $name)
		{
			if (is_string($config[$name] ?? null) AND $config[$name] !== '')
			{
				$this->assertStringNotContainsString($config[$name], $dump, $name);
			}
		}

		$this->assertStringContainsString(get_class($this->storage), $dump);
	}

	/**
	 * Only S3 hands out addresses that work for a while.
	 */
	public function test_temporary_url_is_for_s3(): void
	{
		if ($this->storage instanceof UB_Storage_S3)
		{
			$this->assertStringContainsString('X-Amz-Signature=', $this->storage->temporary_url('a.txt'));

			return;
		}

		$this->expectException(UB_Storage_Exception::class);
		$this->expectExceptionMessage('cannot make temporary addresses');

		$this->storage->temporary_url('a.txt');
	}
}
