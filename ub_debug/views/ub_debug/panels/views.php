<?php
/**
 * The views that were rendered.
 *
 * $rows  list of: file, count, time in ms, variables (names only)
 */
?>
<?php if ( ! $rows): ?>
<p class="k-db-empty">No views were rendered.</p>
<?php else: ?>
<table>
	<thead><tr><th>View</th><th>Variables it was given</th><th>Times</th><th>Time</th></tr></thead>
	<?php foreach ($rows as $row): ?>
	<tr>
		<td><code><?php echo HTML::chars($row['file']) ?></code></td>
		<td><code><?php echo HTML::chars($row['variables']) ?></code></td>
		<td class="k-db-number"><?php echo (int) $row['count'] ?></td>
		<td class="k-db-number"><?php echo number_format($row['time'], 2) ?> ms</td>
	</tr>
	<?php endforeach ?>
</table>
<p class="k-db-empty">The time of a view includes the views it renders inside it. Only the names of the variables are shown, never their values.</p>
<?php endif ?>
