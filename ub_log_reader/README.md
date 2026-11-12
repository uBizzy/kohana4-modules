# Log Reader module

Search the logs of the application: by level, text, date, the reference code of an error, or the request it happened in, and see which messages come most often. From the command line, and on a page.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_log_reader' => MODPATH.'ub_log_reader',
]);
```

```bash
./minion log:search --level=error --from=yesterday
```

```bash
./minion log:top --level=error --from="-1 week"
```

- Reads the text logs of `Log_File` and the JSON logs of `Log_JSON`.
- The page, at `ub-log-reader`, is there in the `DEVELOPMENT` environment. In production it is not found, unless `config/ub_log_reader.php` both switches it on (`production`) and lists the address of the visitor (`allowed_ips`).
- An optional index in SQLite (`index`) makes searches of many logs fast. It keeps itself up to date and can be deleted at any time.
- What looks like a password, a token or a key is shown as `***`.
- The page follows the light or dark theme of the framework and works under a strict Content-Security-Policy.

The full documentation is in `guide/ub_log_reader/` and in the user guide of your application.
