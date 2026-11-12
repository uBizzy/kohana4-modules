<?php
/**
 * Versions, limits and extensions of the server.
 *
 * $values      by label: value
 * $extensions  list of extension names
 */
?>
<table>
	<?php foreach ($values as $name => $value): ?>
	<tr>
		<th class="k-db-key"><?php echo HTML::chars($name) ?></th>
		<td><?php echo HTML::chars( (string) $value) ?></td>
	</tr>
	<?php endforeach ?>
</table>
<h3>PHP extensions</h3>
<ul class="k-db-columns">
	<?php foreach ($extensions as $extension): ?>
	<li><?php echo HTML::chars($extension) ?></li>
	<?php endforeach ?>
</ul>
