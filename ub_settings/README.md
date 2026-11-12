# Settings module

Settings that are saved in the database, to be changed while the application runs: the name of the site, how many items a page shows, whether registration is open.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_settings' => MODPATH.'ub_settings',
]);
```

```bash
./minion settings:migration
```

```bash
./minion migration:run
```

```php
UB_Settings::set('site.name', 'My Site');

echo UB_Settings::get('site.name', 'Untitled');
```

- A value is text, a number, true, false, null or an array of these, and comes back with the type it had.
- Values are stored as JSON: nothing read from the table can become an object.
- One query in a request that uses settings, none with the cache switched on.
- Works on MySQL, MariaDB, PostgreSQL and SQLite. Needs the database module, and the migration and minion modules to create the table.

Passwords and keys do not belong in it: they stay in the config files.

The full documentation is in `guide/ub_settings/` and in the user guide of your application.
