# Panels and Settings

## The panels

| Panel | Shows |
|---|---|
| **Timeline** | Everything that was measured, in the order it started, as bars on a time line, each with what came of it. A row of buttons switches groups on and off |
| **Database** | Every query with how often it ran, how many rows it returned or changed, how long it took, and the file and line of your code that ran it. Queries that ran more than once are marked: they are the usual cause of a slow page |
| **Cache** | Every id that was read from the [Cache module](../cache): how often it was found and how often not. Only there when the request used the cache |
| **Request** | Route, controller and action, and the routes that were tried before the one that matched; route parameters, GET and POST; names of the cookies; headers of the request and of the response |
| **Views** | The views that were rendered, how often, their time, and the names of the variables each was given |
| **Session** | The data of the sessions that were opened. A request that used no session opens none |
| **Logs** | Log messages of this request and notices about deprecated code |
| **Routes** | Every route in the order they are tried, the matched one marked |
| **Files** | Files that were looked for and not found; the PHP files that were loaded, grouped by application, module and system; the classes the autoloader loaded, in order |
| **Modules** | The enabled modules |
| **Server** | Versions of Kohana and PHP, limits, loaded extensions |
| **AJAX** | Background requests the page makes, as they happen, with what the server reports about each |
| **Custom** | Data you add with `Profiler::add()`: see [Your Own Data](data). Only there when you added some |
| **Config** | The config groups you list. Off by default |

## Settings

Copy `extra/ub_debug/config/ub_debug.php` to `application/config/ub_debug.php` and change what you need.

```php
// application/config/ub_debug.php
return [
	// false: no bar, the module stays loaded
	'enabled' => true,

	// Set a panel to false to leave it out
	'panels' => [
		'files'   => false,
		'modules' => false,
		'config'  => true,
	],

	// Groups the Config panel may show
	'config_groups' => ['pagination', 'cors'],

	// Routes whose pages get no bar
	'exclude_routes' => ['docs/media', 'admin-export'],
];
```

Your file is merged with the one of the module, so list only what differs.

| Setting | Default | Meaning |
|---|---|---|
| `enabled` | `true` | Show the bar. It never shows outside development, whatever is set here |
| `panels` | all but `config` | Which panels there are, and their order |
| `config_groups` | none | Config groups the **Config** panel shows. A group that is not listed is never shown |
| `mask_sql` | `true` | Replace text values written inside SQL by `'***'` |
| `exclude_routes` | guide and Codebench media | Names of routes whose pages get no bar |
| `duplicate_queries` | `2` | A query is marked as repeated from this many times on |
| `timeline_shortest` | `0.05` | The timeline leaves out what took fewer milliseconds than this; `0` shows everything |
| `timeline_off` | `classes`, `find file` | Groups of the timeline that start switched off |

## Reading the Timeline

Every row is one thing the framework or your code did, with its group, what it was about, and what came of it:

```text
routing      shop/cart                 matched "default" after trying: api, admin
controller   Controller_Shop::before
validation   email, quantity           failed: quantity
views        APPPATH/views/shop/cart.php   $cart, $errors
```

- Click the name of a group above the list to hide or show its rows. The groups `classes` and `find file` have many rows and start hidden.
- What the framework measures, and what each note means, is listed in [Profiling](../kohana/profiling#what-the-framework-measures).

## Reading the Files panel

**Looked for, not found** comes first, because it is the part that costs time: every file in that list was searched for in the application, in every module and in the system, and was in none of them. A file that is looked for on every request and never exists (a view that is optional, a config file a module asks for) is worth creating empty, or the lookup worth removing.

The loaded files are grouped by where they come from, so you can see which module a request really uses. The list of classes shows them in the order they were loaded, which answers "why is this class loaded at all?": the class before it in the list usually asked for it.

## Reading the Database panel

```php
// One query for the list, then one for every row: 51 queries
foreach (ORM::factory('Article')->find_all() as $article)
{
	echo $article->author->name;
}
```

The panel shows the query for `authors` once, with `50` under **Times** and the mark **repeated**. Under the query is the place that ran it, here the line of the `echo` in your view, with `× 50`. Load the authors in one go instead:

```php
// Two queries, whatever the number of articles
foreach (ORM::factory('Article')->with('author')->find_all() as $article)
{
	echo $article->author->name;
}
```

Queries are shown as they were sent. With prepared statements, the default, a query holds `?` in place of its values.

The place under a query is the nearest step in your application (`APPPATH`). For a query that no application code asked for, it is the nearest step outside the database module, such as a line of another module.

## Reading the Cache panel

```php
$articles = Cache::instance()->get('home.articles');

if ($articles === null)
{
	$articles = ORM::factory('Article')->find_all()->as_array();

	Cache::instance()->set('home.articles', $articles, 300);
}
```

The first request shows `home.articles` under **Not found**; the next ones under **Found**. An id that is never found (the row is marked) is one that is read but never stored, or stored with a lifetime that is too short. The bar shows `found / all reads` beside the name of the panel.
