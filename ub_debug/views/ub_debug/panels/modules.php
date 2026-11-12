<?php
/**
 * The enabled modules, in the order they are searched.
 *
 * $modules  by name: short path
 */
?>
<?php if ( ! $modules): ?>
<p class="k-db-empty">No modules.</p>
<?php else: ?>
<table>
	<?php foreach ($modules as $name => $path): ?>
	<tr>
		<th class="k-db-key"><?php echo HTML::chars($name) ?></th>
		<td><code><?php echo HTML::chars($path) ?></code></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endif ?>
