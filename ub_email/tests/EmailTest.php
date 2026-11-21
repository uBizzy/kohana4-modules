<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the messages that UB_Email builds, with the real PHPMailer.
 *
 * The tests run when PHPMailer can be loaded: by the application, or
 * from the directory named by the environment variable
 * KOHANA_TEST_PHPMAILER (the "src" directory of PHPMailer). Nothing is
 * sent: PHPMailer builds each message and the tests read it.
 *
 * @package    UB_Email
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.email')]
class UB_EmailTest extends Unittest_TestCase
{
	/**
	 * @var array<string, mixed>  The config of the module during a test
	 */
	protected array $environmentDefault = [
		'ub_email.default' => [
			'transport' => 'smtp',
			'from'      => ['site@example.com', 'Test Site'],
			'reply_to'  => null,
			'bcc'       => [],
			'smtp'      => ['host' => 'mail.example.com', 'port' => 587, 'username' => 'site', 'password' => 'the-smtp-password', 'encryption' => 'tls', 'timeout' => 15],
			'dkim'      => ['domain' => ''],
			'layout'    => 'ub_email/layout',
			'redirect'  => null,
		],
	];

	/**
	 * @var Log|null  The log of the application, put back after the test
	 */
	protected ?Log $log = null;

	public function setUp(): void
	{
		parent::setUp();

		$directory = getenv('KOHANA_TEST_PHPMAILER');

		if ( ! class_exists(PHPMailer::class) AND is_string($directory) AND is_file($directory.'/PHPMailer.php'))
		{
			require_once $directory.'/Exception.php';
			require_once $directory.'/PHPMailer.php';
			require_once $directory.'/SMTP.php';
		}

		if ( ! class_exists(PHPMailer::class))
		{
			$this->markTestSkipped('PHPMailer is not loaded: set KOHANA_TEST_PHPMAILER to its "src" directory.');
		}

		$this->log = Kohana::$log;

		Kohana::$log = new Log;
	}

	public function tearDown(): void
	{
		if ($this->log !== null)
		{
			Kohana::$log = $this->log;
		}

		parent::tearDown();
	}

	/**
	 * Changes settings of the group of the tests.
	 *
	 * @param array<string, mixed> $settings
	 */
	protected function config(array $settings): void
	{
		$this->setEnvironment(['ub_email.default' => $settings + $this->environmentDefault['ub_email.default']]);
	}

	/**
	 * Lets PHPMailer build the message and returns it as it would
	 * leave, headers and all.
	 */
	protected function built(UB_Email $email): string
	{
		$this->assertTrue($email->mailer()->preSend(), $email->mailer()->ErrorInfo);

		return $email->mailer()->getSentMIMEMessage();
	}

	/**
	 * The messages that were logged, with their types.
	 *
	 * @return array<int, array{0: string, 1: mixed}>
	 */
	protected function logged(): array
	{
		$messages = (new ReflectionProperty('Log', '_messages'))->getValue(Kohana::$log);

		return array_map(fn (array $message): array => [$message['body'], $message['additional']['type'] ?? null], $messages);
	}

	public function test_a_message_has_what_the_config_and_the_code_gave_it(): void
	{
		$email = UB_Email::factory()
			->to('ana@example.com', 'Ana')
			->cc('bob@example.com')
			->subject('Your invoice')
			->text('Hello Ana');

		$message = $this->built($email);

		$this->assertStringContainsString('From: Test Site <site@example.com>', $message);
		$this->assertStringContainsString('To: Ana <ana@example.com>', $message);
		$this->assertStringContainsString('Cc: bob@example.com', $message);
		$this->assertStringContainsString('Subject: Your invoice', $message);
		$this->assertStringContainsStringIgnoringCase('Content-Type: text/plain; charset=utf-8', $message);
		$this->assertStringContainsString('Hello Ana', $message);

		// Which program sent it is nobody's business
		$this->assertStringNotContainsString('X-Mailer', $message);
	}

	public function test_addresses_come_one_by_one_or_as_a_list(): void
	{
		$email = UB_Email::factory()
			->to(['ana@example.com' => 'Ana', 'bob@example.com'])
			->bcc(['hidden@example.com'])
			->reply_to('answers@example.com', 'Answers')
			->from('other@example.com', 'Other');

		$mailer = $email->mailer();

		$this->assertSame([['ana@example.com', 'Ana'], ['bob@example.com', '']], $mailer->getToAddresses());
		$this->assertSame([['hidden@example.com', '']], $mailer->getBccAddresses());
		$this->assertSame([['answers@example.com', 'Answers']], array_values($mailer->getReplyToAddresses()));
		$this->assertSame('other@example.com', $mailer->From);
		$this->assertSame('Other', $mailer->FromName);
	}

	public function test_the_config_adds_where_answers_go_and_hidden_copies(): void
	{
		$this->config(['reply_to' => ['answers@example.com', 'Answers'], 'bcc' => ['archive@example.com']]);

		$mailer = UB_Email::factory()->mailer();

		$this->assertSame([['answers@example.com', 'Answers']], array_values($mailer->getReplyToAddresses()));
		$this->assertSame([['archive@example.com', '']], $mailer->getBccAddresses());
	}

	public function test_what_is_no_address_is_refused_at_once(): void
	{
		foreach (['not an address', "ana@example.com\r\nBcc: all@example.com", ''] as $address)
		{
			try
			{
				UB_Email::factory()->to($address);

				$this->fail('accepted: '.$address);
			}
			catch (Kohana_Exception $e)
			{
				$this->assertStringContainsString('is not an email address', $e->getMessage());
			}
		}

		$this->expectException(Kohana_Exception::class);

		UB_Email::factory()->from('nobody');
	}

	public function test_a_subject_cannot_add_headers(): void
	{
		$email = UB_Email::factory()->to('ana@example.com')->subject("Hello\r\nBcc: all@example.com\n\n<script>")->text('x');

		$this->assertSame('Hello Bcc: all@example.com <script>', $email->mailer()->Subject);

		$message = $this->built($email);

		$this->assertStringNotContainsString("\nBcc: all@example.com", $message);
	}

	public function test_html_gets_the_layout_and_a_text_version(): void
	{
		$email = UB_Email::factory()
			->to('ana@example.com')
			->subject('News & more')
			->html('<h1>Hello</h1><p>Read <a href="https://example.com/news">the news</a>.</p>');

		$mailer = $email->mailer();

		$this->assertStringContainsString('<title>News &amp; more</title>', $mailer->Body);
		$this->assertStringContainsString('<h1>Hello</h1>', $mailer->Body);
		$this->assertStringStartsWith('<!DOCTYPE html>', $mailer->Body);

		$this->assertStringContainsString('Hello', $mailer->AltBody);
		$this->assertStringContainsString('the news', $mailer->AltBody);
		$this->assertStringNotContainsString('<', $mailer->AltBody);

		$message = $this->built($email);

		$this->assertStringContainsString('multipart/alternative', $message);
	}

	public function test_without_a_layout_the_html_is_sent_as_it_is(): void
	{
		$this->config(['layout' => null]);

		$email = UB_Email::factory()->html('<p>Only this</p>');

		$this->assertSame('<p>Only this</p>', $email->mailer()->Body);
	}

	public function test_a_view_is_the_html_of_a_message(): void
	{
		$this->config(['layout' => null]);

		$email = UB_Email::factory()->subject('Seen')->view('ub_email/layout', ['content' => '<p>From a view</p>', 'subject' => 'In the view']);

		$this->assertStringContainsString('<p>From a view</p>', $email->mailer()->Body);
		$this->assertStringContainsString('<title>In the view</title>', $email->mailer()->Body);
	}

	public function test_text_after_html_leaves_no_html_behind(): void
	{
		$email = UB_Email::factory()->html('<p>first</p>')->text('second');

		$this->assertSame('second', $email->mailer()->Body);
		$this->assertSame('', $email->mailer()->AltBody);
		$this->assertSame('text/plain', $email->mailer()->ContentType);
	}

	public function test_files_and_content_are_attached(): void
	{
		$email = UB_Email::factory()
			->to('ana@example.com')
			->subject('Files')
			->text('See the files')
			->attach(__FILE__, 'the-test.txt')
			->attach_content('%PDF-1.4 not really', 'invoice.pdf', 'application/pdf');

		$names = array_column($email->mailer()->getAttachments(), 2);

		$this->assertSame(['the-test.txt', 'invoice.pdf'], $names);

		$message = $this->built($email);

		$this->assertStringContainsString('filename=the-test.txt', $message);
		$this->assertStringContainsString('Content-Type: application/pdf; name=invoice.pdf', $message);
	}

	public function test_a_file_that_is_not_there_is_refused(): void
	{
		$this->expectException(Kohana_Exception::class);

		UB_Email::factory()->attach(__DIR__.'/no-such-file.pdf');
	}

	public function test_the_mail_server_of_the_config_is_used_encrypted(): void
	{
		$mailer = UB_Email::factory()->mailer();

		$this->assertSame('smtp', $mailer->Mailer);
		$this->assertSame('mail.example.com', $mailer->Host);
		$this->assertSame(587, $mailer->Port);
		$this->assertSame('tls', $mailer->SMTPSecure);
		$this->assertTrue($mailer->SMTPAuth);
		$this->assertSame('site', $mailer->Username);
		$this->assertSame('the-smtp-password', $mailer->Password);
		$this->assertSame(15, $mailer->Timeout);

		// Nothing switched the check of the certificate off
		$this->assertSame([], $mailer->SMTPOptions);
		$this->assertTrue($mailer->SMTPAutoTLS);

		$this->config(['smtp' => ['host' => 'mail.example.com', 'port' => 465, 'username' => 'site', 'password' => 'x', 'encryption' => 'ssl']]);

		$this->assertSame('ssl', UB_Email::factory()->mailer()->SMTPSecure);
	}

	public function test_a_password_is_not_sent_in_the_open(): void
	{
		$this->config(['smtp' => ['host' => 'mail.example.com', 'port' => 25, 'username' => 'site', 'password' => 'x', 'encryption' => '']]);

		try
		{
			UB_Email::factory();

			$this->fail('a password without encryption was accepted');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('not encrypted', $e->getMessage());
		}

		// A server on the same machine that asks for no password is fine
		$this->config(['smtp' => ['host' => 'localhost', 'port' => 25, 'encryption' => '']]);

		$mailer = UB_Email::factory()->mailer();

		$this->assertFalse($mailer->SMTPAuth);
		$this->assertSame('', $mailer->SMTPSecure);
	}

	public function test_the_other_transports_and_unknown_ones(): void
	{
		$this->config(['transport' => 'mail']);
		$this->assertSame('mail', UB_Email::factory()->mailer()->Mailer);

		$this->config(['transport' => 'sendmail']);
		$this->assertSame('sendmail', UB_Email::factory()->mailer()->Mailer);

		$this->config(['transport' => 'pigeon']);
		$this->expectException(Kohana_Exception::class);

		UB_Email::factory();
	}

	public function test_a_group_that_is_not_in_the_config_is_refused(): void
	{
		$this->expectException(Kohana_Exception::class);

		UB_Email::factory('no-such-group');
	}

	public function test_dkim_is_set_when_the_config_has_a_domain(): void
	{
		$this->assertSame('', UB_Email::factory()->mailer()->DKIM_domain);

		$this->config(['dkim' => ['domain' => 'example.com', 'selector' => 'mail', 'private' => '/etc/dkim/example.key', 'passphrase' => '']]);

		$mailer = UB_Email::factory()->mailer();

		$this->assertSame('example.com', $mailer->DKIM_domain);
		$this->assertSame('mail', $mailer->DKIM_selector);
		$this->assertSame('/etc/dkim/example.key', $mailer->DKIM_private);
	}

	public function test_a_message_that_cannot_be_sent_is_false_and_logged_without_the_password(): void
	{
		// A port on this machine where nothing listens
		$this->config(['smtp' => ['host' => '127.0.0.1', 'port' => 1, 'username' => 'site', 'password' => 'the-smtp-password', 'encryption' => 'tls', 'timeout' => 2]]);

		$sent = UB_Email::factory()->to('ana@example.com')->subject('Hello')->text('x')->send();

		$this->assertFalse($sent);

		$logged = $this->logged();

		$this->assertCount(1, $logged);
		$this->assertStringStartsWith('ub_email: "Hello" was not sent: ', $logged[0][0]);
		$this->assertSame('email.failed', $logged[0][1]);
		$this->assertStringNotContainsString('the-smtp-password', $logged[0][0]);
	}

	public function test_a_message_to_nobody_is_not_sent(): void
	{
		$this->assertFalse(UB_Email::factory()->subject('Hello')->text('x')->send());
		$this->assertSame('email.failed', $this->logged()[0][1]);
	}

	public function test_with_redirect_the_real_recipients_get_nothing(): void
	{
		$this->config(['redirect' => ['dev@example.com'], 'bcc' => ['archive@example.com'], 'smtp' => ['host' => '127.0.0.1', 'port' => 1, 'encryption' => '', 'timeout' => 2]]);

		$email = UB_Email::factory()->to('ana@example.com', 'Ana')->cc('bob@example.com')->subject('Hello')->text('x');

		// It fails to leave, after the recipients were changed
		$this->assertFalse($email->send());

		$mailer = $email->mailer();

		$this->assertSame([['dev@example.com', '']], $mailer->getToAddresses());
		$this->assertSame([], $mailer->getCcAddresses());
		$this->assertSame([], $mailer->getBccAddresses());
		$this->assertSame('[to: archive@example.com, ana@example.com, bob@example.com] Hello', $mailer->Subject);
	}
}
