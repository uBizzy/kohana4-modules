<?php
/**
 * Every route, in the order they are tried.
 *
 * $rows  list of: name, uri, current
 */
?>
<table>
	<thead><tr><th>Name</th><th>Pattern</th></tr></thead>
	<?php foreach ($rows as $row): ?>
	<tr<?php echo $row['current'] ? ' class="k-db-current"' : '' ?>>
		<th class="k-db-key"><?php echo HTML::chars($row['name']) ?><?php echo $row['current'] ? ' (this page)' : '' ?></th>
		<td><code><?php echo HTML::chars($row['uri']) ?></code></td>
	</tr>
	<?php endforeach ?>
</table>
