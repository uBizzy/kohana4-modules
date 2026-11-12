<?php
/**
 * The debug bar: the line that is always visible, and the panels that
 * open above it.
 *
 * $summary  the numbers on the bar, see UB_Debug::summary()
 * $panels   by name: title, badge and data, see UB_Debug::panels()
 *
 * No inline event handlers and no style attributes, so the bar works
 * under a strict Content-Security-Policy.
 */
$nonce = HTML::chars(Security::nonce());
?>
<style nonce="<?php echo $nonce ?>">
<?php echo Theme::css() ?>
<?php include Kohana::find_file('views', 'kohana/debug', 'css') ?>
<?php include Kohana::find_file('views', 'ub_debug/bar', 'css') ?>
</style>
<script nonce="<?php echo $nonce ?>"><?php echo Theme::script() ?></script>
<div id="ub-debug" class="k-debug ub-debug">
	<div class="k-db-panels">
		<?php foreach ($panels as $name => $panel): ?>
		<section class="k-db-panel" id="k-db-panel-<?php echo HTML::chars($name) ?>" hidden>
			<h2><?php echo HTML::chars($panel['title']) ?></h2>
			<?php if (isset($panel['data']['error'])): ?>
				<p class="k-db-empty">This panel could not be made: <?php echo HTML::chars($panel['data']['error']) ?></p>
			<?php else: ?>
				<?php echo View::factory('ub_debug/panels/'.$name, $panel['data'])->render() ?>
			<?php endif ?>
		</section>
		<?php endforeach ?>
	</div>
	<div class="k-db-bar">
		<button type="button" class="k-db-logo" data-k-db-minimize title="Hide or show the bar"><?php echo Theme::logo() ?></button>
		<span class="k-db-item k-db-status-<?php echo (int) floor($summary['status'] / 100) ?>" title="Method and status of the response"><?php echo HTML::chars($summary['method']), ' ', (int) $summary['status'] ?></span>
		<span class="k-db-item" title="Time of the request"><?php echo number_format($summary['time'] * 1000) ?> ms</span>
		<span class="k-db-item" title="Most memory used"><?php echo number_format($summary['memory'] / 1048576, 1) ?> MB</span>
		<?php if ($summary['controller'] !== ''): ?>
		<span class="k-db-item k-db-route" title="Controller and action; route: <?php echo HTML::chars($summary['route']) ?>"><?php echo HTML::chars($summary['controller'].'::'.$summary['action']) ?></span>
		<?php endif ?>
		<span class="k-db-tabs">
			<?php foreach ($panels as $name => $panel): ?>
			<button type="button" class="k-db-tab" data-k-db-tab="<?php echo HTML::chars($name) ?>" aria-expanded="false"><?php echo HTML::chars($panel['title']) ?><?php if ($panel['badge'] !== ''): ?> <span class="k-db-badge" id="k-db-badge-<?php echo HTML::chars($name) ?>"><?php echo HTML::chars($panel['badge']) ?></span><?php endif ?></button>
			<?php endforeach ?>
		</span>
		<span class="k-db-end">
			<span class="k-db-item k-db-version">PHP <?php echo HTML::chars($summary['php']) ?> · Kohana <?php echo HTML::chars($summary['kohana']) ?></span>
			<?php echo Theme::button() ?>
			<button type="button" class="k-db-close" data-k-db-close title="Close the open panel">×</button>
		</span>
	</div>
</div>
<script nonce="<?php echo $nonce ?>"><?php include Kohana::find_file('views', 'ub_debug/bar', 'js') ?></script>
