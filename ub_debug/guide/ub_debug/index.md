# Debug Bar

The Debug Bar module puts a bar at the bottom of every page while you develop. It shows what the request did: how long it took, which database queries ran, which views were rendered, what the session holds, what was logged.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_debug' => MODPATH.'ub_debug',
]);
```

That is all: there is nothing to call and nothing to add to your templates.

![The Database panel of the bar](database.png)

## Requirements

- Kohana 4 and nothing else: no other module, no extension of PHP beyond those of the framework.
- Used when they are there: the database module (the queries), the cache module (the cache panel) and OPcache (the server panel). A panel whose module is not enabled stays empty.

## What you see

- **On the bar**: the status of the response, the time, the memory, and the controller and action that made the page.
- **In the panels**, one click away: the timeline, the queries with the line of your code that ran each, the cache, the request, the views, the session, the logs, and more. See [Panels and Settings](panels).
- Click a tab to open a panel, click it again or press `Esc` to close it. The word **Kohana** hides the whole bar and brings it back.

## Only in development

[!!] The bar is shown in the `DEVELOPMENT` environment only. No setting changes that: on `TESTING`, `STAGING` and `PRODUCTION` the module does nothing at all.

```php
// application/bootstrap.php: the environment is usually set from the server
if (isset($_SERVER['KOHANA_ENV']))
{
	Kohana::$environment = constant('Kohana::'.strtoupper($_SERVER['KOHANA_ENV']));
}
```

Even so, do not enable the module on a live server: leave it out of the module list there.

## Pages that get no bar

The bar is only put into whole HTML pages. These are sent exactly as they are:

| Response | Why |
|---|---|
| JSON, images, downloads, anything that is not HTML | The bar would break them |
| Redirects | There is no page |
| AJAX requests | The page that asked wants only its data |
| HTML without a closing `</body>` tag | It is a piece of a page |
| Pages of the routes in `exclude_routes` | Your choice, see [Panels and Settings](panels) |

Every response still gets a `Server-Timing` header with the time, the number of queries and the memory. The developer tools of the browser show it, and the **AJAX** panel reads it for the background requests of the page.

```php
// No bar for this one page
public function action_print(): void
{
	UB_Debug::disable();

	// ...
}
```

## What the module changes while it is on

- [Profiling](../kohana/profiling) is switched on, because the bar shows what the profiler measures.
- The page is kept back until the request ends, so the bar can be put in. Code that sends output in steps (`flush()`) sends it all at the end.
- Notices about deprecated code are shown in the **Logs** panel, not in their own panel at the end of the page.
