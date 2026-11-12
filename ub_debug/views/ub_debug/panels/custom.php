<?php
/**
 * Data added with Profiler::add().
 *
 * $items  by title: the value
 */
?>
<?php foreach ($items as $title => $value): ?>
<h3><?php echo HTML::chars( (string) $title) ?></h3>
<pre><?php echo UB_Debug::value($title, $value) ?></pre>
<?php endforeach ?>
