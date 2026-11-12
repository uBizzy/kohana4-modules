# Storage

The Storage module keeps files somewhere: on the disk of the server, on an FTP server, or in S3. Whatever the place, the methods are the same, so the code that stores an upload does not have to know where it goes, and the place can change later without the code.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_storage' => MODPATH.'ub_storage',
]);
```

Copy `modules/ub_storage/config/ub_storage.php` to `application/config/` and set up the places you use: see [Drivers](drivers).

## Storing and reading

```php
$storage = UB_Storage::instance();            // the default place
$backups = UB_Storage::instance('backups');   // another one of the config

$storage->put('invoices/2026/17.pdf', $pdf);

$pdf = $storage->get('invoices/2026/17.pdf');
```

- A file that is there is replaced.
- The directories of the path are made when they are missing.
- A file is never seen half written: it appears when it is complete.

## Large files

`put()` and `get()` hold the whole content in memory. For an upload or an export, give a file of the server and the content is passed on piece by piece:

```php
// An upload, straight from its temporary file
$storage->put_file('photos/'.$name, $_FILES['photo']['tmp_name']);

// A stored file, to a file of the server
$storage->get_to('exports/2026.zip', APPPATH.'cache/2026.zip');
```

## Asking about files

```php
$storage->exists('invoices/2026/17.pdf');     // true or false
$storage->size('invoices/2026/17.pdf');       // bytes
$storage->modified('invoices/2026/17.pdf');   // UNIX timestamp

$storage->delete('invoices/2026/17.pdf');     // false when there was no such file
```

```php
$storage->files('invoices');         // ['invoices/readme.txt']
$storage->files('invoices', true);   // also what is in sub directories
$storage->files();                   // the files at the top
```

The list holds whole paths, from the root of the storage, in the order of their names. A directory that is not there has no files.

## When something goes wrong

Every failure is a `UB_Storage_Exception`: a file that is not there, a server that cannot be reached, a login that is refused.

```php
try
{
	$pdf = $storage->get('invoices/2026/17.pdf');
}
catch (UB_Storage_Exception $e)
{
	// "The file invoices/2026/17.pdf is not in the storage"
}
```

Keys and passwords are never part of a message, and are not shown when a storage object is dumped.

## Paths

A path is written with `/` and starts at the root of the storage. It can never lead out of it, in any driver:

| Path | Result |
|---|---|
| `invoices/2026/17.pdf` | fine |
| `../config.php`, `docs/../../x` | refused |
| `/etc/passwd`, `C:\x` | refused |
| a path with a control character or a line break | refused |
| `docs//x`, `./x` | refused |

[!!] This makes it safe to build a path from something a visitor sent, as far as *where* the file lands. *What* is stored is still yours to check: see [Receiving an upload](../kohana/helpers/files#receiving-an-upload) for the type and size of uploads.

## A link to a private file

Files in S3 are private. To let somebody download one, hand out an address that works for a while and needs no login:

```php
// Works for ten minutes
$url = UB_Storage::instance('s3')->temporary_url('invoices/2026/17.pdf', 600);

$this->redirect($url);
```

The address is for that one file, and stops working after the time given (a week at most). The other drivers cannot do this and say so.

## Without the config file

```php
$storage = UB_Storage::factory([
	'driver' => 'local',
	'root'   => APPPATH.'files',
]);
```
