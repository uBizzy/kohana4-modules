# Autoload module

Loads classes that are not written the Kohana way: libraries of others (PHPMailer, HTML Purifier, TCPDF, ...), and helpers and classes of your own that live outside `classes`.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_autoload' => DOCROOT.'extra/ub_autoload',
]);
```

```php
// application/config/ub_autoload.php
return [
	'psr4' => [
		'PHPMailer\\PHPMailer\\' => 'phpmailer/src',
	],
	'psr0' => [
		'HTMLPurifier' => 'htmlpurifier/library',
	],
	'classmap' => [
		'TCPDF' => 'tcpdf/tcpdf.php',
	],
	'files' => [
		'helpers/money.php',
	],
];
```

- Four ways to say where classes are, with the words of `composer.json`: `psr4`, `psr0`, `classmap`, `files`.
- Paths are looked for in the `vendor` directory of the application and of every module, in the order of the cascading filesystem; absolute paths work too.
- A module can bring its library and the config for it.
- Nothing is read from the disk for a library until one of its classes is used.
- A path that does not exist is an error with a clear text.
- No library is shipped with the module: each has its own licence and its own updates.

The full documentation, with the lines for PHPMailer, HTML Purifier and TCPDF, is in `guide/ub_autoload/` and in the user guide of your application.
