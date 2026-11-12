<?php
/**
 * Keeps files in a directory of the server.
 *
 *     // config/ub_storage.php
 *     'local' => [
 *         'driver' => 'local',
 *         'root'   => APPPATH.'files',
 *     ],
 *
 * The directory is made when it is not there. Keep it outside the
 * document root, or files that are stored can be asked for by address.
 *
 * @package    UB_Storage
 * @category   Drivers
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Storage_Local extends UB_Storage {

	/**
	 * @var  string|null  The real path of the root directory, once it is known
	 */
	protected ?string $_root = null;

	public function put(string $path, string $content): static
	{
		$target = $this->_target($path);

		// Written beside the file and then moved in its place: a reader
		// never sees half a file
		$temporary = $target.'.'.bin2hex(random_bytes(6)).'.tmp';

		if (@file_put_contents($temporary, $content) === false OR ! @rename($temporary, $target))
		{
			@unlink($temporary);

			throw new UB_Storage_Exception('Unable to store the file :path', [':path' => $path]);
		}

		return $this;
	}

	public function put_file(string $path, string $file): static
	{
		$from      = $this->_read($file);
		$target    = $this->_target($path);
		$temporary = $target.'.'.bin2hex(random_bytes(6)).'.tmp';

		try
		{
			$to = $this->_write($temporary);

			stream_copy_to_stream($from, $to);

			fclose($to);

			if ( ! @rename($temporary, $target))
				throw new UB_Storage_Exception('Unable to store the file :path', [':path' => $path]);
		}
		catch (Throwable $e)
		{
			@unlink($temporary);

			throw $e;
		}
		finally
		{
			fclose($from);
		}

		return $this;
	}

	public function get(string $path): string
	{
		$content = @file_get_contents($this->_existing($path));

		if ($content === false)
			throw new UB_Storage_Exception('Unable to read the file :path', [':path' => $path]);

		return $content;
	}

	public function get_to(string $path, string $file): string
	{
		$from = $this->_read($this->_existing($path));
		$to   = $this->_write($file);

		stream_copy_to_stream($from, $to);

		fclose($from);
		fclose($to);

		return $file;
	}

	public function exists(string $path): bool
	{
		$file = $this->_root().DIRECTORY_SEPARATOR.$this->_path($path);

		return is_file($file) AND $this->_inside($file);
	}

	public function delete(string $path): bool
	{
		if ( ! $this->exists($path))
			return false;

		if ( ! @unlink($this->_existing($path)))
			throw new UB_Storage_Exception('Unable to remove the file :path', [':path' => $path]);

		return true;
	}

	public function size(string $path): int
	{
		return (int) filesize($this->_existing($path));
	}

	public function modified(string $path): int
	{
		return (int) filemtime($this->_existing($path));
	}

	public function files(string $directory = '', bool $deep = false): array
	{
		$directory = $this->_path($directory, true);
		$start     = $this->_root().(($directory === '') ? '' : DIRECTORY_SEPARATOR.$directory);

		if ( ! is_dir($start) OR ! $this->_inside($start))
			return [];

		$found = $deep
			? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($start, FilesystemIterator::SKIP_DOTS))
			: new FilesystemIterator($start, FilesystemIterator::SKIP_DOTS);

		$files = [];

		/** @var SplFileInfo $file */
		foreach ($found as $file)
		{
			// Files that are being written, and links, are not part of the storage
			if ($file->isFile() AND ! $file->isLink() AND ! str_ends_with($file->getFilename(), '.tmp'))
			{
				$files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($this->_root()) + 1));
			}
		}

		sort($files);

		return $files;
	}

	/**
	 * Returns the real path of the root directory, which is made when
	 * it is not there.
	 *
	 * @return  string
	 * @throws  UB_Storage_Exception  when it is not set or cannot be made
	 */
	protected function _root(): string
	{
		if ($this->_root === null)
		{
			$root = $this->_setting('root');

			if ($root === '' OR ( ! is_dir($root) AND ! @mkdir($root, 0755, true) AND ! is_dir($root)))
				throw new UB_Storage_Exception('The directory of the storage is not set or cannot be made: :root', [':root' => Debug::path($root)]);

			$this->_root = (string) realpath($root);
		}

		return $this->_root;
	}

	/**
	 * Tells whether a file or directory that exists is really inside the
	 * root: a link in the storage could lead out of it.
	 *
	 * @param   string  $path  path on the server
	 * @return  bool
	 */
	protected function _inside(string $path): bool
	{
		$real = (string) realpath($path);

		return $real === $this->_root() OR str_starts_with($real, $this->_root().DIRECTORY_SEPARATOR);
	}

	/**
	 * Returns the path on the server of a file that is in the storage.
	 *
	 * @param   string  $path  the file
	 * @return  string
	 * @throws  UB_Storage_Exception  when the file is not there
	 */
	protected function _existing(string $path): string
	{
		if ( ! $this->exists($path))
			throw new UB_Storage_Exception('The file :path is not in the storage', [':path' => $path]);

		return $this->_root().DIRECTORY_SEPARATOR.$this->_path($path);
	}

	/**
	 * Returns the path on the server where a file is to be stored, and
	 * makes its directory when that is missing.
	 *
	 * @param   string  $path  the file
	 * @return  string
	 * @throws  UB_Storage_Exception  when the directory cannot be made or leads out of the storage
	 */
	protected function _target(string $path): string
	{
		$target    = $this->_root().DIRECTORY_SEPARATOR.$this->_path($path);
		$directory = dirname($target);

		// The part of the path that is there already must be inside the
		// storage, before anything is made under it
		for ($existing = $directory; ! is_dir($existing); $existing = dirname($existing));

		if ( ! $this->_inside($existing))
			throw new UB_Storage_Exception('The path ":path" leads out of the storage', [':path' => $path]);

		if ( ! is_dir($directory) AND ! @mkdir($directory, 0755, true) AND ! is_dir($directory))
			throw new UB_Storage_Exception('Unable to make the directory of :path', [':path' => $path]);

		return $target;
	}

}
