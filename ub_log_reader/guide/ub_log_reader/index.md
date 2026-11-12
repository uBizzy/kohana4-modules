# Log Reader

The Log Reader searches the logs of the application: by level, text, date, the reference code of an error, or the request it happened in. It answers "what went wrong for that visitor at ten past ten?" without opening a file.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_log_reader' => MODPATH.'ub_log_reader',
]);
```

There are three ways to use it: a command, a page, and a class. With many logs, an [index](#the-index) makes all three fast.

## The command

```text
./minion log:search --level=error --from=yesterday
2026-11-12 09:00:01  CRITICAL  [FFEE0011] Database_Exception [ 0 ]: The database is gone  (MODPATH/database/classes/Kohana/Database/PDO.php:90)  request bbbb2222
2026-11-11 10:05:00  ERROR     [A1B2C3D4] Kohana_Exception [ 0 ]: The payment failed  (/app/classes/Payment.php:88)

✔ 2 entries.
```

The newest entries come first.

| Option | Keeps |
|---|---|
| `--level=error` | entries of that level **and worse**: `error` also gives critical, alert and emergency |
| `--text=payment` | entries with this text in the message, the file, the address or the trace |
| `--reference=A1B2C3D4` | the error with this reference code |
| `--request=bbbb2222` | everything that was logged in that request (JSON logs) |
| `--from=yesterday` | entries from this day or time on: `2026-11-12`, `yesterday`, `"-2 hours"` |
| `--to=2026-11-12` | entries up to this day or time |
| `--limit=20` | so many entries at most; 50 when left out |
| `--trace` | shows the trace of each error |
| `--dir=/path/to/logs` | another directory of logs |

The command works in every environment: whoever can run it can read the files anyway.

### An error a visitor reports

The error page shows a reference code. With it, the error is found at once:

```bash
./minion log:search --reference=A1B2C3D4 --trace
```

## The page

In development, the same search is at the address `ub-log-reader`:

```text
http://localhost/ub-log-reader
```

A form with the filters, and the entries that fit. The code of an error and the code of a request are links: one click shows that error, or everything that was logged in that request. **Most frequent** shows which messages come most often (see below).

The page follows the [theme](../kohana/theme) of the framework, light or dark, and works under a strict Content-Security-Policy. Everything that comes from a log is written as text.

To switch the page off everywhere, set `page` to `false` in `config/ub_log_reader.php`.

### In production

[!!] Logs hold what visitors must not read. Outside the `DEVELOPMENT` environment the address is **not found**, unless you open it for addresses you name.

Two settings are needed, both of them:

```php
// application/config/ub_log_reader.php
return [
	'production'  => true,
	'allowed_ips' => [
		'203.0.113.7',       // one address
		'10.0.0.0/8',        // a network
		'2001:db8::/32',     // IPv6 works the same way
	],
];
```

| `production` | `allowed_ips` | The page outside development |
|---|---|---|
| `false` (the default) | anything | not found |
| `true` | empty | not found |
| `true` | has the address of the visitor | shown |
| `true` | does not have it | not found |

- Everybody else gets the same "404 Not Found" as for an address that does not exist: the answer does not tell that there is a page.
- `production` must be exactly `true`.
- Each time the page is read outside development, a line is logged with the address it was read from.
- The same rule holds for the `TESTING` and `STAGING` environments.

[!!] **Behind a proxy or a load balancer**, the framework only sees the address of the visitor when the proxy is named in `Request::$trusted_proxies` (see [Requests](../kohana/requests)). Without that, every visitor has the address of the proxy: listing that address here would let everybody in. Check with `echo Request::$client_ip;` what the framework sees for you.

An address is a weak lock: everybody in the same office or on the same VPN shares it. Keep the list short. When the logs are sensitive, add a second lock in front of the address `ub-log-reader` in your web server: a password (HTTP authentication) or a VPN.

The command line has no such rule: whoever can run `./minion` on the server can read the log files anyway.

## The most frequent messages

"Which errors does this site have?" is answered by counting:

```text
./minion log:top --level=error --from="-1 week"
Times  Level     Last              Message
-----  --------  ----------------  --------------------------------------------------------------
  214  ERROR     2026-11-12 09:41  [5F0A77C1] Kohana_Exception [ 0 ]: Order 2051 not saved ~ APPPA...
   12  CRITICAL  2026-11-12 09:00  [FFEE0011] Database_Exception [ 0 ]: The database is gone
    3  ERROR     2026-11-11 10:05  [A1B2C3D4] Kohana_Exception [ 0 ]: The payment failed
```

- Messages that differ only in their numbers and in the reference code are counted as one: "Order 17 not saved" and "Order 2051 not saved" are the same problem.
- The newest wording is shown, with the code of the newest error to look it up.
- `--level`, `--text`, `--from` and `--to` work as for `log:search`; `--limit` is how many messages (10).
- On the page: the link **Most frequent**, with the same filters.

```php
$top = $reader->top(['level' => 'ERROR', 'from' => '-1 week'], 10);

// [['count' => 214, 'level' => 'ERROR', 'message' => '...', 'first' => 1762..., 'last' => 1762..., 'reference' => '5F0A77C1'], ...]
```

## The index

Without an index every search reads the log files. That is fast for the newest entries, and slow for what is rare or old: an error code of last month, a text that is found nowhere, the most frequent messages. With many logs, switch the index on:

```php
// application/config/ub_log_reader.php
return [
	'index' => true,
];
```

The index is a SQLite database, the file `ub_log_reader.sqlite` beside the logs.

- **It keeps itself up to date.** Each search first adds what was written to the logs since the last one. There is nothing to run by hand or by cron.
- **The log files stay the truth.** Delete the index whenever you like: it is made again from the files. Log files that are deleted leave the index too.
- **The same answers.** A search gives the same entries in the same order with the index as without it.
- **About as large as the logs.** It holds a copy of their entries.
- **As private as the logs.** It holds what they hold: keep it out of reach of the web, as `application/` is. Secrets are hidden when entries are shown, as without the index.
- Needs the `pdo_sqlite` extension of PHP. Without it the files are read, as if the index were off.

On 300 000 entries of 30 days (29 MB of logs, an index of 34 MB that took 4 seconds to make):

| Search | Files | Index |
|---|---|---|
| The newest 100 entries | 38 ms | 1 ms |
| An error by its code, a month old | 1 026 ms | under 1 ms |
| A text that is found nowhere | 1 389 ms | 76 ms |
| The most frequent errors of the month | 927 ms | 74 ms |

```bash
# Make the index now, before the first search of many logs
./minion log:index
```

```bash
# Empty it and read every log file again
./minion log:index --rebuild
```

## From your own code

```php
$reader = new UB_Log_Reader;

// The newest 50 errors of today that mention "payment"
$entries = $reader->search(['level' => 'ERROR', 'text' => 'payment', 'from' => 'today'], 50);

foreach ($entries as $entry)
{
	echo date('H:i:s', $entry['time']), ' ', $entry['level'], ' ', $entry['message'];
}
```

```php
$reader->days();   // ['2026-11-12', '2026-11-11', ...]: the days there are logs of
```

```php
UB_Log_Reader::allowed(Request::$client_ip);   // may the page be read from this address?
$reader->index();                              // the index, or null when none is used
$reader->reindex();                            // makes the index again from the files
```

An entry is an array:

| Key | Holds |
|---|---|
| `time` | a UNIX timestamp |
| `level` | `EMERGENCY`, `ALERT`, `CRITICAL`, `ERROR`, `WARNING`, `NOTICE`, `INFO` or `DEBUG` |
| `message` | the message |
| `file`, `line` | where it was logged |
| `reference` | the reference code, for an error |
| `trace` | the trace, for an error |
| `request`, `method`, `uri` | the request it was logged in (JSON logs) |
| `exception` | the class of the error (JSON logs) |

What a log file does not tell is `null`.

## Text logs and JSON logs

The reader understands both kinds of log file of the framework:

| Writer | Files | What the reader gets |
|---|---|---|
| `Log_File` | `logs/2026/11/12.php` | time, level, message, file and line, reference code and trace of errors |
| `Log_JSON` | `logs/2026/11/12.json.php` | the same, and the request each entry belongs to |

With `Log_JSON` every entry carries the code of its request, so `--request` shows the whole story of one request: what was logged before the error, and after it. To write JSON logs, beside the text log or in its place:

```php
// application/bootstrap.php
Kohana::$log->attach(new Log_JSON(APPPATH.'logs'));
```

For a day that has both files, the JSON file is read: it holds the same messages with more detail, and nothing is listed twice.

## Secrets

Logs hold private data, and now and then a secret that should never have been logged. In what the reader returns, the value after a name that looks like a secret is replaced by `***`:

```text
Login failed: password=hunter2 user=ana      Login failed: password=*** user=ana
orders/save?access_token=abc123              orders/save?access_token=***
Authorization: Bearer eyJhbGciOi...          Authorization: ***
```

The names are those with `password`, `passwd`, `secret`, `token`, `api_key`, `salt` or `signature` in them, and `authorization` and `cookie`.

- A search for the secret still finds the entry: the value is hidden, not removed.
- This is a net, not a wall: a secret that is logged without such a name is shown. Keep secrets out of log messages.
- `$reader->mask = false` switches it off for code of your own.

## Good to know

- Without the index, files are read line by line, the newest day first, and the search stops when it has enough: a log of many days is no problem, one huge day takes a moment.
- The text log is read in its default time format (`Y-m-d H:i:s`). With another `Log_Writer::$timestamp`, use `Log_JSON`.
- `./minion check` does not warn about this module in production: the page is closed there unless you open it.
