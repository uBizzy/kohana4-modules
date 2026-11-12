<?php
/**
 * Files kept somewhere: on the disk of the server, on an FTP server or
 * in S3. Whatever the place, the methods are the same, so code that
 * stores an upload does not have to know where it goes.
 *
 *     $storage = UB_Storage::instance();          // the default place
 *     $storage = UB_Storage::instance('backups'); // another one of config/ub_storage.php
 *
 *     $storage->put('invoices/2026/17.pdf', $pdf);
 *     $pdf = $storage->get('invoices/2026/17.pdf');
 *
 *     $storage->exists('invoices/2026/17.pdf');   // true
 *     $storage->files('invoices/2026');           // ['invoices/2026/17.pdf']
 *     $storage->delete('invoices/2026/17.pdf');
 *
 * A path is always written with "/" and is relative to the root of the
 * place. It can never lead out of it: "..", a path from the root of the
 * disk and control characters are refused, in every driver.
 *
 * The places are set up in config/ub_storage.php. The drivers are
 * [UB_Storage_Local], [UB_Storage_FTP] and [UB_Storage_S3].
 *
 * @package    UB_Storage
 * @category   Base
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
abstract class UB_Storage {

	/**
	 * @var  string|null  Name of the place that instance() gives without a name;
	 *                    the "default" of config/ub_storage.php when null
	 */
	public static ?string $default = null;

	/**
	 * @var  array<string, UB_Storage>  The places that were asked for, by name
	 */
	protected static array $_instances = [];

	/**
	 * @var  array<string, mixed>  The settings of this place
	 */
	protected array $_config;

	/**
	 * Returns a place of config/ub_storage.php. The same object is given
	 * each time, so a connection is made once.
	 *
	 * @param   string|null  $name  name of the place, the default one when null
	 * @return  UB_Storage
	 * @throws  UB_Storage_Exception  when the place or its driver is not there
	 */
	public static function instance(?string $name = null): UB_Storage
	{
		$default = Kohana::$config->load('ub_storage.default');

		$name ??= static::$default ?? (is_string($default) ? $default : 'local');

		if ( ! isset(static::$_instances[$name]))
		{
			$config = Kohana::$config->load('ub_storage.disks.'.$name);

			if ( ! is_array($config))
				throw new UB_Storage_Exception('The storage ":name" is not in config/ub_storage.php', [':name' => $name]);

			/** @var array<string, mixed> $config  the settings of the place, by name */
			static::$_instances[$name] = static::factory($config);
		}

		return static::$_instances[$name];
	}

	/**
	 * Makes a place from settings, without the config file.
	 *
	 *     $storage = UB_Storage::factory(['driver' => 'local', 'root' => APPPATH.'files']);
	 *
	 * @param   array<string, mixed>  $config  settings; "driver" names the driver
	 * @return  UB_Storage
	 * @throws  UB_Storage_Exception  when there is no such driver
	 */
	public static function factory(array $config): UB_Storage
	{
		$driver = is_string($config['driver'] ?? null) ? $config['driver'] : '';
		$class  = 'UB_Storage_'.(strtolower($driver) === 'local' ? 'Local' : strtoupper($driver));

		if ( ! ctype_alnum($driver) OR ! class_exists($class) OR ! is_subclass_of($class, 'UB_Storage'))
			throw new UB_Storage_Exception('There is no storage driver ":driver"', [':driver' => $driver]);

		return new $class($config);
	}

	/**
	 * @param   array<string, mixed>  $config  the settings of the place
	 */
	public function __construct(array $config)
	{
		$this->_config = $config;
	}

	/**
	 * The settings are not shown when the object is dumped: they hold
	 * passwords and keys.
	 *
	 * @return  array<string, string>
	 */
	public function __debugInfo(): array
	{
		return ['driver' => static::class];
	}

	/**
	 * Stores text as a file. A file that is there is replaced; the
	 * directories of the path are made when they are missing.
	 *
	 * @param   string  $path     where to store it
	 * @param   string  $content  the content
	 * @return  static
	 * @throws  UB_Storage_Exception  when it cannot be stored
	 */
	abstract public function put(string $path, string $content): static;

	/**
	 * Stores a file of the server, without reading it into memory.
	 *
	 *     $storage->put_file('uploads/'.$name, $_FILES['photo']['tmp_name']);
	 *
	 * @param   string  $path  where to store it
	 * @param   string  $file  path of the file on the server
	 * @return  static
	 * @throws  UB_Storage_Exception  when the file cannot be read or stored
	 */
	abstract public function put_file(string $path, string $file): static;

	/**
	 * Returns the content of a file.
	 *
	 * @param   string  $path  the file
	 * @return  string
	 * @throws  UB_Storage_Exception  when the file is not there or cannot be read
	 */
	abstract public function get(string $path): string;

	/**
	 * Copies a file to the server, without reading it into memory.
	 *
	 *     $storage->get_to('exports/2026.zip', APPPATH.'cache/2026.zip');
	 *
	 * @param   string  $path  the file
	 * @param   string  $file  where to write it on the server
	 * @return  string  the path on the server
	 * @throws  UB_Storage_Exception  when the file is not there or cannot be written
	 */
	abstract public function get_to(string $path, string $file): string;

	/**
	 * Tells whether a file is there.
	 *
	 * @param   string  $path  the file
	 * @return  bool
	 */
	abstract public function exists(string $path): bool;

	/**
	 * Removes a file.
	 *
	 * @param   string  $path  the file
	 * @return  bool  false when there was no such file
	 * @throws  UB_Storage_Exception  when it is there and cannot be removed
	 */
	abstract public function delete(string $path): bool;

	/**
	 * Returns the size of a file in bytes.
	 *
	 * @param   string  $path  the file
	 * @return  int
	 * @throws  UB_Storage_Exception  when the file is not there
	 */
	abstract public function size(string $path): int;

	/**
	 * Returns when a file was last changed, as a UNIX timestamp.
	 *
	 * @param   string  $path  the file
	 * @return  int
	 * @throws  UB_Storage_Exception  when the file is not there
	 */
	abstract public function modified(string $path): int;

	/**
	 * Lists the files of a directory, as paths from the root of the
	 * place, in the order of their names.
	 *
	 *     $storage->files('invoices');         // the files of "invoices"
	 *     $storage->files('invoices', true);   // and of its sub directories
	 *     $storage->files();                   // the files at the top
	 *
	 * @param   string  $directory  the directory, the root when empty
	 * @param   bool    $deep       also list what is in sub directories
	 * @return  array<int, string>
	 */
	abstract public function files(string $directory = '', bool $deep = false): array;

	/**
	 * Returns an address at which the file can be fetched for a while,
	 * by anybody who has the address. Only S3 can do this.
	 *
	 * @param   string  $path     the file
	 * @param   int     $seconds  how long the address works
	 * @return  string
	 * @throws  UB_Storage_Exception  with a driver that cannot do it
	 */
	public function temporary_url(string $path, int $seconds = 3600): string
	{
		throw new UB_Storage_Exception(':driver cannot make temporary addresses', [':driver' => static::class]);
	}

	/**
	 * Returns a setting of this place as text.
	 *
	 * @param   string  $key      name of the setting
	 * @param   string  $default  value when it is not set
	 * @return  string
	 */
	protected function _setting(string $key, string $default = ''): string
	{
		$value = $this->_config[$key] ?? $default;

		return is_scalar($value) ? (string) $value : $default;
	}

	/**
	 * Returns a path as every driver uses it: with "/", without one at
	 * its start or end. A path that could lead out of the place is refused.
	 *
	 * @param   string  $path          the path as it was given
	 * @param   bool    $may_be_empty  true for a directory, where "" is the root
	 * @return  string
	 * @throws  UB_Storage_Exception  when the path is not allowed
	 */
	protected function _path(string $path, bool $may_be_empty = false): string
	{
		$given = str_replace('\\', '/', $path);
		$clean = trim($given, '/');
		$parts = ($clean === '') ? [] : explode('/', $clean);

		if (str_starts_with($given, '/')                              // from the root of the disk
			OR preg_match('/^[a-z]:/i', $clean) === 1                // a Windows drive
			OR preg_match('/[\x00-\x1F\x7F]/', $clean) === 1         // control characters
			OR array_intersect(['..', '.', ''], $parts) !== []       // climbing, or an empty part
			OR ($clean === '' AND ! $may_be_empty))
			throw new UB_Storage_Exception('The path ":path" is not allowed: it is empty or could lead out of the storage', [':path' => $path]);

		return $clean;
	}

	/**
	 * Opens a file of the server to read it and says so when that fails.
	 *
	 * @param   string  $file  path of the file on the server
	 * @return  resource
	 * @throws  UB_Storage_Exception  when the file cannot be read
	 */
	protected function _read(string $file): mixed
	{
		$handle = (is_file($file) AND is_readable($file)) ? @fopen($file, 'rb') : false;

		if ($handle === false)
			throw new UB_Storage_Exception('Unable to read the file :file', [':file' => Debug::path($file)]);

		return $handle;
	}

	/**
	 * Opens a file of the server to write it and says so when that fails.
	 *
	 * @param   string  $file  path of the file on the server
	 * @return  resource
	 * @throws  UB_Storage_Exception  when the file cannot be written
	 */
	protected function _write(string $file): mixed
	{
		$handle = @fopen($file, 'wb');

		if ($handle === false)
			throw new UB_Storage_Exception('Unable to write the file :file', [':file' => Debug::path($file)]);

		return $handle;
	}

}
