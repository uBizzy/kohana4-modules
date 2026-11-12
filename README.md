# Kohana 4 modules

Modules for [Kohana 4](https://github.com/uBizzy/kohana4) that are not part of its core: each one is optional, has its own tests and its own guide, and needs nothing but the framework.

## Installing

The modules are not shipped with the framework. Copy the ones you want into the `modules/` directory of your Kohana 4 project, beside `orm`, `database` and the others:

```bash
git clone https://github.com/uBizzy/kohana4-modules
```

```bash
cp -r kohana4-modules/ub_debug modules/
```

Then enable them in `application/bootstrap.php` (see "Using one" below). The framework ignores `modules/ub_*` in its own repository, so the two do not mix.

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
	'ub_debug' => MODPATH.'ub_debug',
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
  the framework; the framework never calls a module of this repository.
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

Once the modules are in `modules/`, the checks of the framework find them: code style, types, class loading and the tests, guide pages included. From the project root:

```bash
docker/check.sh
```

Static analysis has a list of directories, so the modules have a file of their own for it. With this repository cloned into the project root:

```bash
vendor/bin/phpstan analyse -c kohana4-modules/phpstan.neon
```

A new module is added to the `paths` of `phpstan.neon`; the other checks find it by themselves. The same checks run on GitHub for every push: see `.github/workflows/ci.yml`.

## License and links

[BSD-3-Clause](LICENSE.md), as the framework.

- The framework: https://github.com/uBizzy/kohana4
- Questions, bugs and security problems: https://github.com/uBizzy/kohana4-modules/issues (see [SECURITY.md](SECURITY.md))
