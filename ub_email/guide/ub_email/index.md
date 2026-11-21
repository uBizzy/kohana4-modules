# Email

`UB_Email` sends email with [PHPMailer](https://github.com/PHPMailer/PHPMailer). It adds what an application repeats: who the messages come from, the mail server, a layout around every message, a text version of HTML, and a way to keep test messages away from real people.

## Requirements

- Kohana 4.
- PHPMailer, which is not part of the module (tested with version 7). Anything that makes the class `PHPMailer\PHPMailer\PHPMailer` loadable will do; the ub_autoload module is the simple way, and the steps below use it.
- For the transport `smtp`: the `openssl` extension of PHP, for the encrypted connection.
- No database.

## Set up

PHPMailer is not part of the module. Put its `src` directory in a `vendor` directory of the application (`application/vendor/phpmailer/src`) and let the `ub_autoload` module load it:

	// application/config/ub_autoload.php
	return [
		'psr4' => [
			'PHPMailer\\PHPMailer\\' => 'phpmailer/src',
		],
	];

	// application/bootstrap.php
	Kohana::modules([
		'ub_autoload' => MODPATH.'ub_autoload',
		'ub_email'    => MODPATH.'ub_email',
	]);

Copy `config/ub_email.php` of the module to `application/config/` and set who the messages come from and the mail server.

[!!] The password of the mail server is in that file: keep it out of a public repository.

## Sending

	$sent = UB_Email::factory()
		->to('ana@example.com', 'Ana')
		->subject('Your invoice')
		->html('<p>Hello Ana, your invoice is attached.</p>')
		->attach($path_of_the_pdf)
		->send();

	if ( ! $sent)
	{
		UB_Message::danger('The message could not be sent.');
	}

`send()` returns true or false and never throws. Why a message was not sent is in the log, with the type `email.failed`; a sent message is logged with `email.sent`.

### Who

	->to('ana@example.com', 'Ana')
	->to(['ana@example.com' => 'Ana', 'bob@example.com'])    // several
	->cc(...)  ->bcc(...)  ->reply_to(...)
	->from('sales@example.com', 'Sales')                    // in place of the config

What is not an email address throws a `Kohana_Exception` at once, so a form can tell the visitor.

### What

	->html($html)                                  // HTML, with a text version made from it
	->view('email/welcome', ['user' => $user])     // the HTML of a view
	->text($text)                                  // plain text only

Around an HTML message goes the layout of the config (`layout`): a view that gets `$content` and `$subject`. Copy `views/ub_email/layout.php` to your application to change it, or name another view. `null` sends the HTML as it is.

[!!] A message is HTML that somebody else's program shows. Escape what comes from visitors (`HTML::chars()`) in your message views, as in pages.

Mail programs ignore style sheets: write styles on the elements (`style="..."`) in message views. The Content-Security-Policy of your site does not apply to email.

### Attachments

	->attach('/path/to/file.pdf')
	->attach('/path/to/file.pdf', 'invoice-12.pdf')                  // with another name
	->attach_content($pdf_content, 'invoice-12.pdf', 'application/pdf')   // not a file on the disk

[!!] Never build the path from what a visitor sent: the file would be mailed, whatever it is.

### Anything else

`mailer()` returns the PHPMailer object, for what the class has no method for:

	$email = UB_Email::factory()->to($address)->subject('Hello');

	$email->mailer()->addEmbeddedImage($logo, 'logo');
	$email->mailer()->addCustomHeader('X-Campaign', 'spring');

	$email->html('<img src="cid:logo">')->send();

## The config

Setting | What it is
--- | ---
`transport` | `'smtp'` (a mail server), `'mail'` (the `mail()` function of PHP) or `'sendmail'`
`from` | who the messages come from: `[address, name]`
`reply_to` | where answers go: an address or `[address, name]`; `null` for none
`bcc` | addresses that get a hidden copy of every message
`smtp` | `host`, `port`, `username`, `password`, `encryption` (`'tls'` or `'ssl'`), `timeout`
`dkim` | `domain`, `selector`, `private` (path of the key), `passphrase`: signs the messages when `domain` is set
`layout` | the view around every HTML message; `null` for none
`redirect` | addresses that get every message in place of the real recipients; `null` to send for real

Several groups can live in the file, for several senders: `UB_Email::factory('newsletter')`.

### Encryption

`encryption` is `'tls'` (STARTTLS, usually port 587) or `'ssl'` (usually port 465). The certificate of the mail server is always checked: there is no setting that switches that off. With a user name and no encryption, the module refuses to start: the password would travel in the open.

A mail server on the same machine that asks for no password can be used without encryption (`'encryption' => ''`, no `username`).

### While you develop

	'redirect' => ['you@example.com'],

Every message then goes to you, and to nobody else: the real recipients, copies and hidden copies are removed and named in the subject (`[to: ana@example.com] Your invoice`). Set it in the config of your development machine, and leave it `null` in production.

## With the ub_log module

`ub_log` sends its alerts through this module when it is enabled.
