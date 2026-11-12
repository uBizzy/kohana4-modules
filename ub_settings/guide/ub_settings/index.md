# Settings

The Settings module keeps settings in the database, so they can be changed while the application runs: the name of the site, how many articles a page shows, whether registration is open. Config files stay what they are: set by the developer, in version control.

```php
UB_Settings::set('site.name', 'My Site');

echo UB_Settings::get('site.name', 'Untitled');
```

## Setting it up

```php
// application/bootstrap.php
Kohana::modules([
	'database'    => MODPATH.'database',
	'migration'   => MODPATH.'migration',
	'minion'      => MODPATH.'minion',
	'ub_settings' => DOCROOT.'extra/ub_settings',
]);
```

The table comes as a [migration](../migration), which a task writes into your application:

```bash
./minion settings:migration
```

```bash
./minion migration:run
```

```text
applied  20261112_101500_create_settings_table
✔ 1 migration applied.
```

The table is `settings`, with two columns: `name` and `value`. It works on MySQL, MariaDB, PostgreSQL and SQLite.

## Using it

```php
// Save: new or changed
UB_Settings::set('articles.per_page', 20);
UB_Settings::set('registration.open', false);
UB_Settings::set('mail.copies', ['ana@example.com', 'bea@example.com']);

// Read, with what to use when it was never set
$per_page = UB_Settings::get('articles.per_page', 10);

// Was it set?
if (UB_Settings::has('site.name'))
{
	// ...
}

// Remove: get() returns the default again
UB_Settings::delete('articles.per_page');

// All of them, or those whose names start with something
$all  = UB_Settings::all();
$mail = UB_Settings::all('mail.');   // ['mail.copies' => [...]]
```

| Method | Does |
|---|---|
| `UB_Settings::get($name, $default = null)` | Returns the setting, or the default when it was never set |
| `UB_Settings::has($name)` | `true` when it was set, to any value |
| `UB_Settings::all($prefix = '')` | All settings as `name => value`, sorted by name |
| `UB_Settings::set($name, $value)` | Saves the setting |
| `UB_Settings::delete($name)` | Removes it |
| `UB_Settings::reload()` | Reads the table again at the next call |

## Names and values

- **A name** is made of letters, digits, `_`, `.` and `-`, 100 at most. The dot has no meaning of its own; it is a good habit for grouping (`mail.from`, `mail.copies`), and `all('mail.')` finds the group.
- **A value** is text, a number, `true`, `false`, `null`, or an array of these. It comes back with the type it had: `20` is a number, not `"20"`.
- Objects are refused. Values are stored as JSON, so nothing that is read from the table can ever become an object or run code.
- A value can be about 64 000 bytes of JSON (the `TEXT` column of MySQL).

[!!] `get()` returns the default only when the setting was **never set**. A setting that was set to `null`, `0` or `''` returns that.

## A form for the settings

```php
// application/classes/Controller/Admin/Settings.php
class Controller_Admin_Settings extends Controller {

	public function action_index(): void
	{
		$errors = [];

		if ($this->request->method() === HTTP_Request::POST)
		{
			$post = Validation::factory($this->request->post())
				->rule('csrf', 'not_empty')
				->rule('csrf', 'Security::check')
				->rule('name', 'not_empty')
				->rule('name', 'max_length', [':value', 100])
				->rule('per_page', 'digit')
				->rule('per_page', 'range', [':value', 1, 100]);

			if ($post->check())
			{
				UB_Settings::set('site.name', $post['name']);
				UB_Settings::set('articles.per_page', (int) $post['per_page']);
				UB_Settings::set('registration.open', isset($post['open']));

				$this->redirect('admin/settings');
			}

			$errors = $post->errors('settings');
		}

		$this->response->body(View::factory('admin/settings', [
			'errors'   => $errors,
			'name'     => UB_Settings::get('site.name', ''),
			'per_page' => UB_Settings::get('articles.per_page', 10),
			'open'     => UB_Settings::get('registration.open', true),
		]));
	}
}
```

[!!] The module stores what it is given. **Who may change a setting, and what a valid value is, is checked by your controller**, as above: only a logged in administrator should reach that page.

## What does not belong here

- **Passwords and keys** (of the database, of a mail server, the cookie salt). They stay in the config files, where only the developer and the server reach them. Whoever can open the settings page, or read the table, would see them.
- **Settings the application needs before the database is there**, such as the database connection itself.
- **Large or fast changing data**: all settings are read together, in one query.

## Speed and the cache

All settings are read with one query, the first time one is asked for in a request; a request that uses no setting runs no query.

To skip that query as well, keep the settings in the cache between requests:

```php
// application/config/ub_settings.php
return [
	'cache' => 3600,   // seconds; 0 (the default) uses no cache
];
```

- The cache is the one of `Kohana::cache()`: the [Cache module](../cache) when it is enabled and configured, files in `application/cache` otherwise.
- A change made with `UB_Settings` clears it at once.
- With several servers that each have a cache of their own (the file cache), the other servers see a change only when their copy runs out. Use a shared cache (Redis, Memcached) there, or a short time.
- After changing the table another way (by hand, by a script), call `UB_Settings::reload()`.

## The config

```php
// application/config/ub_settings.php
return [
	'database' => null,         // the group of config/database.php; null is the default
	'table'    => 'settings',   // without the prefix of the connection
	'cache'    => 0,            // seconds in the cache; 0 is off
];
```

For another table name, change it in the migration file as well, before you run it.

## In tasks and tests

A [Minion](../minion) task uses the settings like a controller does. A process that runs for a long time (a worker) reads the table once: call `UB_Settings::reload()` where it should look again.

In a test, start from a known state:

```php
public function setUp(): void
{
	parent::setUp();

	Database::instance()->begin();
	UB_Settings::reload();
}

public function tearDown(): void
{
	Database::instance()->rollback();
	UB_Settings::reload();

	parent::tearDown();
}
```
