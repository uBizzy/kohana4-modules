# Database log

The `ub_log` module writes the log of the framework to a table, so that you can search it with SQL: all errors of last night, everything one request did, every failed upload this month.

It does not replace the log of the framework: it is one more writer of it. What the framework logs (errors, with their code) and what your code logs both go there.

## Requirements

- Kohana 4 and the database module, on MySQL, MariaDB, PostgreSQL or SQLite.
- The migration and minion modules, to create the table with `./minion ublog:migration` and to clean it with `./minion ublog:clean`. Without them, create the table yourself from `ub_log/create_ub_logs_table.php` of the module.
- For alerts by email, one of: the ub_email module, or a mail server set up for the `mail()` function of PHP. Without alerts, neither.
- A cache that works (`Kohana::cache()`), for the limit of the alerts: the file cache of the framework is enough.

## Set up

	// application/bootstrap.php
	Kohana::modules([
		'database' => MODPATH.'database',
		'ub_log'   => MODPATH.'ub_log',
	]);

	// Everything...
	Kohana::$log->attach(new UB_Log_Database);

	// ...or warnings and worse only
	Kohana::$log->attach(new UB_Log_Database, Log::WARNING);

Create the table. The first command writes a migration into `application/migrations`; the second applies it.

	./minion ublog:migration
	./minion migration:run

[!!] Keep a file writer attached as well (`Log_File` or `Log_JSON`). When the database is down, the table cannot tell you so.

## Levels and types

A message has one of the eight levels of the framework. They tell **how bad** it is:

`Log::EMERGENCY`, `ALERT`, `CRITICAL`, `ERROR`, `WARNING`, `NOTICE`, `INFO`, `DEBUG`

A message can also have a type. It tells **what** happened, in a name of your own:

	UB_Log::add('upload.failed', 'The file was too large', ['size' => $size], Log::WARNING);
	UB_Log::add('invoice.sent', 'Invoice 12 was sent');
	UB_Log::add('api.stripe.error', 'The payment was refused', ['code' => $code], Log::ERROR);

A type is letters, digits, `.`, `_` and `-`, up to 64 characters. Name them from general to specific, with dots: `upload.failed`, `upload.security`. Then one filter finds a family: `upload.*`.

`UB_Log::add()` goes to every writer of the framework log: the files too. The plain call of the framework works as well:

	Kohana::$log->add(Log::WARNING, 'The file was too large', null, ['type' => 'upload.failed', 'context' => ['size' => $size]]);

Other modules use the same way to name their messages: `ub_email` logs `email.sent` and `email.failed`.

## What a row holds

Column | What it is
--- | ---
`time` | when, in seconds since 1970 (UTC)
`level` | the number of the level: 0 (emergency) to 7 (debug). Lower is worse.
`type` | the type, or null
`message` | the message
`context` | the context, as JSON
`request` | a code that all rows of one request share (the same as in the JSON log files)
`reference` | for an error: the code that the error page showed to the visitor
`user_id` | who the message is about: see [Who](#who)
`ip` | the address of the visitor
`method`, `uri` | the request: `GET`, `invoices/view/12`; `CLI` for a task
`file`, `line` | where it was logged

### Who

The writer does not know how your application logs people in. Tell it once, in the bootstrap, and every row gets the number of who was logged in:

	UB_Log_Database::$user = fn (): ?int => My_Login::user_id();

The function is called when the rows are written, at the end of the request. It returns the number of the account, or null for nobody. What it throws is ignored: the message is written without a number.

A single message can name somebody else, next to `type` and `context`:

	Kohana::$log->add(Log::NOTICE, 'Wrong password', null, ['type' => 'login.failed', 'user' => $id]);

### What is not stored

Nothing of the request is stored by itself: no form fields, no cookies, no headers. Only the context you pass. In it, the value of every name that looks like a secret (`password`, `token`, `secret`, `csrf`, `authorization`, `cookie`, `session`, `api_key`, `card`, `cvv`, `iban` ...) is replaced by `***`, at any depth. Long texts are cut, objects become their class name.

[!!] The masking goes by the name of a value, not by what it holds. Do not pass what must stay secret.

## Searching

In SQL, as you like. Some examples:

	-- Errors and worse of the last day (lower is worse: ERROR is 3)
	SELECT * FROM ub_logs WHERE level <= 3 AND time > UNIX_TIMESTAMP() - 86400 ORDER BY id DESC;

	-- Everything one request did
	SELECT * FROM ub_logs WHERE request = '3f9a1c0e' ORDER BY id;

	-- The error a visitor reported, by the code of the error page
	SELECT * FROM ub_logs WHERE reference = 'A1B2C3D4';

	-- How many failed uploads a day
	SELECT FROM_UNIXTIME(time, '%Y-%m-%d') AS day, COUNT(*) FROM ub_logs WHERE type = 'upload.failed' GROUP BY day;

Or from PHP, newest first:

	$rows = UB_Log::find(['level' => Log::WARNING, 'from' => strtotime('-1 day')]);
	$rows = UB_Log::find(['type' => 'upload.*'], 500);
	$rows = UB_Log::find(['request' => '3f9a1c0e']);

Filter | What it keeps
--- | ---
`level` | this level and worse
`type` | this type; with `*` at the end, the types that start so
`request` | the messages of one request
`reference` | the error with this code
`user` | the messages of one account
`from`, `to` | between these times (seconds since 1970)
`text` | the messages that hold this text

Each row comes with its `context` as an array.

[!!] A page that shows the log shows what visitors typed: escape every value (`HTML::chars()`), and guard the page.

## Alerts by email

	// application/config/ub_log.php
	'alert' => [
		'to'      => ['dev@example.com'],
		'level'   => LOG_ERR,                    // errors and worse
		'types'   => ['upload.security'],        // and these, whatever their level
		'limit'   => [10, 3600],                 // at most 10 emails an hour
		'subject' => 'My Site: log alert',
	],

One email holds all such messages of a request: level, type, message, the code of the request, the error code and the address. The context is not sent: email is not a safe place.

The limit matters. Without one, a visitor who can cause an error can fill your mailbox. What comes after the limit is in the table, not in an email.

The email goes through the `ub_email` module when it is enabled, otherwise through the `mail()` function of PHP.

## Cleaning up

The table grows. A task removes what is older than the config keeps:

	// application/config/ub_log.php
	'keep' => [
		'days'       => 90,     // warnings and lighter
		'error_days' => 365,    // errors and worse
	],

	./minion ublog:clean

Run it once a day, from the scheduler of the server (cron).

## When the table cannot be written

A log must never break the page. When the INSERT fails, the reason goes to the error log of PHP and the request goes on. That is one more reason to keep a file writer.

## When it is written

The framework hands the messages to its writers when the request ends, all at once: the visitor pays one INSERT, after the page was made. To write each message at once (in a long task, for example), set `Log::$write_on_add = true`.
