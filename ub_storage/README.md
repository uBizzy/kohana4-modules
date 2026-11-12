# Storage module

Files kept on the disk of the server, on an FTP server or in S3, behind the same few methods. Code that stores an upload does not have to know where it goes, and the place can change without the code.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_storage' => MODPATH.'ub_storage',
]);
```

```php
$storage = UB_Storage::instance();

$storage->put('invoices/2026/17.pdf', $pdf);
$pdf = $storage->get('invoices/2026/17.pdf');
```

- Drivers: `local`, `ftp` (with FTPS) and `s3` (Amazon S3 and the services that speak its language).
- A path can never lead out of the storage, in any driver.
- S3 needs no library of Amazon: requests are signed by the module itself.
- Keys and passwords are never part of an error message or a dump.

The places are set up in `config/ub_storage.php`. The full documentation is in `guide/ub_storage/` and in the user guide of your application.
