# Debug Bar module

A bar at the bottom of every page that shows what the request did: time, memory, database queries (with repeated ones marked), views, route, session, log messages and more.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_debug' => DOCROOT.'extra/ub_debug',
]);
```

- Shown in the `DEVELOPMENT` environment only; this cannot be changed by a setting.
- Values with a secret name are hidden, cookie values and environment variables are never shown.
- Panels are switched on and off in `config/ub_debug.php`.
- Works under a strict Content-Security-Policy.

The full documentation is in `guide/ub_debug/` and in the user guide of your application.
