# Autoload

Kohana finds a class by its name: `Model_Article` is `classes/Model/Article.php`. Libraries of others are not written that way. The Autoload module tells PHP where their classes are, so you use them like any class of your own:

```php
$mail = new PHPMailer\PHPMailer\PHPMailer;
```

It also loads helpers and classes of your own that live outside `classes`.

## Setting it up

```php
// application/bootstrap.php
Kohana::modules([
	'ub_autoload' => MODPATH.'ub_autoload',
]);
```

1. Put the library in a `vendor` directory: `application/vendor/phpmailer/`.
2. Copy `modules/ub_autoload/config/ub_autoload.php` to `application/config/` and say where the classes are:

```php
// application/config/ub_autoload.php
return [
	'psr4' => [
		'PHPMailer\\PHPMailer\\' => 'phpmailer/src',
	],
];
```

That is all. Ready-made lines for well-known libraries are in [Libraries](libraries).

## The four ways to say where classes are

Which one a library needs is written in its `composer.json`, under `"autoload"`: the config of this module uses the same four words.

### psr4: a namespace and its directory

What most libraries of today use. What follows the namespace in the name of a class is its path below the directory.

```php
'psr4' => [
	// PHPMailer\PHPMailer\SMTP  is  vendor/phpmailer/src/SMTP.php
	'PHPMailer\\PHPMailer\\' => 'phpmailer/src',
],
```

[!!] In PHP text, write the `\` of a namespace twice: `'PHPMailer\\PHPMailer\\'`.

### psr0: a prefix and its directory

For older libraries, whose classes are named `Some_Class`. The whole name is the path: every `\` and every `_` is a directory.

```php
'psr0' => [
	// HTMLPurifier_Config  is  vendor/htmlpurifier/library/HTMLPurifier/Config.php
	'HTMLPurifier' => 'htmlpurifier/library',
],
```

### classmap: a class and its file

For libraries that follow no rule, or keep everything in one file.

```php
'classmap' => [
	'TCPDF' => 'tcpdf/tcpdf.php',
],
```

### files: included in every request

PHP can load a class when it is needed, but not a function. Files of functions, and files a library asks to be included at the start, are listed here.

```php
'files' => [
	'helpers/money.php',
],
```

[!!] Each of these files is read in every request, used or not. Keep the list short.

## Where the files are looked for

A path that is not absolute is looked for in the `vendor` directory of the application and of every enabled module, in the order of the [cascading filesystem](../kohana/files):

```text
application/vendor/
modules/shop/vendor/
modules/blog/vendor/
```

- For a **file** (`classmap`, `files`), the first one found is used.
- For a **directory** (`psr4`, `psr0`), a class is looked for in each of them, in that order. A file in `application/vendor/` replaces the same file of a module.

A path can also be absolute, to reach a library anywhere:

```php
'psr4' => [
	'Acme\\Billing\\' => DOCROOT.'../shared/billing/src',
],
```

A namespace can have more than one directory:

```php
'psr4' => [
	'Acme\\' => ['acme-core/src', 'acme-extras/src'],
],
```

[!!] A `vendor` directory holds code. It must not be reachable from the web: `application/` and `modules/` are outside `public/` for that reason. See [Deploying](../kohana/security/deploying).

## A module that brings its library

A module can carry a library in its own `vendor` directory, with a `config/ub_autoload.php` that names it. The config files of all modules are used together, so enabling the module is enough:

```text
modules/invoices/
	classes/Invoice.php
	config/ub_autoload.php
	vendor/tcpdf/
```

```php
// modules/invoices/config/ub_autoload.php
return [
	'classmap' => [
		'TCPDF' => 'tcpdf/tcpdf.php',
	],
];
```

Enable `ub_autoload` as well as the module.

## Helpers and classes of your own

Code of your own that is not a Kohana class goes the same way: a file of functions, or classes in namespaces.

```php
// application/vendor/helpers/money.php

/**
 * Formats an amount of cents: 123456 is "1,234.56".
 */
function money(int $cents): string
{
	return number_format($cents / 100, 2);
}
```

```php
// application/config/ub_autoload.php
return [
	'psr4' => [
		// App\Billing\Invoice  is  application/vendor/app/Billing/Invoice.php
		'App\\' => 'app',
	],
	'files' => [
		'helpers/money.php',
	],
];
```

For a helper, a class with static methods in `classes/` (`Money::format()`) needs no config at all and is only read when it is used. Use `files` for functions you already have.

## While the application runs

The same can be said in code, for a library that is only needed in one place:

```php
UB_Autoload::add_psr4('Acme\\Billing\\', 'billing/src');
UB_Autoload::add_psr0('Old_Library', 'old-library/lib');
UB_Autoload::add_class_map('TCPDF', 'tcpdf/tcpdf.php');
UB_Autoload::add_file('helpers/money.php');
```

| Method | Does |
|---|---|
| `UB_Autoload::add_psr4($prefix, $paths)` | A namespace and its directory, or several |
| `UB_Autoload::add_psr0($prefix, $paths)` | A prefix and its directory, or several |
| `UB_Autoload::add_class_map($class, $file)` | A class and its file |
| `UB_Autoload::add_file($file)` | Includes a file now, once |
| `UB_Autoload::get_vendor_paths()` | The `vendor` directories that are searched |
| `UB_Autoload::get_config()` | What `config/ub_autoload.php` says, of all modules together |

## When something is wrong

A path in the config that does not exist is an error with a clear text, not a class that is silently missing:

```text
ub_autoload: the directory phpmailer/scr of PHPMailer\PHPMailer\ was not found.
A path that is not absolute is looked for in the vendor directories: APPPATH/vendor/
```

- A missing file of `files` is reported at the start of the request.
- A missing directory or a missing file of `classmap` is reported when a class of it is first used: nothing is looked for on the disk before that.
- "Class not found" with no such error means that no line of the config covers the class. Compare the namespace of the class, letter by letter, with the config. On Linux, large and small letters in file names are different letters.

## Good to know

- **Speed.** The loader of Kohana is asked first, so the classes of the framework and of your application cost nothing more. For a library, nothing is read from the disk until one of its classes is used.
- **Safety.** The name of a class becomes part of a path. Only what can be the name of a class is looked for, so a name that came from a visitor (`class_exists($name)`) cannot reach other files.
- **Composer.** When you install libraries with Composer, you do not need this module for them: `application/bootstrap.php` includes `vendor/autoload.php` of Composer when it is there. Both can be used side by side.
- **Updates and licences.** A library you copy into `vendor` is yours to keep up to date: watch its security releases. Each has a licence of its own; read it before you ship.

## Coming from the older ub_autoload

For those who used the module of the same name before Kohana 4.

The config file has the same name and the same four keys, so it can be kept. What changed:

- The class is `UB_Autoload`. The old name, `UB_Autoloader`, still works.
- A relative path of `classmap` is found in the `vendor` directories, as the config always said. Before, only absolute paths worked there.
- A path that does not exist is an error. Before, it was skipped without a word.
- `MODPATH` ends with a `/`: write `MODPATH.'ub_core/vendor/phpmailer/src'`, or just `'phpmailer/src'` when the library is in the `vendor` directory of an enabled module.
