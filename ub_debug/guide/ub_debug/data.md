# Your Own Data

## A value you want to look at

`Profiler::add()` keeps a value with the request; the bar shows it in the **Custom** panel.

```php
public function action_checkout(): void
{
	$cart = Cart::current();

	Profiler::add('Cart', $cart->as_array());
	Profiler::add('Price rules', $cart->rules());

	// ...
}
```

- `Profiler` is part of the framework, so these lines can stay in the code: where the Debug Bar module is not enabled, nothing happens with the data.
- Nothing is kept when profiling is off, as in production.
- Values are shown with the same rules as everywhere on the bar: what has a secret name is hidden, see [What Is Hidden](security).

## Measuring a piece of code

Whatever the profiler measures appears on the **Timeline**.

```php
$total = Profiler::measure('Shop', 'Price calculation', fn () => $cart->total());
```

See [Profiling](../kohana/profiling) for more.

## A message

Log messages of the request are in the **Logs** panel, with the place in the code that wrote them.

```php
Kohana::$log->add(Log::INFO, 'Coupon :code accepted', [':code' => $code]);
```

## A panel of your own

A panel is a method that collects the data and a view that draws it. Both go in your application.

```php
// application/classes/UB_Debug.php
class UB_Debug extends UB_Debug {

	/**
	 * Feature switches of the application.
	 */
	protected static function _panel_features(): ?array
	{
		$features = Kohana::$config->load('features')->as_array();

		return [
			'title' => 'Features',
			'badge' => (string) count(array_filter($features)),
			'data'  => ['features' => $features],
		];
	}

}
```

```php
<?php // application/views/ub_debug/panels/features.php ?>
<table>
	<?php foreach ($features as $name => $on): ?>
	<tr>
		<th class="k-db-key"><?php echo HTML::chars($name) ?></th>
		<td><?php echo $on ? 'on' : 'off' ?></td>
	</tr>
	<?php endforeach ?>
</table>
```

```php
// application/config/ub_debug.php
return [
	'panels' => [
		'features' => true,
	],
];
```

- The method is named `_panel_` plus the name in the config file; the view is `ub_debug/panels/` plus that name.
- Return `null` to leave the panel out for this request.
- The keys of `data` become the variables of the view.
- Show values with `UB_Debug::value($name, $value)`, so secrets stay hidden, and plain text with `HTML::chars()`.

[!!] The bar must work under a strict Content-Security-Policy: write no `style="..."` and no `onclick="..."` in the view of a panel. Use the classes the other panels use.

## The methods

| Method | Does |
|---|---|
| `UB_Debug::disable()` | No bar for this request |
| `UB_Debug::enabled()` | Is the bar shown for this request? |
| `UB_Debug::render()` | The HTML of the bar, to put it somewhere yourself |
| `UB_Debug::value($name, $value)` | The HTML of a value, hidden when its name is secret |
| `UB_Debug::sql($sql)` | A query without the text values in it |
| `Profiler::add($title, $data)` | Adds data to the **Custom** panel |
