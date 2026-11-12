<?php
/**
 * Loads classes that are not written the Kohana way: libraries of
 * others (PHPMailer, HTML Purifier, TCPDF), and helpers and classes of
 * your own that live outside "classes".
 *
 * config/ub_autoload.php says where each one is, in four ways:
 *
 *  - psr4:      a namespace and its directory (most libraries of today)
 *  - psr0:      a prefix and its directory (older libraries, Some_Class)
 *  - classmap:  a class and its file (libraries with no rule at all)
 *  - files:     files that are included in every request (functions)
 *
 * A path that is not absolute is looked for in the "vendor" directories
 * of the application and of every module, in the order of the
 * [cascading filesystem](kohana/files).
 *
 * Nothing is read from the disk for a library until one of its classes
 * is used. Only the "files" are included at once.
 *
 * @package    UB_Autoload
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Autoload {

	/**
	 * @var  array<string, string[]>  PSR-4: namespace with "\" at the end => paths as given
	 */
	protected static array $_psr4 = [];

	/**
	 * @var  array<string, string[]>  PSR-0: prefix => paths as given
	 */
	protected static array $_psr0 = [];

	/**
	 * @var  array<string, string>  class => file as given
	 */
	protected static array $_classmap = [];

	/**
	 * @var  array<string, bool>  the "files" that were included, by their full path
	 */
	protected static array $_included = [];

	/**
	 * @var  string[]|null  the vendor directories, once they were looked for
	 */
	protected static ?array $_vendor_paths = null;

	/**
	 * @var  bool  was the loader given to PHP?
	 */
	protected static bool $_registered = false;

	/**
	 * Starts the loader with what config/ub_autoload.php says. Called by
	 * the init.php of the module; calling it again does no harm.
	 *
	 * @return  void
	 * @throws  Kohana_Exception  when a file of "files" is not found
	 */
	public static function init(): void
	{
		if ( ! static::$_registered)
		{
			// After the loader of Kohana: the classes of the framework
			// and of the application are the ones asked for most
			spl_autoload_register(static function (string $class): void
			{
				static::load($class);
			});

			static::$_registered = true;
		}

		$config = static::get_config();

		foreach (static::_pairs($config, 'psr4') as $prefix => $paths)
		{
			static::add_psr4($prefix, $paths);
		}

		foreach (static::_pairs($config, 'psr0') as $prefix => $paths)
		{
			static::add_psr0($prefix, $paths);
		}

		foreach (static::_pairs($config, 'classmap') as $class => $files)
		{
			static::add_class_map($class, $files[0]);
		}

		foreach (static::_list($config['files'] ?? []) as $file)
		{
			static::add_file($file);
		}
	}

	/**
	 * Says where the classes of a namespace are (PSR-4): what follows the
	 * namespace in the name of a class is its path below the directory.
	 *
	 *     // PHPMailer\PHPMailer\SMTP is vendor/phpmailer/src/SMTP.php
	 *     UB_Autoload::add_psr4('PHPMailer\\PHPMailer\\', 'phpmailer/src');
	 *
	 * @param   string           $prefix  the namespace
	 * @param   string|string[]  $paths   its directory, or several
	 * @return  void
	 */
	public static function add_psr4(string $prefix, string|array $paths): void
	{
		$prefix = trim($prefix, '\\').'\\';

		static::$_psr4[$prefix] = array_values(array_unique(array_merge(static::$_psr4[$prefix] ?? [], static::_list($paths))));
	}

	/**
	 * Says where the classes with a prefix are (PSR-0): the whole name of
	 * the class is its path below the directory, with every "\" and
	 * every "_" of the class name a directory.
	 *
	 *     // HTMLPurifier_Config is vendor/htmlpurifier/library/HTMLPurifier/Config.php
	 *     UB_Autoload::add_psr0('HTMLPurifier', 'htmlpurifier/library');
	 *
	 * @param   string           $prefix  how the names of the classes start
	 * @param   string|string[]  $paths   the directory, or several
	 * @return  void
	 */
	public static function add_psr0(string $prefix, string|array $paths): void
	{
		$prefix = ltrim($prefix, '\\');

		static::$_psr0[$prefix] = array_values(array_unique(array_merge(static::$_psr0[$prefix] ?? [], static::_list($paths))));
	}

	/**
	 * Says in which file a class is.
	 *
	 *     UB_Autoload::add_class_map('TCPDF', 'tcpdf/tcpdf.php');
	 *
	 * @param   string  $class  name of the class, with its namespace
	 * @param   string  $file   the file
	 * @return  void
	 */
	public static function add_class_map(string $class, string $file): void
	{
		static::$_classmap[ltrim($class, '\\')] = $file;
	}

	/**
	 * Includes a file now, once: for files of functions, which PHP
	 * cannot load when they are needed.
	 *
	 *     UB_Autoload::add_file('helpers/money.php');
	 *
	 * @param   string  $file  the file
	 * @return  void
	 * @throws  Kohana_Exception  when the file is not found
	 */
	public static function add_file(string $file): void
	{
		$path = static::_find($file, false)[0] ?? null;

		if ($path === null)
			throw new Kohana_Exception(
				'ub_autoload: the file :file was not found. A path that is not absolute is looked for in the vendor directories: :paths',
				[':file' => $file, ':paths' => static::_vendor_list()]
			);

		if ( ! isset(static::$_included[$path]))
		{
			static::$_included[$path] = true;

			static::_require($path);
		}
	}

	/**
	 * Loads a class, when the config knows where it is. PHP calls this
	 * for every class that the loader of Kohana did not find.
	 *
	 * @param   string  $class  name of the class, with its namespace
	 * @return  bool    was it loaded?
	 * @throws  Kohana_Exception  when the config names a path that is not there
	 */
	public static function load(string $class): bool
	{
		$class = ltrim($class, '\\');

		// The name becomes part of a path. Code can ask for a class by a
		// name that came from a visitor (class_exists($name)): only what
		// can be the name of a class is looked for
		if ( ! preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/D', $class))
			return false;

		if (isset(static::$_classmap[$class]))
			return static::_load_mapped($class);

		return (static::_load_psr4($class) OR static::_load_psr0($class));
	}

	/**
	 * Returns the vendor directories in which paths are looked for: the
	 * one of the application first, then those of the modules.
	 *
	 * @return  string[]
	 */
	public static function get_vendor_paths(): array
	{
		if (static::$_vendor_paths === null)
		{
			static::$_vendor_paths = [];

			foreach (Kohana::include_paths() as $path)
			{
				if (is_dir($path.'vendor'))
				{
					static::$_vendor_paths[] = $path.'vendor'.DIRECTORY_SEPARATOR;
				}
			}
		}

		return static::$_vendor_paths;
	}

	/**
	 * Returns what config/ub_autoload.php says, of all modules together.
	 *
	 * @return  array<int|string, mixed>
	 */
	public static function get_config(): array
	{
		$config = Kohana::$config->load('ub_autoload');

		return ($config instanceof Config_Group) ? $config->as_array() : [];
	}

	/**
	 * Loads a class of the class map.
	 *
	 * @param   string  $class  a class that is in the map
	 * @return  bool
	 * @throws  Kohana_Exception  when its file is not found
	 */
	protected static function _load_mapped(string $class): bool
	{
		$path = static::_find(static::$_classmap[$class], false)[0] ?? null;

		if ($path === null)
			throw new Kohana_Exception(
				'ub_autoload: the file :file of the class :class was not found. A path that is not absolute is looked for in the vendor directories: :paths',
				[':file' => static::$_classmap[$class], ':class' => $class, ':paths' => static::_vendor_list()]
			);

		static::_require($path);

		return true;
	}

	/**
	 * Loads a class by the PSR-4 rule, when its namespace is known.
	 *
	 * @param   string  $class  name of the class
	 * @return  bool
	 */
	protected static function _load_psr4(string $class): bool
	{
		foreach (static::$_psr4 as $prefix => $paths)
		{
			if ( ! str_starts_with($class, $prefix))
				continue;

			// What follows the namespace is the path
			$file = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))).'.php';

			if (static::_require_from(static::_directories($prefix, $paths), $file))
				return true;
		}

		return false;
	}

	/**
	 * Loads a class by the PSR-0 rule, when its prefix is known.
	 *
	 * @param   string  $class  name of the class
	 * @return  bool
	 */
	protected static function _load_psr0(string $class): bool
	{
		// The namespace is a path as it is; in the name of the class
		// itself every "_" is a directory as well
		$split     = (int) strrpos($class, '\\');
		$namespace = substr($class, 0, $split);
		$name      = ltrim(substr($class, $split), '\\');

		$file = str_replace('\\', DIRECTORY_SEPARATOR, $namespace)
			.(($namespace === '') ? '' : DIRECTORY_SEPARATOR)
			.str_replace('_', DIRECTORY_SEPARATOR, $name).'.php';

		foreach (static::$_psr0 as $prefix => $paths)
		{
			if (str_starts_with($class, $prefix) AND static::_require_from(static::_directories($prefix, $paths), $file))
				return true;
		}

		return false;
	}

	/**
	 * Includes a file from the first of some directories that has it.
	 *
	 * @param   string[]  $directories  full paths, with the separator at the end
	 * @param   string    $file         path of the file below them
	 * @return  bool      was it there?
	 */
	protected static function _require_from(array $directories, string $file): bool
	{
		foreach ($directories as $directory)
		{
			if (is_file($directory.$file))
			{
				static::_require($directory.$file);

				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the directories of a prefix as full paths.
	 *
	 * @param   string    $prefix  the prefix, for the error text
	 * @param   string[]  $paths   the paths as given
	 * @return  string[]
	 * @throws  Kohana_Exception  when one of them is nowhere
	 */
	protected static function _directories(string $prefix, array $paths): array
	{
		$directories = [];

		foreach ($paths as $path)
		{
			$found = static::_find($path, true);

			if ($found === [])
				throw new Kohana_Exception(
					'ub_autoload: the directory :path of :prefix was not found. A path that is not absolute is looked for in the vendor directories: :paths',
					[':path' => $path, ':prefix' => $prefix, ':paths' => static::_vendor_list()]
				);

			$directories = array_merge($directories, $found);
		}

		return $directories;
	}

	/**
	 * Returns the full paths a given path stands for: itself when it is
	 * absolute, otherwise wherever it is in the vendor directories.
	 *
	 * @param   string  $path       path as given
	 * @param   bool    $directory  look for a directory (true) or a file (false)
	 * @return  string[]  directories with the separator at the end
	 */
	protected static function _find(string $path, bool $directory): array
	{
		$found = [];

		// An absolute path stands for itself
		foreach (static::_is_absolute($path) ? [''] : static::get_vendor_paths() as $vendor)
		{
			$candidate = $vendor.$path;

			if ($directory AND is_dir($candidate))
			{
				$found[] = rtrim($candidate, '/\\').DIRECTORY_SEPARATOR;
			}
			elseif ( ! $directory AND is_file($candidate))
			{
				$found[] = $candidate;
			}
		}

		return $found;
	}

	/**
	 * Tells whether a path starts at the root of the disk ("/var/lib",
	 * "C:\lib") and not in a vendor directory.
	 *
	 * @param   string  $path  a path
	 * @return  bool
	 */
	protected static function _is_absolute(string $path): bool
	{
		return (bool) preg_match('#^(/|\\\\|[a-zA-Z]:[/\\\\])#', $path);
	}

	/**
	 * Includes a file with nothing of this class in its reach.
	 *
	 * @param   string  $path  full path of the file
	 * @return  void
	 */
	protected static function _require(string $path): void
	{
		(static function () use ($path): void
		{
			require_once $path;
		})();
	}

	/**
	 * Returns the vendor directories as text, for an error message.
	 *
	 * @return  string
	 */
	protected static function _vendor_list(): string
	{
		$paths = array_map(Debug::path(...), static::get_vendor_paths());

		return ($paths === []) ? '(there is none)' : implode(', ', $paths);
	}

	/**
	 * Returns one part of the config as name => list of paths.
	 *
	 * @param   array<int|string, mixed>  $config  the config
	 * @param   string                    $key     "psr4", "psr0" or "classmap"
	 * @return  array<string, non-empty-list<string>>
	 */
	protected static function _pairs(array $config, string $key): array
	{
		$pairs = [];

		foreach (is_array($config[$key] ?? null) ? $config[$key] : [] as $name => $paths)
		{
			$paths = static::_list($paths);

			if ($paths !== [])
			{
				$pairs[ (string) $name] = $paths;
			}
		}

		return $pairs;
	}

	/**
	 * Returns the texts of a value that is one path or a list of them.
	 *
	 * @param   mixed  $paths  a path, or several
	 * @return  list<string>
	 */
	protected static function _list(mixed $paths): array
	{
		return array_values(array_filter( (array) $paths, fn (mixed $path): bool => (is_string($path) AND $path !== '')));
	}

}
