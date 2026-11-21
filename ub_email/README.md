# Email module (ub_email)

Sends email with PHPMailer: HTML with a text version made by itself, views as messages, attachments.

```php
// application/bootstrap.php
Kohana::modules([
	'ub_autoload' => MODPATH.'ub_autoload',   // loads PHPMailer
	'ub_email'    => MODPATH.'ub_email',
]);
```

```php
$sent = UB_Email::factory()
	->to('ana@example.com', 'Ana')
	->subject('Your invoice')
	->view('email/invoice', ['invoice' => $invoice])
	->attach($pdf)
	->send();
```

- PHPMailer is not part of the module: put it in a `vendor` directory and name it in `config/ub_autoload.php`.
- The connection to the mail server is encrypted and its certificate is checked. A password is never sent in the open.
- `send()` does not throw: it returns false, and why is in the log.
- While you develop, `redirect` in the config sends every message to you in place of the real recipients.

The full documentation is in `guide/ub_email/` and in the user guide of your application.
