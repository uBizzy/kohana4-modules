<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the messages: what is kept, what is shown and how.
 *
 * @package    UB_Message
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.message')]
class UB_MessageTest extends Unittest_TestCase
{
	/**
	 * @var array<string, mixed>  The config of the module during a test
	 */
	protected array $environmentDefault = [
		'ub_message.style'       => 'theme',
		'ub_message.duration'    => 0,
		'ub_message.dismissible' => true,
		'ub_message.view'        => 'ub_message/list',
	];

	public function setUp(): void
	{
		parent::setUp();

		UB_Message::clear();
		UB_Message::reset();
	}

	public function tearDown(): void
	{
		UB_Message::clear();
		UB_Message::reset();

		parent::tearDown();
	}

	public function test_there_are_no_messages_at_first(): void
	{
		$this->assertFalse(UB_Message::has());
		$this->assertSame([], UB_Message::get());
		$this->assertSame('', UB_Message::render());
		$this->assertSame('', UB_Message::render(true));
		$this->assertSame('[]', UB_Message::json());
	}

	public function test_a_message_is_kept_until_it_is_asked_for_once(): void
	{
		UB_Message::success('Saved.');
		UB_Message::danger('Failed.', ['title' => 'Sorry']);

		$this->assertTrue(UB_Message::has());
		$this->assertCount(2, Session::instance()->get(UB_Message::SESSION));

		$messages = UB_Message::get();

		$this->assertSame(['success', 'danger'], array_column($messages, 'type'));
		$this->assertSame(['Saved.', 'Failed.'], array_column($messages, 'text'));
		$this->assertSame('Sorry', $messages[1]['title']);
		$this->assertSame(
			['type' => 'success', 'text' => 'Saved.', 'title' => '', 'inline' => false, 'html' => false, 'duration' => 0, 'dismissible' => true],
			$messages[0]
		);

		$this->assertFalse(UB_Message::has());
		$this->assertSame([], UB_Message::get());
	}

	public function test_every_kind_has_its_method_and_others_are_refused(): void
	{
		UB_Message::success('a');
		UB_Message::info('b');
		UB_Message::warning('c');
		UB_Message::danger('d');

		$this->assertSame(UB_Message::TYPES, array_column(UB_Message::get(), 'type'));

		$this->expectException(Kohana_Exception::class);

		UB_Message::add('"><script>', 'x');
	}

	public function test_floating_and_inline_messages_are_asked_for_apart(): void
	{
		UB_Message::success('floats');
		UB_Message::danger('in the form', ['inline' => true]);

		$this->assertTrue(UB_Message::has(true));
		$this->assertTrue(UB_Message::has(false));

		$this->assertSame(['in the form'], array_column(UB_Message::get(true), 'text'));

		$this->assertFalse(UB_Message::has(true));
		$this->assertTrue(UB_Message::has(false));

		$this->assertSame(['floats'], array_column(UB_Message::get(false), 'text'));
		$this->assertFalse(UB_Message::has());
	}

	public function test_looking_without_clearing_keeps_the_messages(): void
	{
		UB_Message::info('still here');

		$this->assertCount(1, UB_Message::get(null, false));
		$this->assertCount(1, UB_Message::get(null, false));

		UB_Message::clear();

		$this->assertFalse(UB_Message::has());
	}

	public function test_the_text_and_the_title_are_printed_as_text(): void
	{
		UB_Message::success('Saved <b>Ana</b> <script>alert(1)</script>'."\n".'second line', ['title' => '<i>Done</i>']);

		$html = UB_Message::render();

		$this->assertStringNotContainsString('<script>alert(1)', $html);
		$this->assertStringNotContainsString('<b>Ana</b>', $html);
		$this->assertStringContainsString('Saved &lt;b&gt;Ana&lt;/b&gt; &lt;script&gt;alert(1)&lt;/script&gt;<br>', $html);
		$this->assertStringContainsString('<strong>&lt;i&gt;Done&lt;/i&gt;</strong>', $html);
	}

	public function test_a_message_that_says_so_holds_html(): void
	{
		UB_Message::info('See <a href="/help">the help</a>.', ['html' => true]);

		$this->assertStringContainsString('<span>See <a href="/help">the help</a>.</span>', UB_Message::render());
	}

	public function test_the_first_render_brings_the_style_and_the_script_with_the_nonce(): void
	{
		UB_Message::success('one');

		$first = UB_Message::render();
		$nonce = Security::nonce();

		$this->assertStringContainsString('<style nonce="'.$nonce.'">', $first);
		$this->assertStringContainsString('<script nonce="'.$nonce.'">', $first);
		$this->assertStringContainsString('class="ub-message-float"', $first);
		$this->assertStringContainsString('class="ub-message ub-message-success"', $first);

		// No inline handlers and no style attributes, in the markup or made by the script
		$this->assertDoesNotMatchRegularExpression('/\sstyle=|\son[a-z]+=|innerHTML|eval\(/i', $first);

		UB_Message::danger('two', ['inline' => true]);

		$second = UB_Message::render(true);

		$this->assertStringNotContainsString('<style', $second);
		$this->assertStringNotContainsString('<script', $second);
		$this->assertStringContainsString('class="ub-message-inline"', $second);
		$this->assertStringContainsString('class="ub-message ub-message-danger"', $second);
	}

	public function test_a_message_can_be_closed_and_can_go_away_by_itself(): void
	{
		UB_Message::success('closes', ['duration' => 3000]);
		UB_Message::success('stays', ['dismissible' => false]);

		$html = UB_Message::render();

		$this->assertSame(1, substr_count($html, 'data-ub-message-duration="3000"'));
		$this->assertSame(1, substr_count($html, '<button type="button" class="ub-message-close" data-ub-message-close'));

		// An inline message stays where it is
		UB_Message::success('inline', ['inline' => true, 'duration' => 3000]);

		$this->assertStringNotContainsString('data-ub-message-duration=', UB_Message::render(true));
	}

	public function test_the_config_sets_what_a_message_does_not_say(): void
	{
		$this->setEnvironment(['ub_message.duration' => 5000, 'ub_message.dismissible' => false]);

		UB_Message::success('by the config');
		UB_Message::success('by itself', ['duration' => 0, 'dismissible' => true]);

		$messages = UB_Message::get();

		$this->assertSame([5000, 0], array_column($messages, 'duration'));
		$this->assertSame([false, true], array_column($messages, 'dismissible'));
	}

	public function test_the_bulma_style_changes_the_class_names_and_loads_nothing(): void
	{
		$this->setEnvironment(['ub_message.style' => 'bulma']);

		UB_Message::warning('careful');

		$html = UB_Message::render();

		$this->assertStringContainsString('class="notification is-warning"', $html);
		$this->assertStringContainsString('class="delete" data-ub-message-close', $html);
		$this->assertDoesNotMatchRegularExpression('#https?://|<link#i', $html);
	}

	public function test_json_gives_all_messages_and_forgets_them(): void
	{
		UB_Message::success('one <b>');
		UB_Message::danger('two', ['inline' => true]);

		$messages = json_decode(UB_Message::json(), true);

		$this->assertSame(['one <b>', 'two'], array_column($messages, 'text'));
		$this->assertSame([false, true], array_column($messages, 'inline'));
		$this->assertFalse(UB_Message::has());
	}

	public function test_what_the_session_holds_that_is_no_message_is_ignored(): void
	{
		Session::instance()->set(UB_Message::SESSION, [
			'not a message',
			['type' => 'purple', 'text' => 'x'],
			['type' => 'info', 'text' => ['an array']],
			['type' => 'info', 'text' => 'fine', 'html' => 'yes', 'duration' => '<script>'],
		]);

		$messages = UB_Message::get();

		$this->assertCount(1, $messages);
		$this->assertSame('fine', $messages[0]['text']);
		$this->assertFalse($messages[0]['html']);
		$this->assertSame(0, $messages[0]['duration']);
	}
}
