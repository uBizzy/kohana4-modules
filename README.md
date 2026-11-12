# Kohana 4 modules

Modules for [Kohana 4](https://github.com/uBizzy/kohana4) that are not part of its core: each one is optional, has its own tests and its own guide, and needs nothing but the framework.

## Installing

This repository is the `extra/` directory of a Kohana 4 project. From the root of the project, beside `system/` and `modules/`:

```bash
git clone https://github.com/uBizzy/kohana4-modules extra
```

Then enable the modules you want (see "Using one" below). The framework ignores the `extra/` directory in its own repository, so the two do not mix.

What changed is in [CHANGELOG.md](CHANGELOG.md), what is planned in [TODO.md](TODO.md).

## The modules

| Module | Class | Does |
|---|---|---|
| `ub_debug` | `UB_Debug` | A bar at the bottom of every page that shows what the request did. Development only. |
| `ub_storage` | `UB_Storage` | Files kept on the disk, on an FTP server or in S3, behind the same methods. |
| `ub_log_reader` | `UB_Log_Reader` | Search the logs by level, text, date, error code or request; the most frequent errors. On the web in development, or in production for listed addresses. |
| `ub_settings` | `UB_Settings` | Settings that are saved in the database and changed while the application runs. |
| `ub_autoload` | `UB_Autoload` | Loads libraries of others (PHPMailer, HTML Purifier, TCPDF), and helpers and classes of your own that are not written the Kohana way. |

## Using one

```php
// application/bootstrap.php
Kohana::modules([
	'ub_debug' => DOCROOT.'extra/ub_debug',
]);
```

Each module has a `README.md`, and a guide that the userguide module
shows once the module is enabled.

## The rules for a module here

- **Its own prefix.** The directory is `ub_name`, the main class
  `UB_Name` (`classes/UB/Name.php`), further classes `UB_Name_Something`.
  The prefix `Kohana_` is for the core.
- **Its own names everywhere**: `config/ub_name.php`, `views/ub_name/`,
  `guide/ub_name/`.
- **Nothing in the core knows it.** A module uses the public parts of
  the framework; the framework never calls a module of this directory.
- **The same quality as the core**: types on everything, comments, tests
  in `tests/`, and clean under the code style and static analysis rules
  of the framework.
- **For development only?** Then the module says so in a
  `config/check.php` (see the one of `ub_debug`), and `./minion check`
  warns when it is enabled in production.
- **A guide** in `guide/ub_name/`, listed in `config/userguide.php`. Its
  pages are checked like those of the framework: complete, with links
  that lead somewhere and examples that are valid PHP.

## Checking them

`docker/check.sh` of the framework checks the modules of `extra/` after
the core, with the same rules, when the directory is there. On their
own, from the project root:

```bash
vendor/bin/phpcs -q extra
```

```bash
php docker/check-types.php extra/*/classes
```

```bash
vendor/bin/phpstan analyse -c extra/phpstan.neon
```

```bash
vendor/bin/phpunit -c extra/phpunit.xml
```

A new module is added to the `paths` of `extra/phpstan.neon`; the other
three find it by themselves.

The same four checks run on GitHub for every push, with the framework
checked out around the modules: see `.github/workflows/ci.yml`.

## License and links

[BSD-3-Clause](LICENSE.md), as the framework.

- The framework: https://github.com/uBizzy/kohana4
- Questions, bugs and security problems: https://github.com/uBizzy/kohana4-modules/issues (see [SECURITY.md](SECURITY.md))
