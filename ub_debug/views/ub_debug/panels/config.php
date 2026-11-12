<?php
/**
 * The config groups listed in "config_groups" of config/ub_debug.php.
 *
 * $groups  by group name: its values
 */
?>
<?php if ( ! $groups): ?>
<p class="k-db-empty">No config groups are listed in "config_groups" of config/ub_debug.php.</p>
<?php endif ?>
<?php foreach ($groups as $group => $values): ?>
<h3><?php echo HTML::chars($group) ?></h3>
<table>
	<?php foreach ($values as $name => $value): ?>
	<tr>
		<th class="k-db-key"><?php echo HTML::chars( (string) $name) ?></th>
		<td><?php echo UB_Debug::value($name, $value) ?></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endforeach ?>
