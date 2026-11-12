<?php
/**
 * What the Cache module was asked for in this request.
 *
 * $rows    list of: driver, id, hits, misses
 * $hits    number of values that were found
 * $misses  number of values that were not
 */
?>
<?php if ( ! $hits AND ! $misses): ?>
<p class="k-db-empty">Nothing was read from the cache.</p>
<?php else: ?>
<p><?php echo (int) $hits ?> found, <?php echo (int) $misses ?> not found.</p>
<table>
	<thead><tr><th>Id</th><th>Driver</th><th>Found</th><th>Not found</th></tr></thead>
	<?php foreach ($rows as $row): ?>
	<tr<?php echo $row['misses'] ? ' class="k-db-marked"' : '' ?>>
		<td><code><?php echo HTML::chars($row['id']) ?></code></td>
		<td><?php echo HTML::chars($row['driver']) ?></td>
		<td class="k-db-number"><?php echo (int) $row['hits'] ?></td>
		<td class="k-db-number"><?php echo (int) $row['misses'] ?></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endif ?>
