<?php
/**
 * The request and its response.
 *
 * $route             method, URI, status, route, controller, action
 * $params            parameters taken from the URI by the route
 * $get, $post        input
 * $cookies           names of the cookies, with the length of their values only
 * $request_headers   headers that came in
 * $response_headers  headers that go out
 *
 * Every value goes through UB_Debug::value(), which hides secrets.
 */
$sections = [
	'Route'            => $route,
	'Route parameters' => $params,
	'GET'              => $get,
	'POST'             => $post,
	'Cookies (values are never shown)' => $cookies,
	'Request headers'  => $request_headers,
	'Response headers' => $response_headers,
];
?>
<?php foreach ($sections as $title => $values): ?>
<h3><?php echo HTML::chars($title) ?></h3>
<?php if ( ! $values): ?>
<p class="k-db-empty">None.</p>
<?php else: ?>
<table>
	<?php foreach ($values as $name => $value): ?>
	<tr>
		<th class="k-db-key"><?php echo HTML::chars( (string) $name) ?></th>
		<td><?php echo is_scalar($value) && ! Debug::is_sensitive(str_replace('-', '_', (string) $name)) ? HTML::chars( (string) $value) : UB_Debug::value($name, $value) ?></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endif ?>
<?php endforeach ?>
