<?php
/**
 * The database queries of every connection.
 *
 * $connections  by profiler group: list of sql, count, rows, time in ms, repeated, callers
 *               (rows: returned or changed by all runs of the query together)
 *               (callers: by place in the code, how often the query ran from there)
 * $count        number of queries
 * $time         time of all queries in ms
 * $duplicates   number of queries that ran more than once
 */
?>
<?php if ( ! $count): ?>
<p class="k-db-empty">No database queries.</p>
<?php else: ?>
<p><?php echo (int) $count ?> queries in <?php echo number_format($time, 2) ?> ms.
<?php if ($duplicates): ?><span class="k-db-tag"><?php echo (int) $duplicates ?> of them repeat a query that already ran</span><?php endif ?></p>
<?php foreach ($connections as $group => $rows): ?>
<h3><?php echo HTML::chars($group) ?></h3>
<table>
	<thead><tr><th>Query</th><th>Times</th><th>Rows</th><th>Time</th></tr></thead>
	<?php foreach ($rows as $row): ?>
	<tr<?php echo $row['repeated'] ? ' class="k-db-marked"' : '' ?>>
		<td>
			<code><?php echo HTML::chars($row['sql']) ?></code><?php if ($row['repeated']): ?> <span class="k-db-tag">repeated</span><?php endif ?>
			<?php foreach ($row['callers'] as $place => $times): ?>
			<div class="k-db-where"><?php echo HTML::chars($place) ?><?php echo $times > 1 ? ' × '.(int) $times : '' ?></div>
			<?php endforeach ?>
		</td>
		<td class="k-db-number"><?php echo (int) $row['count'] ?></td>
		<td class="k-db-number"><?php echo number_format($row['rows']) ?></td>
		<td class="k-db-number"><?php echo number_format($row['time'], 2) ?> ms</td>
	</tr>
	<?php endforeach ?>
</table>
<?php endforeach ?>
<?php endif ?>
