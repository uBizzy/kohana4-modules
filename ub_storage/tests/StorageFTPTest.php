<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/StorageDriverTestCase.php';

/**
 * Tests the storage on an FTP server.
 *
 * Runs when the environment variable UB_STORAGE_TEST_FTP_HOST is set.
 * The other settings are UB_STORAGE_TEST_FTP_PORT (21),
 * UB_STORAGE_TEST_FTP_USER and UB_STORAGE_TEST_FTP_PASSWORD.
 *
 * @package    UB_Storage
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.storage')]
class UB_StorageFTPTest extends UB_Storage_DriverTestCase
{
	/**
	 * The settings of the test server, with others on top.
	 */
	protected function settings(array $settings = []): array
	{
		return $settings + [
			'driver'   => 'ftp',
			'host'     => getenv('UB_STORAGE_TEST_FTP_HOST'),
			'port'     => getenv('UB_STORAGE_TEST_FTP_PORT') ?: 21,
			'username' => (string) getenv('UB_STORAGE_TEST_FTP_USER'),
			'password' => (string) getenv('UB_STORAGE_TEST_FTP_PASSWORD'),
			'timeout'  => 10,
		];
	}

	protected function storage(string $root): ?UB_Storage
	{
		if ( ! getenv('UB_STORAGE_TEST_FTP_HOST') OR ! function_exists('ftp_connect'))
			return null;

		return UB_Storage::factory($this->settings(['root' => $root]));
	}

	/**
	 * A login that is refused says so, without the password.
	 */
	public function test_refused_login_does_not_show_the_password(): void
	{
		$storage = UB_Storage::factory($this->settings(['password' => 'a-wrong-password']));

		try
		{
			$storage->exists('a.txt');
			$this->fail('A wrong password must be refused');
		}
		catch (UB_Storage_Exception $e)
		{
			$this->assertStringContainsString('refused the login', $e->getMessage());
			$this->assertStringNotContainsString('a-wrong-password', $e->getMessage());
		}
	}

	public function test_server_that_is_not_there(): void
	{
		$this->expectException(UB_Storage_Exception::class);
		$this->expectExceptionMessage('Unable to connect to the FTP server');

		UB_Storage::factory($this->settings(['port' => 1, 'timeout' => 2]))->exists('a.txt');
	}

	/**
	 * Without a root, the paths start in the directory of the login.
	 */
	public function test_without_a_root(): void
	{
		$storage = UB_Storage::factory($this->settings());
		$name    = 'ub-storage-'.bin2hex(random_bytes(6)).'.txt';

		$storage->put($name, 'at the top');

		try
		{
			$this->assertContains($name, $storage->files());
			$this->assertSame('at the top', $storage->get($name));
		}
		finally
		{
			$storage->delete($name);
		}
	}
}
