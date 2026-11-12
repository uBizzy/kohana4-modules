<?php
/**
 * Keeps files in Amazon S3, or in any service that speaks the same
 * language: MinIO, DigitalOcean Spaces, Backblaze B2, Cloudflare R2,
 * Wasabi and others. Needs the curl extension of PHP.
 *
 *     // config/ub_storage.php
 *     's3' => [
 *         'driver' => 's3',
 *         'key'    => '...',
 *         'secret' => '...',
 *         'region' => 'eu-west-1',
 *         'bucket' => 'my-files',
 *     ],
 *
 *     // Another service: give its address
 *     'spaces' => [
 *         'driver'   => 's3',
 *         'key'      => '...',
 *         'secret'   => '...',
 *         'region'   => 'ams3',
 *         'bucket'   => 'my-files',
 *         'endpoint' => 'https://ams3.digitaloceanspaces.com',
 *     ],
 *
 * Every request is signed with the secret (AWS Signature Version 4);
 * the secret itself never leaves the server. Nothing but PHP is needed:
 * there is no library of Amazon to install.
 *
 * @package    UB_Storage
 * @category   Drivers
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Storage_S3 extends UB_Storage {

	/**
	 * @var  string  What is signed in place of the content, when the content is not part of the signature
	 */
	public const string UNSIGNED = 'UNSIGNED-PAYLOAD';

	public function put(string $path, string $content): static
	{
		$request = $this->_request('PUT', $this->_key($path), [], hash('sha256', $content))->body($content);

		$this->_send($request, $path);

		return $this;
	}

	public function put_file(string $path, string $file): static
	{
		// Checked first: a missing file must not become an empty one in the storage
		fclose($this->_read($file));

		$request = $this->_request('PUT', $this->_key($path), [], (string) hash_file('sha256', $file));

		$this->_client($request)->body_from($file);

		$this->_send($request, $path);

		return $this;
	}

	public function get(string $path): string
	{
		$body = $this->_send($this->_request('GET', $this->_key($path)), $path)->body();

		return is_string($body) ? $body : '';
	}

	public function get_to(string $path, string $file): string
	{
		$request = $this->_request('GET', $this->_key($path));

		$this->_client($request)->save_to($file);

		$this->_send($request, $path);

		return $file;
	}

	public function exists(string $path): bool
	{
		return $this->_head($path) !== null;
	}

	public function delete(string $path): bool
	{
		// S3 answers the same for a file that was never there
		if ( ! $this->exists($path))
			return false;

		$this->_send($this->_request('DELETE', $this->_key($path)), $path);

		return true;
	}

	public function size(string $path): int
	{
		return (int) $this->_header($path, 'content-length');
	}

	public function modified(string $path): int
	{
		return (int) strtotime($this->_header($path, 'last-modified'));
	}

	public function files(string $directory = '', bool $deep = false): array
	{
		$directory = $this->_path($directory, true);
		$root      = $this->_root();
		$prefix    = $root.(($directory === '') ? '' : $directory.'/');
		$files     = [];
		$token     = null;

		do
		{
			// S3 gives a list in parts of 1000 at most
			$query = ['list-type' => '2', 'prefix' => $prefix] + ($deep ? [] : ['delimiter' => '/']) + (($token === null) ? [] : ['continuation-token' => $token]);

			$body = $this->_send($this->_request('GET', '', $query), $directory)->body();
			$list = @simplexml_load_string(is_string($body) ? $body : '');

			if ($list === false)
				throw new UB_Storage_Exception('The storage gave a list that cannot be read');

			foreach ($list->Contents as $entry)
			{
				$key = (string) $entry->Key;

				// A "directory" made in a console is an empty file whose name ends in "/"
				if ( ! str_ends_with($key, '/'))
				{
					$files[] = substr($key, strlen($root));
				}
			}

			$token = ((string) $list->IsTruncated === 'true') ? (string) $list->NextContinuationToken : null;
		}
		while ($token !== null AND $token !== '');

		sort($files);

		return $files;
	}

	/**
	 * Returns an address at which the file can be fetched for a while,
	 * by anybody who has the address, without a login. For a download
	 * link to a private file.
	 *
	 *     // Works for ten minutes
	 *     $url = $storage->temporary_url('invoices/2026/17.pdf', 600);
	 *
	 * @param   string  $path     the file
	 * @param   int     $seconds  how long the address works, a week at most
	 * @return  string
	 */
	public function temporary_url(string $path, int $seconds = 3600): string
	{
		$time  = $this->_time();
		$date  = gmdate('Ymd\THis\Z', $time);
		$scope = gmdate('Ymd', $time).'/'.$this->_setting('region', 'us-east-1').'/s3/aws4_request';
		$uri   = $this->_uri($this->_key($path));

		$query = [
			'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
			'X-Amz-Credential'    => $this->_setting('key').'/'.$scope,
			'X-Amz-Date'          => $date,
			'X-Amz-Expires'       => (string) max(1, min(604800, $seconds)),
			'X-Amz-SignedHeaders' => 'host',
		];

		if ($this->_setting('token') !== '')
		{
			$query['X-Amz-Security-Token'] = $this->_setting('token');
		}

		$signature = $this->_signature('GET', $uri, $query, ['host' => $this->_host()], static::UNSIGNED, $time);

		return $this->_base().$uri.'?'.$this->_query($query).'&X-Amz-Signature='.$signature;
	}

	/**
	 * Returns the time requests are signed with.
	 *
	 * @return  int
	 */
	protected function _time(): int
	{
		return time();
	}

	/**
	 * Returns the prefix that every key of this storage starts with: the
	 * "root" of the settings, with a "/" after it, or nothing.
	 *
	 * @return  string
	 */
	protected function _root(): string
	{
		$root = $this->_path($this->_setting('root'), true);

		return ($root === '') ? '' : $root.'/';
	}

	/**
	 * Returns the key of a file in the bucket.
	 *
	 * @param   string  $path  the file
	 * @return  string
	 * @throws  UB_Storage_Exception  when the path is not allowed
	 */
	protected function _key(string $path): string
	{
		return $this->_root().$this->_path($path);
	}

	/**
	 * Tells whether the bucket is named in the path of the address
	 * ("https://host/bucket/key") and not in its host
	 * ("https://bucket.host/key"). Amazon wants the host; most other
	 * services, and every address given as "endpoint", the path.
	 *
	 * @return  bool
	 */
	protected function _path_style(): bool
	{
		return (bool) ($this->_config['path_style'] ?? ($this->_setting('endpoint') !== ''));
	}

	/**
	 * Returns the scheme and host of the service, without a "/" at the end.
	 *
	 * @return  string
	 */
	protected function _base(): string
	{
		$endpoint = rtrim($this->_setting('endpoint', 'https://s3.'.$this->_setting('region', 'us-east-1').'.amazonaws.com'), '/');

		if ($this->_path_style())
			return $endpoint;

		// The bucket goes in front of the host
		return (string) preg_replace('#^(https?://)#', '$1'.$this->_setting('bucket').'.', $endpoint);
	}

	/**
	 * Returns the host of the service as it is sent and signed, with its
	 * port when that is not the usual one.
	 *
	 * @return  string
	 */
	protected function _host(): string
	{
		$port = parse_url($this->_base(), PHP_URL_PORT);

		return parse_url($this->_base(), PHP_URL_HOST).(is_int($port) ? ':'.$port : '');
	}

	/**
	 * Returns the path part of the address for a key, encoded as S3
	 * expects: every character but letters, digits, "-", "_", "." and
	 * "~", and the "/" between parts.
	 *
	 * @param   string  $key  key of the file, empty for the bucket itself
	 * @return  string
	 */
	protected function _uri(string $key): string
	{
		$path = ($this->_path_style() ? $this->_setting('bucket').'/' : '').$key;

		return '/'.implode('/', array_map('rawurlencode', explode('/', $path)));
	}

	/**
	 * Returns a query string as it is signed: sorted by name, every
	 * name and value encoded.
	 *
	 * @param   array<string, string>  $query  values by name
	 * @return  string
	 */
	protected function _query(array $query): string
	{
		ksort($query);

		$pairs = [];

		foreach ($query as $name => $value)
		{
			$pairs[] = rawurlencode($name).'='.rawurlencode($value);
		}

		return implode('&', $pairs);
	}

	/**
	 * Returns the signature of a request (AWS Signature Version 4): a
	 * code that only somebody with the secret can make, and that fits
	 * this request only.
	 *
	 * @param   string                 $method   HTTP method
	 * @param   string                 $uri      path part of the address, encoded
	 * @param   array<string, string>  $query    values of the query string
	 * @param   array<string, string>  $headers  the headers that are signed, in lower case
	 * @param   string                 $hash     SHA-256 of the content, or UNSIGNED
	 * @param   int                    $time     time of the request
	 * @return  string
	 */
	protected function _signature(string $method, string $uri, array $query, array $headers, #[SensitiveParameter] string $hash, int $time): string
	{
		ksort($headers);

		$lines = '';

		foreach ($headers as $name => $value)
		{
			$lines .= $name.':'.trim($value)."\n";
		}

		// What the request is, in the one form both sides agree on
		$request = implode("\n", [$method, $uri, $this->_query($query), $lines, implode(';', array_keys($headers)), $hash]);

		$day    = gmdate('Ymd', $time);
		$region = $this->_setting('region', 'us-east-1');
		$scope  = $day.'/'.$region.'/s3/aws4_request';

		$to_sign = implode("\n", ['AWS4-HMAC-SHA256', gmdate('Ymd\THis\Z', $time), $scope, hash('sha256', $request)]);

		// The secret is not used directly: a key for this day, region and service is made from it
		$key = hash_hmac('sha256', $day, 'AWS4'.$this->_setting('secret'), true);
		$key = hash_hmac('sha256', $region, $key, true);
		$key = hash_hmac('sha256', 's3', $key, true);
		$key = hash_hmac('sha256', 'aws4_request', $key, true);

		return hash_hmac('sha256', $to_sign, $key);
	}

	/**
	 * Returns a signed request to the service.
	 *
	 * @param   string                 $method  HTTP method
	 * @param   string                 $key     key of the file, empty for the bucket itself
	 * @param   array<string, string>  $query   values of the query string
	 * @param   string                 $hash    SHA-256 of the content that is sent; of nothing by default
	 * @return  Request
	 */
	protected function _request(string $method, string $key, array $query = [], ?string $hash = null): Request
	{
		$time = $this->_time();
		$hash ??= hash('sha256', '');
		$uri  = $this->_uri($key);

		$headers = [
			'host'                 => $this->_host(),
			'x-amz-content-sha256' => $hash,
			'x-amz-date'           => gmdate('Ymd\THis\Z', $time),
		];

		if ($this->_setting('token') !== '')
		{
			// Keys that are lent for a while come with a token
			$headers['x-amz-security-token'] = $this->_setting('token');
		}

		$signature = $this->_signature($method, $uri, $query, $headers, $hash, $time);

		$authorization = 'AWS4-HMAC-SHA256 Credential='.$this->_setting('key').'/'.gmdate('Ymd', $time).'/'.$this->_setting('region', 'us-east-1').'/s3/aws4_request'
			.', SignedHeaders='.implode(';', array_keys($headers))
			.', Signature='.$signature;

		$request = Request::factory($this->_base().$uri.(($query === []) ? '' : '?'.$this->_query($query)))->method($method);

		// The host is set by the client from the address
		unset($headers['host']);

		foreach ($headers + ['authorization' => $authorization] as $name => $value)
		{
			$request->headers($name, $value);
		}

		$this->_client($request)->timeout((int) $this->_setting('timeout', '30'));

		return $request;
	}

	/**
	 * Returns the client of a request, which must be the curl client.
	 *
	 * @param   Request  $request  the request
	 * @return  Request_Client_Curl
	 * @throws  UB_Storage_Exception  without the curl extension
	 */
	protected function _client(Request $request): Request_Client_Curl
	{
		$client = $request->client();

		if ( ! $client instanceof Request_Client_Curl)
			throw new UB_Storage_Exception('The S3 storage needs the curl extension of PHP');

		return $client;
	}

	/**
	 * Sends a request and returns the answer. An answer that is an
	 * error becomes an exception with the reason the service gives.
	 *
	 * @param   Request  $request  the request
	 * @param   string   $path     the file it is about, for the message
	 * @return  Response
	 * @throws  UB_Storage_Exception  when the service cannot be reached or refuses
	 */
	protected function _send(Request $request, string $path): Response
	{
		try
		{
			$response = $request->execute();
		}
		catch (Request_Exception $e)
		{
			throw new UB_Storage_Exception('The storage cannot be reached: :error', [':error' => $e->getMessage()], 0, $e);
		}

		if ($response->status() >= 300)
		{
			$body = $response->body();

			// The service says what was wrong in a short code: "NoSuchKey", "AccessDenied"
			$reason = preg_match('#<Code>([A-Za-z0-9]+)</Code>#', is_string($body) ? $body : '', $match) ? $match[1] : 'status '.$response->status();

			throw new UB_Storage_Exception(($response->status() === 404)
				? 'The file :path is not in the storage (:reason)'
				: 'The storage refused the request for :path (:reason)', [':path' => $path, ':reason' => $reason]);
		}

		return $response;
	}

	/**
	 * Asks for the headers of a file, without its content.
	 *
	 * @param   string  $path  the file
	 * @return  Response|null  null when the file is not there
	 * @throws  UB_Storage_Exception  when the service cannot be reached or refuses
	 */
	protected function _head(string $path): ?Response
	{
		try
		{
			$response = $this->_request('HEAD', $this->_key($path))->execute();
		}
		catch (Request_Exception $e)
		{
			throw new UB_Storage_Exception('The storage cannot be reached: :error', [':error' => $e->getMessage()], 0, $e);
		}

		if ($response->status() === 404)
			return null;

		if ($response->status() >= 300)
			throw new UB_Storage_Exception('The storage refused the request for :path (status :status)', [':path' => $path, ':status' => $response->status()]);

		return $response;
	}

	/**
	 * Returns a header of a file.
	 *
	 * @param   string  $path  the file
	 * @param   string  $name  name of the header
	 * @return  string
	 * @throws  UB_Storage_Exception  when the file is not there
	 */
	protected function _header(string $path, string $name): string
	{
		$response = $this->_head($path);

		if ($response === null)
			throw new UB_Storage_Exception('The file :path is not in the storage', [':path' => $path]);

		$value = $response->headers($name);

		return is_scalar($value) ? (string) $value : '';
	}

}
