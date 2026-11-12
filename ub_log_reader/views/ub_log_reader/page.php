<?php
/**
 * The page that searches the logs.
 *
 * $filters  what the form holds: level, text, reference, request, from, to
 * $limit    how many entries are asked for
 * $entries  the entries that fit, see UB_Log_Reader::search()
 * $top      the most frequent messages, see UB_Log_Reader::top(); null when the entries are shown
 * $indexed  is an index of the logs used?
 * $days     the days there are logs of
 * $levels   the names of the levels
 *
 * No inline event handlers and no style attributes, so the
 * page works under a strict Content-Security-Policy. Everything that
 * comes from a log is written as text.
 */
$nonce = HTML::chars(Security::nonce());
$link  = fn (array $change): string => HTML::chars(URL::site(Route::get('ub-log-reader')->uri()).URL::query($change + array_filter($filters) + ['limit' => $limit], false));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Log Reader</title>
<?php echo Theme::favicon() ?>

<style nonce="<?php echo $nonce ?>">
<?php echo Theme::css() ?>
<?php include Kohana::find_file('views', 'ub_log_reader/page', 'css') ?>
</style>
<script nonce="<?php echo $nonce ?>"><?php echo Theme::script() ?></script>
</head>
<body class="ub-log">

<header>
	<?php echo Theme::logo() ?>
	<h1>Log Reader</h1>
	<?php echo Theme::button() ?>
</header>

<form method="get" action="<?php echo HTML::chars(URL::site(Route::get('ub-log-reader')->uri())) ?>">
	<label>Level, and worse
		<select name="level">
			<option value="">any</option>
			<?php foreach ($levels as $level): ?>
			<option value="<?php echo HTML::chars($level) ?>"<?php echo (strtoupper($filters['level']) === $level) ? ' selected' : '' ?>><?php echo HTML::chars($level) ?></option>
			<?php endforeach ?>
		</select>
	</label>
	<label>Text
		<input type="search" name="text" value="<?php echo HTML::chars($filters['text']) ?>">
	</label>
	<label>Error code
		<input type="text" name="reference" size="10" value="<?php echo HTML::chars($filters['reference']) ?>">
	</label>
	<label>Request
		<input type="text" name="request" size="10" value="<?php echo HTML::chars($filters['request']) ?>">
	</label>
	<label>From
		<input type="text" name="from" size="12" placeholder="2026-11-12" value="<?php echo HTML::chars($filters['from']) ?>">
	</label>
	<label>To
		<input type="text" name="to" size="12" placeholder="today" value="<?php echo HTML::chars($filters['to']) ?>">
	</label>
	<label>At most
		<input type="number" name="limit" min="1" max="<?php echo UB_Log_Reader::MAX ?>" value="<?php echo (int) $limit ?>">
	</label>
	<?php if ($top !== null): ?>
	<input type="hidden" name="show" value="top">
	<?php endif ?>
	<button type="submit">Search</button>
</form>

<p class="ub-log-tabs">
	<?php if ($top === null): ?>
		<strong>Entries</strong> &middot; <a href="<?php echo $link(['show' => 'top']) ?>">Most frequent</a>
	<?php else: ?>
		<a href="<?php echo $link([]) ?>">Entries</a> &middot; <strong>Most frequent</strong>
	<?php endif ?>
</p>

<p class="ub-log-note">
	<?php if ($top === null): ?>
	<?php echo count($entries) ?> <?php echo (count($entries) === 1) ? 'entry' : 'entries' ?>, the newest first.
	<?php else: ?>
	<?php echo count($top) ?> <?php echo (count($top) === 1) ? 'message' : 'messages' ?>, the most frequent first; messages that differ only in their numbers are counted as one.
	<?php endif ?>
	Logs of <?php echo count($days) ?> <?php echo (count($days) === 1) ? 'day' : 'days' ?><?php echo $days ? ', from '.HTML::chars((string) end($days)).' to '.HTML::chars((string) reset($days)) : '' ?>.
	What looks like a password, a token or a key is shown as ***.
	<?php echo $indexed ? 'The index is used.' : '' ?>
</p>

<?php if ($entries): ?>
<table>
	<thead>
		<tr><th>Time</th><th>Level</th><th>Message</th></tr>
	</thead>
	<tbody>
	<?php foreach ($entries as $entry): ?>
		<tr>
			<td class="ub-log-time"><?php echo HTML::chars(date('Y-m-d H:i:s', (int) $entry['time'])) ?></td>
			<td><span class="ub-log-level ub-log-<?php echo HTML::chars((string) $entry['level']) ?>"><?php echo HTML::chars((string) $entry['level']) ?></span></td>
			<td>
				<div class="ub-log-message"><?php echo HTML::chars((string) $entry['message']) ?></div>
				<div class="ub-log-where">
					<?php if (is_string($entry['file'])): ?>
						<?php echo HTML::chars($entry['file'].(is_int($entry['line']) ? ':'.$entry['line'] : '')) ?>
					<?php endif ?>
					<?php if (is_string($entry['method']) OR is_string($entry['uri'])): ?>
						&middot; <?php echo HTML::chars(trim($entry['method'].' '.$entry['uri'])) ?>
					<?php endif ?>
					<?php if (is_string($entry['reference'])): ?>
						&middot; code <a href="<?php echo $link(['reference' => $entry['reference']]) ?>"><?php echo HTML::chars($entry['reference']) ?></a>
					<?php endif ?>
					<?php if (is_string($entry['request'])): ?>
						&middot; request <a href="<?php echo $link(['request' => $entry['request'], 'level' => '', 'text' => '', 'reference' => '']) ?>"><?php echo HTML::chars($entry['request']) ?></a>
					<?php endif ?>
				</div>
				<?php if (is_string($entry['trace'])): ?>
				<details>
					<summary>Trace</summary>
					<pre><?php echo HTML::chars($entry['trace']) ?></pre>
				</details>
				<?php endif ?>
			</td>
		</tr>
	<?php endforeach ?>
	</tbody>
</table>
<?php endif ?>

<?php if ($top): ?>
<table>
	<thead>
		<tr><th>Times</th><th>Level</th><th>First</th><th>Last</th><th>Message</th></tr>
	</thead>
	<tbody>
	<?php foreach ($top as $group): ?>
		<tr>
			<td class="ub-log-number"><?php echo number_format($group['count']) ?></td>
			<td><span class="ub-log-level ub-log-<?php echo HTML::chars($group['level']) ?>"><?php echo HTML::chars($group['level']) ?></span></td>
			<td class="ub-log-time"><?php echo HTML::chars(date('Y-m-d H:i', $group['first'])) ?></td>
			<td class="ub-log-time"><?php echo HTML::chars(date('Y-m-d H:i', $group['last'])) ?></td>
			<td>
				<div class="ub-log-message"><?php echo HTML::chars($group['message']) ?></div>
				<?php if (is_string($group['reference'])): ?>
				<div class="ub-log-where">the newest: code <a href="<?php echo $link(['reference' => $group['reference'], 'show' => '']) ?>"><?php echo HTML::chars($group['reference']) ?></a></div>
				<?php endif ?>
			</td>
		</tr>
	<?php endforeach ?>
	</tbody>
</table>
<?php endif ?>

</body>
</html>
