<?php
/**
 * Log messages and notices of the request.
 *
 * $rows  list of: level, body, file, line
 */
?>
<?php if ( ! $rows): ?>
<p class="k-db-empty">Nothing was logged.</p>
<?php else: ?>
<table>
	<thead><tr><th>Level</th><th>Message</th><th>Where</th></tr></thead>
	<?php foreach ($rows as $row): ?>
	<tr>
		<td><span class="k-db-level"><?php echo HTML::chars($row['level']) ?></span></td>
		<td><?php echo HTML::chars($row['body']) ?></td>
		<td><?php if ($row['file']): ?><code><?php echo HTML::chars(Debug::path($row['file'])), ' [ ', (int) $row['line'], ' ]' ?></code><?php endif ?></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endif ?>
