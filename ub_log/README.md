# Database log module (ub_log)

Writes the log of the framework to a table, to search it with SQL, and sends alerts by email.

```php
// application/bootstrap.php
Kohana::modules([
	'database' => MODPATH.'database',
	'ub_log'   => MODPATH.'ub_log',
]);

Kohana::$log->attach(new UB_Log_Database);
```

```bash
./minion ublog:migration
```

```bash
./minion migration:run
```

```php
UB_Log::add('upload.failed', 'The file was too large', ['size' => $size], Log::WARNING);

$rows = UB_Log::find(['type' => 'upload.*', 'level' => Log::WARNING]);
```

- The eight levels of the framework, plus a type of your own for each message: levels tell how bad, types tell what.
- One INSERT for all messages of a request, after the page was made.
- Nothing of the request is stored by itself, and secrets in the context are masked.
- Alerts by email for errors and for the types you list, with a limit.
- Works on MySQL, MariaDB, PostgreSQL and SQLite. Needs the database module, and the migration and minion modules for the table and the clean-up task.

The full documentation is in `guide/ub_log/` and in the user guide of your application.
