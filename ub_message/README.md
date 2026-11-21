# Messages module (ub_message)

Messages for the visitor that are kept in the session until a page shows them: "Saved", "Could not send", after a redirect too.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_message' => MODPATH.'ub_message',
]);
```

```php
// In a controller
UB_Message::success('The invoice was saved.');
$this->redirect('invoices');
```

```php
// In the layout
echo UB_Message::render();
```

- Floating messages, or inline ones printed where the view wants them.
- The text is printed as text; HTML only when the message says so.
- Works under a strict Content-Security-Policy.
- The colours of the framework theme, or the class names of Bulma.

The full documentation is in `guide/ub_message/` and in the user guide of your application.
