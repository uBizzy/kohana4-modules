<?php
/**
 * A list of messages.
 *
 * $messages  the messages: type, text, title, html, duration, dismissible
 * $inline    are these the inline ones? (false: they float over the page)
 * $classes   the class names of the style: float, inline, message, close
 *
 * No inline event handlers and no style attributes: the button and the
 * time are read by views/ub_message/message.js from data attributes.
 */
?>
<div class="<?php echo HTML::chars($classes[$inline ? 'inline' : 'float']) ?>" role="status" aria-live="polite">
<?php foreach ($messages as $message): ?>
	<div class="<?php echo HTML::chars(strtr($classes['message'], [':type' => $message['type']])) ?>" data-ub-message<?php echo ( ! $inline AND $message['duration'] > 0) ? ' data-ub-message-duration="'.(int) $message['duration'].'"' : '' ?>>
		<?php if ($message['dismissible']): ?>
		<button type="button" class="<?php echo HTML::chars($classes['close']) ?>" data-ub-message-close aria-label="<?php echo HTML::chars(__('Close')) ?>"></button>
		<?php endif ?>
		<?php if ($message['title'] !== ''): ?>
		<strong><?php echo HTML::chars($message['title']) ?></strong>
		<?php endif ?>
		<span><?php echo $message['html'] ? $message['text'] : nl2br(HTML::chars($message['text']), false) ?></span>
	</div>
<?php endforeach ?>
</div>
