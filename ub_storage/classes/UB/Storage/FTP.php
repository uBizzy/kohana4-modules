<?php
/**
 * Keeps files on an FTP server. Needs the ftp extension of PHP.
 *
 *     // config/ub_storage.php
 *     'ftp' => [
 *         'driver'   => 'ftp',
 *         'host'     => 'ftp.example.com',
 *         'username' => 'ana',
 *         'password' => '...',
 *         'ssl'      => true,        // FTPS: the password and the files are encrypted
 *         'root'     => 'site/files',
 *     ],
 *
 * [!!] Plain FTP sends the password and every file readable by anybody
 * on the way. Use "ssl" whenever the server offers it.
 *
 * The connection is made the first time it is needed and kept until
 * the request ends.
 *
 * @package    UB_Storage
 * @category   Drivers
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Storage_FTP extends UB_Storage {

	/**
	 * @var  FTP\Connection|null  The connection, once it is made
	 */
	protected ?FTP\Connection $_ftp = null;

	/**
	 * Closes the connection.
	 */
	public function __destruct()
	{
		if ($this->_ftp !== null)
		{
			@ftp_close($this->_ftp);
		}
	}

	public function put(string $path, string $content): static
	{
		// The ftp functions send from an open file: one in memory
		$memory = fopen('php://temp', 'r+');

		if ($memory === false)
			throw new UB_Storage_Exception('Unable to store the file :path', [':path' => $path]);

		fwrite($memory, $content);
		rewind($memory);

		return $this->_send($path, $memory);
	}

	public function put_file(string $path, string $file): static
	{
		return $this->_send($path, $this->_read($file));
	}

	public function get(string $path): string
	{
		// The path is checked before the server is asked anything
		$remote = $this->_remote($path);
		$memory = fopen('php://temp', 'r+');

		if ($memory === false OR ! @ftp_fget($this->_connection(), $memory, $remote, FTP_BINARY))
			throw new UB_Storage_Exception('The file :path is not in the storage or cannot be read', [':path' => $path]);

		rewind($memory);

		$content = (string) stream_get_contents($memory);

		fclose($memory);

		return $content;
	}

	public function get_to(string $path, string $file): string
	{
		$remote = $this->_remote($path);
		$to     = $this->_write($file);
		$done   = @ftp_fget($this->_connection(), $to, $remote, FTP_BINARY);

		fclose($to);

		if ( ! $done)
		{
			@unlink($file);

			throw new UB_Storage_Exception('The file :path is not in the storage or cannot be read', [':path' => $path]);
		}

		return $file;
	}

	public function exists(string $path): bool
	{
		$remote = $this->_remote($path);

		// A file has a size; a directory and a missing file have none
		return ftp_size($this->_connection(), $remote) >= 0;
	}

	public function delete(string $path): bool
	{
		if ( ! $this->exists($path))
			return false;

		$remote = $this->_remote($path);

		if ( ! @ftp_delete($this->_connection(), $remote))
			throw new UB_Storage_Exception('Unable to remove the file :path', [':path' => $path]);

		return true;
	}

	public function size(string $path): int
	{
		$remote = $this->_remote($path);
		$size   = ftp_size($this->_connection(), $remote);

		if ($size < 0)
			throw new UB_Storage_Exception('The file :path is not in the storage', [':path' => $path]);

		return $size;
	}

	public function modified(string $path): int
	{
		$remote = $this->_remote($path);
		$time   = ftp_mdtm($this->_connection(), $remote);

		if ($time < 0)
			throw new UB_Storage_Exception('The file :path is not in the storage', [':path' => $path]);

		return $time;
	}

	public function files(string $directory = '', bool $deep = false): array
	{
		$files = $this->_list($this->_path($directory, true), $deep);

		sort($files);

		return $files;
	}

	/**
	 * Lists the files of a directory of the server.
	 *
	 * @param   string  $directory  the directory, as a clean path
	 * @param   bool    $deep       also list what is in sub directories
	 * @return  array<int, string>
	 */
	protected function _list(string $directory, bool $deep): array
	{
		$files = [];

		foreach ($this->_entries($directory) as $name => $is_directory)
		{
			$path = ltrim($directory.'/'.$name, '/');

			if ( ! $is_directory)
			{
				$files[] = $path;
			}
			elseif ($deep)
			{
				$files = array_merge($files, $this->_list($path, true));
			}
		}

		return $files;
	}

	/**
	 * Returns what is in a directory of the server: for each name,
	 * whether it is a directory.
	 *
	 * @param   string  $directory  the directory, as a clean path
	 * @return  array<string, bool>
	 */
	protected function _entries(string $directory): array
	{
		$remote  = $this->_remote($directory, true);
		$ftp     = $this->_connection();
		$entries = [];

		// The newer way, which says what is a file and what a directory
		$listed = @ftp_mlsd($ftp, $remote);

		if (is_array($listed))
		{
			foreach ($listed as $entry)
			{
				$name = $entry['name'] ?? null;
				$type = $entry['type'] ?? null;

				if (is_string($name) AND ($type === 'file' OR $type === 'dir'))
				{
					$entries[basename($name)] = ($type === 'dir');
				}
			}

			return $entries;
		}

		// A server without it gives names only: what has no size is a directory
		foreach (@ftp_nlist($ftp, $remote) ?: [] as $name)
		{
			$name = basename($name);

			if ($name !== '.' AND $name !== '..')
			{
				$entries[$name] = ftp_size($ftp, rtrim($remote, '/').'/'.$name) < 0;
			}
		}

		return $entries;
	}

	/**
	 * Sends an open file to the server and closes it.
	 *
	 * @param   string    $path  where to store it
	 * @param   resource  $file  open file to send
	 * @return  static
	 * @throws  UB_Storage_Exception  when it cannot be stored
	 */
	protected function _send(string $path, mixed $file): static
	{
		$remote = $this->_remote($path);
		$ftp    = $this->_connection();

		$this->_make_directory(dirname($remote));

		// Sent under another name and then moved in its place: a reader
		// never sees half a file
		$temporary = $remote.'.'.bin2hex(random_bytes(6)).'.tmp';

		$done = (@ftp_fput($ftp, $temporary, $file, FTP_BINARY) AND @ftp_rename($ftp, $temporary, $remote));

		fclose($file);

		if ( ! $done)
		{
			@ftp_delete($ftp, $temporary);

			throw new UB_Storage_Exception('Unable to store the file :path', [':path' => $path]);
		}

		return $this;
	}

	/**
	 * Makes a directory of the server, with those above it.
	 *
	 * @param   string  $directory  path on the server
	 * @return  void
	 */
	protected function _make_directory(string $directory): void
	{
		$ftp  = $this->_connection();
		$path = str_starts_with($directory, '/') ? '/' : '';

		foreach (array_filter(explode('/', $directory), fn ($part) => $part !== '' AND $part !== '.') as $part)
		{
			$path .= $part;

			// Fails when it is there already, which is fine
			@ftp_mkdir($ftp, $path);

			$path .= '/';
		}
	}

	/**
	 * Returns the path on the server of a path of the storage: the
	 * root of the settings, then the path.
	 *
	 * @param   string  $path          the path as it was given
	 * @param   bool    $may_be_empty  true for a directory, where "" is the root
	 * @return  string
	 * @throws  UB_Storage_Exception  when the path is not allowed
	 */
	protected function _remote(string $path, bool $may_be_empty = false): string
	{
		$root = rtrim(str_replace('\\', '/', $this->_setting('root')), '/');
		$path = $this->_path($path, $may_be_empty);

		if ($root === '')
			return ($path === '') ? '.' : $path;

		return ($path === '') ? $root : $root.'/'.$path;
	}

	/**
	 * Returns the connection to the server, which is made and logged in
	 * the first time.
	 *
	 * @return  FTP\Connection
	 * @throws  UB_Storage_Exception  without the ftp extension, or when the server refuses
	 */
	protected function _connection(): FTP\Connection
	{
		if ($this->_ftp !== null)
			return $this->_ftp;

		if ( ! function_exists('ftp_connect'))
			throw new UB_Storage_Exception('The FTP storage needs the ftp extension of PHP');

		$host    = $this->_setting('host');
		$ssl     = ! empty($this->_config['ssl']);
		$port    = (int) $this->_setting('port', '21');
		$timeout = (int) $this->_setting('timeout', '30');

		$ftp = $ssl ? @ftp_ssl_connect($host, $port, $timeout) : @ftp_connect($host, $port, $timeout);

		if ($ftp === false)
			throw new UB_Storage_Exception('Unable to connect to the FTP server :host', [':host' => $host]);

		// The password is never part of a message
		if ( ! @ftp_login($ftp, $this->_setting('username', 'anonymous'), $this->_setting('password')))
		{
			@ftp_close($ftp);

			throw new UB_Storage_Exception('The FTP server :host refused the login of :user', [':host' => $host, ':user' => $this->_setting('username', 'anonymous')]);
		}

		// Passive mode works through firewalls, and is what servers expect today
		ftp_pasv($ftp, ($this->_config['passive'] ?? true) !== false);

		return $this->_ftp = $ftp;
	}

}
