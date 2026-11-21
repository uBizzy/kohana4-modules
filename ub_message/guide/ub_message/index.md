# Messages

`UB_Message` keeps short messages for the visitor in the session until a page shows them. That is what "Saved" needs after a form: the controller redirects, and the next page tells.

	// application/bootstrap.php
	Kohana::modules([
		'ub_message' => MODPATH.'ub_message',
	]);

## Requirements

- Kohana 4 and nothing else: no other module, no database, no extension of PHP beyond those of the framework.
- A session that works (`Session::instance()`): that is where the messages wait.

## Adding a message

	UB_Message::success('The invoice was saved.');
	UB_Message::info('Your trial ends in 3 days.');
	UB_Message::warning('The file is large and may take a while.');
	UB_Message::danger('The invoice could not be sent.');

Each takes options as a second argument:

Option | What it is
--- | ---
`title` | a short title above the text
`inline` | `true` prints it where the view asks for inline messages; otherwise it floats
`html` | `true` prints the text as HTML (see below)
`duration` | milliseconds until a floating message goes away; 0 never
`dismissible` | can it be closed?

	UB_Message::success('Saved.', ['title' => 'Invoice 12', 'duration' => 4000]);

## Showing them

Floating messages sit in a corner, over the page. Print them once, in the layout:

	<body>
		...
		<?php echo UB_Message::render() ?>
	</body>

Inline messages are part of the page: print them where they belong, for example above a form.

	UB_Message::danger('Check the fields below.', ['inline' => true]);

	<?php echo UB_Message::render(true) ?>
	<form ...>

A message is shown once: `render()` forgets what it printed. The first `render()` of a request also prints the small style and script the messages need, with the nonce of the Content-Security-Policy.

## Text and HTML

The text of a message is printed as text. A name with `<` in it shows as it is, and cannot run a script:

	UB_Message::success('Saved '.$invoice->customer);    // safe, whatever the customer is called

A message that must hold HTML says so. Then it is your job that nothing a visitor typed is in it, or that it is escaped:

	UB_Message::info('See <a href="/help">the help</a>.', ['html' => true]);

	UB_Message::info('Saved <strong>'.HTML::chars($name).'</strong>.', ['html' => true]);

## Asking

	UB_Message::has();           // any message?
	UB_Message::has(true);       // an inline one?
	UB_Message::get();           // all of them, as arrays, and forgets them
	UB_Message::get(null, false);   // ...and keeps them
	UB_Message::clear();

## For a script

	// In a controller that answers a request of a script
	$this->response->json(['saved' => true, 'messages' => json_decode(UB_Message::json())]);

`UB_Message::json()` gives all messages and forgets them. The script must add a text to the page as text (`textContent`), and as HTML only when the message has `html` set.

When a page gets its messages this way and never calls `render()`, print the style and the script once in the layout with `echo UB_Message::assets()`. Messages that the script adds with the same markup and data attributes (`data-ub-message`, `data-ub-message-close`) can then be closed like the others.

## The look

`config/ub_message.php`:

Setting | Default | What it is
--- | --- | ---
`style` | `'theme'` | `'theme'`: the class names of the module, in the colours of the framework theme. `'bulma'`: the class names of Bulma (`notification is-success`, `delete`).
`duration` | `0` | milliseconds until a floating message goes away, when the message does not say
`dismissible` | `true` | can messages be closed, when the message does not say
`view` | `'ub_message/list'` | the view that prints a list of messages

With `'bulma'`, the module only writes the class names: your site loads Bulma itself. Where the floating messages sit (`.ub-message-float`) comes from the module in both styles.

To change the markup, copy `views/ub_message/list.php` to `application/views/ub_message/` and change the copy. Keep the `data-ub-message...` attributes: the script finds the messages by them. To change the colours of the style `'theme'`, change the theme (`config/theme.php`) or write your own rules for `.ub-message-success` and the others.
