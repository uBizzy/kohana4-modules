<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/StorageDriverTestCase.php';

/**
 * Tests the storage in S3: against a server that checks the signature
 * of every request, and against the example Amazon gives.
 *
 * The tests with a server run when the environment variable
 * UB_STORAGE_TEST_S3_ENDPOINT is set. The other settings are
 * UB_STORAGE_TEST_S3_KEY, UB_STORAGE_TEST_S3_SECRET and
 * UB_STORAGE_TEST_S3_BUCKET (made when it is not there).
 *
 * @package    UB_Storage
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.storage')]
class UB_StorageS3Test extends UB_Storage_DriverTestCase
{
	/**
	 * @var bool  Was the bucket of the tests made in this run?
	 */
	protected static $bucket_made = false;

	/**
	 * The settings of the test server, with others on top.
	 */
	protected function settings(array $settings = []): array
	{
		return $settings + [
			'driver'   => 's3',
			'endpoint' => getenv('UB_STORAGE_TEST_S3_ENDPOINT'),
			'key'      => (string) getenv('UB_STORAGE_TEST_S3_KEY'),
			'secret'   => (string) getenv('UB_STORAGE_TEST_S3_SECRET'),
			'bucket'   => getenv('UB_STORAGE_TEST_S3_BUCKET') ?: 'ub-storage-test',
			'region'   => 'us-east-1',
			'timeout'  => 10,
		];
	}

	protected function storage(string $root): ?UB_Storage
	{
		if ( ! getenv('UB_STORAGE_TEST_S3_ENDPOINT') OR ! extension_loaded('curl'))
			return null;

		$storage = UB_Storage::factory($this->settings(['root' => $root]));

		if ( ! static::$bucket_made)
		{
			// "PUT /bucket/" makes the bucket; the answer says so when it is there already
			$response = (new ReflectionMethod($storage, '_request'))->invoke($storage, 'PUT', '')->execute();

			if ($response->status() >= 400 AND ! str_contains((string) $response->body(), 'BucketAlready'))
			{
				$this->fail('The bucket of the tests could not be made: '.$response->status().' '.$response->body());
			}

			static::$bucket_made = true;
		}

		return $storage;
	}

	/**
	 * The signature is the one Amazon works out in its documentation
	 * ("Signature Calculations for the Authorization Header", the
	 * example "GET Object"), so it is right for the real service too.
	 */
	public function test_signature_is_the_one_of_the_documentation(): void
	{
		$storage = new UB_StorageS3Test_Storage([
			'driver' => 's3',
			'key'    => 'AKIAIOSFODNN7EXAMPLE',
			'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
			'region' => 'us-east-1',
			'bucket' => 'examplebucket',
		]);

		$signature = $storage->signature('GET', '/test.txt', [], [
			'host'                 => 'examplebucket.s3.amazonaws.com',
			'range'                => 'bytes=0-9',
			'x-amz-content-sha256' => hash('sha256', ''),
			'x-amz-date'           => '20130524T000000Z',
		], hash('sha256', ''), gmmktime(0, 0, 0, 5, 24, 2013));

		$this->assertSame('f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41', $signature);

		// The example of a list, with a query string
		$signature = $storage->signature('GET', '/', ['max-keys' => '2', 'prefix' => 'J'], [
			'host'                 => 'examplebucket.s3.amazonaws.com',
			'x-amz-content-sha256' => hash('sha256', ''),
			'x-amz-date'           => '20130524T000000Z',
		], hash('sha256', ''), gmmktime(0, 0, 0, 5, 24, 2013));

		$this->assertSame('34b48302e7b5fa45bde8084f4b7868a86f0a534bc59db6670ed5711ef69dc6f7', $signature);
	}

	/**
	 * Where a request goes: the bucket in the host for Amazon, in the
	 * path for a service with an address of its own.
	 */
	public function test_addresses(): void
	{
		$amazon = new UB_StorageS3Test_Storage(['driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'eu-west-1', 'bucket' => 'my-files', 'root' => 'site']);

		$this->assertStringStartsWith('https://my-files.s3.eu-west-1.amazonaws.com/site/a%20b/c%2B.txt?', $amazon->temporary_url('a b/c+.txt'));

		$other = new UB_StorageS3Test_Storage(['driver' => 's3', 'key' => 'k', 'secret' => 's', 'bucket' => 'my-files', 'endpoint' => 'https://storage.example.com:9000/']);

		$this->assertStringStartsWith('https://storage.example.com:9000/my-files/a.txt?', $other->temporary_url('a.txt'));

		// The address says how long it works, a week at most, and holds no secret
		$url = $other->temporary_url('a.txt', 99999999);

		$this->assertStringContainsString('X-Amz-Expires=604800', $url);
		$this->assertStringContainsString('X-Amz-Credential=k%2F', $url);
		$this->assertStringNotContainsString('=s&', $url);
	}

	/**
	 * A temporary address lets anybody fetch the file without a login,
	 * and only that file.
	 */
	public function test_temporary_url_works_without_a_login(): void
	{
		$this->storage->put('private/report.txt', 'for your eyes only');

		$url      = $this->storage->temporary_url('private/report.txt', 60);
		$response = Request::factory($url)->execute();

		$this->assertSame(200, $response->status());
		$this->assertSame('for your eyes only', $response->body());

		// The same address for another file is refused
		$other = Request::factory(str_replace('report.txt', 'other.txt', $url))->execute();

		$this->assertSame(403, $other->status());

		// And without the signature there is no way in
		$plain = Request::factory(strstr($url, '?', true))->execute();

		$this->assertSame(403, $plain->status());
	}

	/**
	 * A wrong secret is refused by the server, and the message holds
	 * neither secret.
	 */
	public function test_wrong_secret_is_refused(): void
	{
		$storage = UB_Storage::factory($this->settings(['secret' => 'a-wrong-secret']));

		try
		{
			$storage->put('a.txt', 'a');
			$this->fail('A wrong secret must be refused');
		}
		catch (UB_Storage_Exception $e)
		{
			$this->assertStringContainsString('The storage refused the request for a.txt', $e->getMessage());
			$this->assertStringContainsString('SignatureDoesNotMatch', $e->getMessage());
			$this->assertStringNotContainsString('a-wrong-secret', $e->getMessage());
			$this->assertStringNotContainsString((string) getenv('UB_STORAGE_TEST_S3_SECRET'), $e->getMessage());
		}
	}

	public function test_server_that_is_not_there(): void
	{
		$this->expectException(UB_Storage_Exception::class);
		$this->expectExceptionMessage('The storage cannot be reached');

		UB_Storage::factory($this->settings(['endpoint' => 'http://127.0.0.1:1', 'timeout' => 2]))->put('a.txt', 'a');
	}

	/**
	 * Without a root, the keys start at the top of the bucket; with one,
	 * a storage does not see the files of another.
	 */
	public function test_root_keeps_storages_apart(): void
	{
		$top  = UB_Storage::factory($this->settings());
		$root = (new ReflectionMethod($this->storage, '_root'))->invoke($this->storage);

		$this->storage->put('mine.txt', 'mine');

		$this->assertSame('mine', $top->get($root.'mine.txt'));
		$this->assertSame(['mine.txt'], $this->storage->files());
		$this->assertContains($root.'mine.txt', $top->files(rtrim($root, '/')));
	}
}

/**
 * The S3 storage with its signature open to the test.
 */
class UB_StorageS3Test_Storage extends UB_Storage_S3
{
	public function signature(string $method, string $uri, array $query, array $headers, string $hash, int $time): string
	{
		return $this->_signature($method, $uri, $query, $headers, $hash, $time);
	}
}
