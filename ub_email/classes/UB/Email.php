<?php

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends email with PHPMailer.
 *
 *     $sent = UB_Email::factory()
 *         ->to('ana@example.com', 'Ana')
 *         ->subject('Your invoice')
 *         ->view('email/invoice', ['invoice' => $invoice])
 *         ->attach($pdf)
 *         ->send();
 *
 * An HTML message gets a text version by itself, and the layout of the
 * config around it. Who it comes from, the mail server and the rest
 * are in config/ub_email.php.
 *
 * PHPMailer is not part of the module: load it with the ub_autoload
 * module (see the guide).
 *
 * - The connection to the mail server is encrypted and its certificate
 *   is checked. There is no setting that switches the check off.
 * - [UB_Email::send] never throws: it returns false, and why is in the
 *   log. The password is not.
 *
 * @package    UB_Email
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Email {

	/**
	 * @var  PHPMailer  The message, and what sends it
	 */
	protected PHPMailer $_mailer;

	/**
	 * @var  string  The subject, as the code set it
	 */
	protected string $_subject = '';

	/**
	 * @var  array<string, mixed>  The group of the config
	 */
	protected array $_config;

	/**
	 * Starts a message.
	 *
	 * @param   string  $group  the group of config/ub_email.php
	 * @return  static
	 * @throws  Kohana_Exception  when PHPMailer is not loaded, or the config is not usable
	 */
	public static function factory(string $group = 'default'): static
	{
		return new static($group);
	}

	/**
	 * @param   string  $group  the group of config/ub_email.php
	 * @throws  Kohana_Exception  when PHPMailer is not loaded, or the config is not usable
	 */
	final public function __construct(string $group = 'default')
	{
		if ( ! class_exists(PHPMailer::class))
			throw new Kohana_Exception('PHPMailer is not loaded. Put it in a vendor directory and name it in config/ub_autoload.php: see the guide of ub_email');

		$config = Kohana::$config->load('ub_email.'.$group);

		if ( ! is_array($config))
			throw new Kohana_Exception('config/ub_email.php has no group :group', [':group' => $group]);

		/** @var array<string, mixed> $config */
		$this->_config = $config;

		// true: PHPMailer throws what goes wrong, and send() catches it
		$this->_mailer = new PHPMailer(true);

		$this->_mailer->CharSet = PHPMailer::CHARSET_UTF8;
		$this->_mailer->XMailer = ' ';

		$this->_transport();
		$this->_defaults();
	}

	/**
	 * Adds who the message goes to.
	 *
	 *     $email->to('ana@example.com', 'Ana');
	 *     $email->to(['ana@example.com' => 'Ana', 'bob@example.com']);
	 *
	 * @param   string|array<int|string, string>  $email  an address, or a list of them: address => name, or addresses
	 * @param   string                            $name   the name, for one address
	 * @return  $this
	 * @throws  Kohana_Exception  for what is no email address
	 */
	public function to(string|array $email, string $name = ''): static
	{
		return $this->_add('addAddress', $email, $name);
	}

	/**
	 * Adds who gets a copy.
	 *
	 * @param   string|array<int|string, string>  $email  as for [UB_Email::to]
	 * @param   string                            $name   the name, for one address
	 * @return  $this
	 * @throws  Kohana_Exception  for what is no email address
	 */
	public function cc(string|array $email, string $name = ''): static
	{
		return $this->_add('addCC', $email, $name);
	}

	/**
	 * Adds who gets a copy that the others do not see.
	 *
	 * @param   string|array<int|string, string>  $email  as for [UB_Email::to]
	 * @param   string                            $name   the name, for one address
	 * @return  $this
	 * @throws  Kohana_Exception  for what is no email address
	 */
	public function bcc(string|array $email, string $name = ''): static
	{
		return $this->_add('addBCC', $email, $name);
	}

	/**
	 * Adds where answers go.
	 *
	 * @param   string|array<int|string, string>  $email  as for [UB_Email::to]
	 * @param   string                            $name   the name, for one address
	 * @return  $this
	 * @throws  Kohana_Exception  for what is no email address
	 */
	public function reply_to(string|array $email, string $name = ''): static
	{
		return $this->_add('addReplyTo', $email, $name);
	}

	/**
	 * Sets who the message comes from, in place of "from" of the config.
	 *
	 * @param   string  $email  the address
	 * @param   string  $name   the name
	 * @return  $this
	 * @throws  Kohana_Exception  for what is no email address
	 */
	public function from(string $email, string $name = ''): static
	{
		try
		{
			$this->_mailer->setFrom($email, $name);
		}
		catch (Throwable $e)
		{
			throw new Kohana_Exception(':email is not an email address', [':email' => $email], 0, $e);
		}

		return $this;
	}

	/**
	 * Sets the subject. Line breaks in it become spaces.
	 *
	 * @param   string  $subject  the subject
	 * @return  $this
	 */
	public function subject(string $subject): static
	{
		$this->_subject = trim((string) preg_replace('/\s+/', ' ', $subject));

		$this->_mailer->Subject = $this->_subject;

		return $this;
	}

	/**
	 * Sets the message as HTML. The layout of the config goes around
	 * it, and a text version is made from it for mail programs that
	 * show no HTML.
	 *
	 * [!!] Escape what comes from visitors (HTML::chars) before it goes
	 * into the HTML.
	 *
	 * @param   string  $html  the message
	 * @return  $this
	 */
	public function html(string $html): static
	{
		$layout = $this->_config['layout'] ?? null;

		// The text version is made of the message, not of the layout
		$text = $this->_mailer->html2text($html);

		if (is_string($layout) AND $layout !== '')
		{
			$html = View::factory($layout, ['content' => $html, 'subject' => $this->_subject])->render();
		}

		$this->_mailer->isHTML(true);
		$this->_mailer->Body    = $html;
		$this->_mailer->AltBody = $text;

		return $this;
	}

	/**
	 * Sets the message as HTML, from a view.
	 *
	 *     $email->view('email/welcome', ['user' => $user]);
	 *
	 * @param   string                $view  name of the view
	 * @param   array<string, mixed>  $data  what the view needs
	 * @return  $this
	 */
	public function view(string $view, array $data = []): static
	{
		return $this->html(View::factory($view, $data)->render());
	}

	/**
	 * Sets the message as plain text, without HTML.
	 *
	 * @param   string  $text  the message
	 * @return  $this
	 */
	public function text(string $text): static
	{
		$this->_mailer->isHTML(false);
		$this->_mailer->Body    = $text;
		$this->_mailer->AltBody = '';

		return $this;
	}

	/**
	 * Attaches a file.
	 *
	 * [!!] Never build the path from what a visitor sent: the file
	 * would be mailed, whatever it is.
	 *
	 * @param   string       $path  path of the file
	 * @param   string|null  $name  the name it has in the message; null keeps its own
	 * @return  $this
	 * @throws  Kohana_Exception  when the file cannot be read
	 */
	public function attach(string $path, ?string $name = null): static
	{
		if ( ! is_file($path) OR ! is_readable($path))
			throw new Kohana_Exception('The file :file cannot be attached: it is not there or cannot be read', [':file' => Debug::path($path)]);

		$this->_mailer->addAttachment($path, $name ?? '');

		return $this;
	}

	/**
	 * Attaches content that is not a file on the disk: a PDF that was
	 * just made, a file from a storage.
	 *
	 * @param   string  $content  the content
	 * @param   string  $name     the name it has in the message
	 * @param   string  $type     its MIME type; empty lets PHPMailer tell from the name
	 * @return  $this
	 */
	public function attach_content(string $content, string $name, string $type = ''): static
	{
		$this->_mailer->addStringAttachment($content, $name, PHPMailer::ENCODING_BASE64, $type);

		return $this;
	}

	/**
	 * Sends the message.
	 *
	 * @return  bool  false when it was not sent; why is in the log
	 */
	public function send(): bool
	{
		try
		{
			$this->_redirect();

			$count = count($this->_recipients());

			$this->_mailer->send();
		}
		catch (Throwable $e)
		{
			Kohana::$log?->add(Log::ERROR, 'ub_email: ":subject" was not sent: :error', [
				':subject' => $this->_subject,
				':error'   => $e->getMessage(),
			], ['type' => 'email.failed']);

			return false;
		}

		Kohana::$log?->add(Log::INFO, 'ub_email: ":subject" was sent to :count address(es)', [
			':subject' => $this->_subject,
			':count'   => $count,
		], ['type' => 'email.sent']);

		return true;
	}

	/**
	 * Returns the PHPMailer object, for what this class has no method
	 * for: embedded images, a priority, headers of your own.
	 *
	 *     $email->mailer()->addCustomHeader('X-Campaign', 'spring');
	 *
	 * @return  PHPMailer
	 */
	public function mailer(): PHPMailer
	{
		return $this->_mailer;
	}

	/**
	 * Tells PHPMailer how messages leave.
	 *
	 * @return  void
	 * @throws  Kohana_Exception  for a transport that does not exist, or a password that would travel in the open
	 */
	protected function _transport(): void
	{
		$transport = $this->_config['transport'] ?? 'smtp';

		if ($transport === 'mail')
		{
			$this->_mailer->isMail();
		}
		elseif ($transport === 'sendmail')
		{
			$this->_mailer->isSendmail();
		}
		elseif ($transport === 'smtp')
		{
			$this->_smtp();
		}
		else
		{
			throw new Kohana_Exception('The transport of ub_email is smtp, mail or sendmail');
		}

		/** @var array<string, mixed> $dkim */
		$dkim = is_array($this->_config['dkim'] ?? null) ? $this->_config['dkim'] : [];

		if (is_string($dkim['domain'] ?? null) AND $dkim['domain'] !== '')
		{
			$this->_mailer->DKIM_domain     = $dkim['domain'];
			$this->_mailer->DKIM_selector   = is_string($dkim['selector'] ?? null) ? $dkim['selector'] : '';
			$this->_mailer->DKIM_private    = is_string($dkim['private'] ?? null) ? $dkim['private'] : '';
			$this->_mailer->DKIM_passphrase = is_string($dkim['passphrase'] ?? null) ? $dkim['passphrase'] : '';
		}
	}

	/**
	 * Tells PHPMailer about the mail server.
	 *
	 * @return  void
	 * @throws  Kohana_Exception  when a password is set and the connection is not encrypted
	 */
	protected function _smtp(): void
	{
		/** @var array<string, mixed> $smtp */
		$smtp = is_array($this->_config['smtp'] ?? null) ? $this->_config['smtp'] : [];

		$username   = is_string($smtp['username'] ?? null) ? $smtp['username'] : '';
		$encryption = $smtp['encryption'] ?? '';

		$this->_mailer->isSMTP();
		$this->_mailer->Host    = is_string($smtp['host'] ?? null) ? $smtp['host'] : 'localhost';
		$this->_mailer->Port    = is_numeric($smtp['port'] ?? null) ? (int) $smtp['port'] : 587;
		$this->_mailer->Timeout = is_numeric($smtp['timeout'] ?? null) ? (int) $smtp['timeout'] : 15;

		if ($encryption === 'tls')
		{
			$this->_mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		}
		elseif ($encryption === 'ssl')
		{
			$this->_mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
		}
		elseif ($username !== '')
		{
			throw new Kohana_Exception('ub_email does not send a password over a connection that is not encrypted: set "encryption" to tls or ssl');
		}

		if ($username !== '')
		{
			$this->_mailer->SMTPAuth = true;
			$this->_mailer->Username = $username;
			$this->_mailer->Password = is_string($smtp['password'] ?? null) ? $smtp['password'] : '';
		}
	}

	/**
	 * Sets what the config says of every message: who it comes from,
	 * where answers go, who gets a hidden copy.
	 *
	 * @return  void
	 * @throws  Kohana_Exception  for what is no email address
	 */
	protected function _defaults(): void
	{
		$from = (array) ($this->_config['from'] ?? []);

		if (is_string($from[0] ?? null) AND $from[0] !== '')
		{
			$this->from($from[0], is_string($from[1] ?? null) ? $from[1] : '');
		}

		$reply = (array) ($this->_config['reply_to'] ?? []);

		if (is_string($reply[0] ?? null) AND $reply[0] !== '')
		{
			$this->reply_to($reply[0], is_string($reply[1] ?? null) ? $reply[1] : '');
		}

		/** @var array<int|string, string> $bcc */
		$bcc = array_filter((array) ($this->_config['bcc'] ?? []), 'is_string');

		$this->bcc($bcc);
	}

	/**
	 * Adds addresses with one of the methods of PHPMailer.
	 *
	 * @param   string                            $method  addAddress, addCC, addBCC or addReplyTo
	 * @param   string|array<int|string, string>  $email   an address, or a list: address => name, or addresses
	 * @param   string                            $name    the name, for one address
	 * @return  $this
	 * @throws  Kohana_Exception  for what is no email address
	 */
	protected function _add(string $method, string|array $email, string $name): static
	{
		$list = is_array($email) ? $email : [$email => $name];

		foreach ($list as $address => $who)
		{
			// A list of addresses without names has numbers as keys
			if (is_int($address))
			{
				list($address, $who) = [$who, ''];
			}

			try
			{
				$this->_mailer->$method($address, $who);
			}
			catch (Throwable $e)
			{
				throw new Kohana_Exception(':email is not an email address', [':email' => $address], 0, $e);
			}
		}

		return $this;
	}

	/**
	 * Returns the addresses the message goes to: to, cc and bcc.
	 *
	 * @return  array<int, string>
	 */
	protected function _recipients(): array
	{
		$all = $this->_mailer->getAllRecipientAddresses();

		return is_array($all) ? array_map('strval', array_keys($all)) : [];
	}

	/**
	 * With "redirect" in the config, sends the message to those
	 * addresses in place of the real recipients, who are named in the
	 * subject.
	 *
	 * @return  void
	 * @throws  Kohana_Exception  for what is no email address
	 */
	protected function _redirect(): void
	{
		/** @var array<int|string, string> $redirect */
		$redirect = array_filter((array) ($this->_config['redirect'] ?? []), 'is_string');

		if ($redirect === [])
			return;

		$real = implode(', ', $this->_recipients());

		$this->_mailer->clearAllRecipients();
		$this->_mailer->Subject = '[to: '.$real.'] '.$this->_subject;

		$this->to($redirect);
	}

}
