# Changes

## First version (2026-11-15)

The first version, for Kohana 4.0.0 "renatus".

- **ub_debug**: a bar that shows time, memory, queries (with repeated ones marked), views, route, session, logs and more. Development only.
- **ub_storage**: files on the disk, on an FTP or FTPS server, or in S3 and the services that speak its language. S3 requests are signed by the module itself.
- **ub_log_reader**: a search through text and JSON logs by level, text, date, error code or request; the most frequent messages; a page that is closed in production unless switched on for listed addresses; an optional index in SQLite.
- **ub_settings**: settings saved in the database as JSON, read with one query, optionally cached.
- **ub_autoload**: loads libraries that are not written the Kohana way (PSR-4, PSR-0, class map, files), from `vendor` directories of the application and of modules. Tried with PHPMailer, HTML Purifier and TCPDF.

## Tests

- About 440 tests, run against real MariaDB, PostgreSQL, FTP and S3 servers; about 94% of the lines are covered.
- The same rules as the framework: types on everything, PHPStan at level 10, PHP_CodeSniffer.
