<?php
/**
 * The data of the sessions that were opened in this request.
 *
 * $sessions  by session type: its data
 */
?>
<?php if ( ! $sessions): ?>
<p class="k-db-empty">This request used no session.</p>
<?php endif ?>
<?php foreach ($sessions as $type => $values): ?>
<h3><?php echo HTML::chars($type) ?></h3>
<?php if ( ! $values): ?>
<p class="k-db-empty">Empty.</p>
<?php else: ?>
<table>
	<?php foreach ($values as $name => $value): ?>
	<tr>
		<th class="k-db-key"><?php echo HTML::chars( (string) $name) ?></th>
		<td><?php echo UB_Debug::value($name, $value) ?></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endif ?>
<?php endforeach ?>
