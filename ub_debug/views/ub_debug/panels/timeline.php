<?php
/**
 * Everything the profiler measured, in the order it started.
 *
 * $rows      list of: group, name, note, start and duration in ms, left and width in percent
 * $total     time of the whole request in ms
 * $hidden    number of benchmarks left out because they were too short
 * $shortest  the limit for that, in ms
 * $groups    by group: number of benchmarks listed, for the filter
 * $off       groups that start switched off
 */
?>
<?php if ( ! $rows): ?>
<p class="k-db-empty">Nothing was measured.</p>
<?php else: ?>
<p class="k-db-filter">Show:
	<?php foreach ($groups as $group => $count): ?>
	<button type="button" class="k-db-tab" data-k-db-group="<?php echo HTML::chars($group) ?>" aria-pressed="<?php echo in_array($group, $off, true) ? 'false' : 'true' ?>"><?php echo HTML::chars($group) ?> <span class="k-db-badge"><?php echo (int) $count ?></span></button>
	<?php endforeach ?>
</p>
<table>
	<thead><tr><th>Group</th><th>What</th><th>Result</th><th>Start</th><th>Time</th><th>0 to <?php echo number_format($total) ?> ms</th></tr></thead>
	<?php foreach ($rows as $row): ?>
	<tr data-k-db-row="<?php echo HTML::chars($row['group']) ?>"<?php echo in_array($row['group'], $off, true) ? ' hidden' : '' ?>>
		<th class="k-db-key"><?php echo HTML::chars($row['group']) ?></th>
		<td><code><?php echo HTML::chars(Text::limit_chars($row['name'], 160)) ?></code></td>
		<td><?php echo HTML::chars(Text::limit_chars($row['note'], 160)) ?></td>
		<td class="k-db-number"><?php echo number_format($row['start'], 1) ?> ms</td>
		<td class="k-db-number"><?php echo number_format($row['duration'], 2) ?> ms</td>
		<td><div class="k-db-track"><div class="k-db-span k-db-group-<?php echo HTML::chars(strtok($row['group'], ' ')) ?>" data-k-db-left="<?php echo number_format($row['left'], 2, '.', '') ?>" data-k-db-width="<?php echo number_format($row['width'], 2, '.', '') ?>"></div></div></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endif ?>
<?php if ($hidden): ?>
<p class="k-db-empty"><?php echo (int) $hidden ?> more took less than <?php echo HTML::chars( (string) $shortest) ?> ms each and are not listed ("timeline_shortest" in config/ub_debug.php).</p>
<?php endif ?>
