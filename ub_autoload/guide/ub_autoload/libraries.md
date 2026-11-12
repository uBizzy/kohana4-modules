# Libraries

The config lines for three well-known libraries, each tried with this module. For any other library, open its `composer.json` and copy what is under `"autoload"`: see [the four ways](index).

Download a release of the library from its site and unpack it into a `vendor` directory, so that the paths below fit:

```text
application/vendor/
	phpmailer/src/PHPMailer.php
	htmlpurifier/library/HTMLPurifier.php
	tcpdf/tcpdf.php
```

All three together:

```php
// application/config/ub_autoload.php
return [
	'psr4' => [
		'PHPMailer\\PHPMailer\\' => 'phpmailer/src',
	],
	'psr0' => [
		'HTMLPurifier' => 'htmlpurifier/library',
	],
	'classmap' => [
		'TCPDF' => 'tcpdf/tcpdf.php',
	],
	'files' => [
		'htmlpurifier/library/HTMLPurifier.composer.php',
	],
];
```

## PHPMailer

Sends email, through an SMTP server or the mail program of the server. From [github.com/PHPMailer/PHPMailer](https://github.com/PHPMailer/PHPMailer) (tried with 7.2).

```php
'psr4' => [
	'PHPMailer\\PHPMailer\\' => 'phpmailer/src',
],
```

```php
use PHPMailer\PHPMailer\PHPMailer;

$smtp = Kohana::$config->load('mail');

// true: errors are exceptions
$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->Host       = $smtp['host'];
$mail->Port       = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->SMTPAuth   = true;
$mail->Username   = $smtp['username'];
$mail->Password   = $smtp['password'];
$mail->CharSet    = PHPMailer::CHARSET_UTF8;

$mail->setFrom('shop@example.com', 'My Shop');
$mail->addAddress($user->email, $user->username);
$mail->Subject = 'Your order';
$mail->Body    = View::factory('mail/order', ['order' => $order])->render();
$mail->isHTML(true);

$mail->send();
```

[!!] The password of the mail server belongs in a config file (`config/mail.php` here), not in the code and not in a setting that is kept in the database.

## HTML Purifier

Cleans HTML that somebody typed, so it can be shown as HTML: what a rich text editor sends. From [htmlpurifier.org](http://htmlpurifier.org) (tried with 4.19).

```php
'psr0' => [
	'HTMLPurifier' => 'htmlpurifier/library',
],
'files' => [
	// The library asks for this file to be included
	'htmlpurifier/library/HTMLPurifier.composer.php',
],
```

```php
$config = HTMLPurifier_Config::createDefault();

// Where it keeps what it worked out: a directory the server may write to
$config->set('Cache.SerializerPath', APPPATH.'cache');

$purifier = new HTMLPurifier($config);

// <p>Hi <b>there</b></p>
$clean = $purifier->purify('<p onclick="steal()">Hi <script>alert(1)</script><b>there</b>');
```

- Without `Cache.SerializerPath` the library writes inside its own directory, which the server should not be allowed to do.
- Purify when the text is **saved**, and print the stored result without `HTML::chars()`. Everything else that was typed by somebody is still printed with `HTML::chars()`: see [XSS](../kohana/security/xss).

## TCPDF

Makes PDF files. From [github.com/tecnickcom/TCPDF](https://github.com/tecnickcom/TCPDF) (tried with 6.11).

```php
'classmap' => [
	'TCPDF' => 'tcpdf/tcpdf.php',
],
```

```php
$pdf = new TCPDF;

$pdf->SetTitle('Invoice 17');
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);
$pdf->Write(0, 'Invoice 17');

// "S" returns the PDF as text
$this->response
	->headers('Content-Type', 'application/pdf')
	->body($pdf->Output('invoice-17.pdf', 'S'));
```

[!!] Take a release of the **6** series. From version 7 on, TCPDF is a thin layer over another library of the same author (`tc-lib-pdf`) that is made of many packages: install that one with Composer.

## Another library

1. Open the `composer.json` of the library and find `"autoload"`.
2. Copy each part to the key with the same name: `"psr-4"` to `psr4`, `"psr-0"` to `psr0`, `"classmap"` to `classmap` (one line for each class you use), `"files"` to `files`.
3. Put the directory of the library in front of each path: `"src/"` of a library in `vendor/acme/` is `'acme/src'`.
4. Look at `"require"`: a library that needs other libraries needs them in `vendor` and in the config too. When that list is long, Composer is the better tool.

```php
// "autoload": {"psr-4": {"Acme\\Pdf\\": "src/"}, "files": ["src/functions.php"]}

return [
	'psr4' => [
		'Acme\\Pdf\\' => 'acme-pdf/src',
	],
	'files' => [
		'acme-pdf/src/functions.php',
	],
];
```
