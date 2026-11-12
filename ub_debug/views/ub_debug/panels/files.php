<?php
/**
 * The files of the request.
 *
 * $groups   by origin (Application, Module name, System, Vendor): list of short paths
 * $classes  list of: class, file, origin: the classes that were loaded, in order
 * $missing  by file that was looked for and does not exist: how often
 */
?>
<?php if ($missing): ?>
<h3>Looked for, not found</h3>
<p class="k-db-empty">Every one of these is a search through the application, all modules and the system.</p>
<table>
	<thead><tr><th>File</th><th>Times</th></tr></thead>
	<?php foreach ($missing as $file => $times): ?>
	<tr class="k-db-marked">
		<td><code><?php echo HTML::chars($file) ?></code></td>
		<td class="k-db-number"><?php echo (int) $times ?></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endif ?>

<h3>Loaded files</h3>
<table>
	<?php foreach ($groups as $origin => $files): ?>
	<tr>
		<th class="k-db-key"><?php echo HTML::chars($origin) ?> (<?php echo count($files) ?>)</th>
		<td>
			<button type="button" data-k-db-more="k-db-files-<?php echo md5($origin) ?>">show</button>
			<ul class="k-db-columns" id="k-db-files-<?php echo md5($origin) ?>" hidden>
				<?php foreach ($files as $file): ?>
				<li><code><?php echo HTML::chars($file) ?></code></li>
				<?php endforeach ?>
			</ul>
		</td>
	</tr>
	<?php endforeach ?>
</table>

<?php if ($classes): ?>
<h3>Classes loaded by the autoloader, in order (<?php echo count($classes) ?>)</h3>
<table>
	<thead><tr><th>Class</th><th>From</th><th>File</th></tr></thead>
	<?php foreach ($classes as $class): ?>
	<tr>
		<td><code><?php echo HTML::chars($class['class']) ?></code></td>
		<td><?php echo HTML::chars($class['origin']) ?></td>
		<td><code><?php echo HTML::chars($class['file']) ?></code></td>
	</tr>
	<?php endforeach ?>
</table>
<?php endif ?>
