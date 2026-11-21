<?php
/**
 * Messages for the visitor that are kept in the session until a page
 * shows them: "Saved", "Could not send", after a redirect too.
 *
 *     // In a controller
 *     UB_Message::success('The invoice was saved.');
 *     $this->redirect('invoices');
 *
 *     // In the layout
 *     echo UB_Message::render();
 *
 * A message floats over the page, or is "inline": printed where the
 * view asks for it, for example above a form.
 *
 *     UB_Message::danger('Check the fields below.', ['inline' => true]);
 *
 *     echo UB_Message::render(true);
 *
 * The text is printed as text. A message that must hold HTML says so,
 * and then nothing a visitor typed may be in it:
 *
 *     UB_Message::info('See <a href="/help">the help</a>.', ['html' => true]);
 *
 * @package    UB_Message
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Message {

	/**
	 * @var  string  Where the session keeps the messages
	 */
	public const string SESSION = 'ub_message';

	/**
	 * @var  array<int, string>  The kinds of message
	 */
	public const array TYPES = ['success', 'info', 'warning', 'danger'];

	/**
	 * @var  array<string, array<string, string>>  The class names of each style: of the list (floating and inline), of a message (":type" is its kind) and of its button
	 */
	public const array CLASSES = [
		'theme' => ['float' => 'ub-message-float', 'inline' => 'ub-message-inline', 'message' => 'ub-message ub-message-:type', 'close' => 'ub-message-close'],
		'bulma' => ['float' => 'ub-message-float', 'inline' => 'ub-message-inline', 'message' => 'notification is-:type', 'close' => 'delete'],
	];

	/**
	 * @var  bool  Were the style and the script printed in this request?
	 */
	protected static bool $_assets = false;

	/**
	 * Adds a message that something worked.
	 *
	 * @param   string                $text     the message
	 * @param   array<string, mixed>  $options  see [UB_Message::add]
	 * @return  void
	 */
	public static function success(string $text, array $options = []): void
	{
		static::add('success', $text, $options);
	}

	/**
	 * Adds a message that tells something.
	 *
	 * @param   string                $text     the message
	 * @param   array<string, mixed>  $options  see [UB_Message::add]
	 * @return  void
	 */
	public static function info(string $text, array $options = []): void
	{
		static::add('info', $text, $options);
	}

	/**
	 * Adds a message that asks for care.
	 *
	 * @param   string                $text     the message
	 * @param   array<string, mixed>  $options  see [UB_Message::add]
	 * @return  void
	 */
	public static function warning(string $text, array $options = []): void
	{
		static::add('warning', $text, $options);
	}

	/**
	 * Adds a message that something failed.
	 *
	 * @param   string                $text     the message
	 * @param   array<string, mixed>  $options  see [UB_Message::add]
	 * @return  void
	 */
	public static function danger(string $text, array $options = []): void
	{
		static::add('danger', $text, $options);
	}

	/**
	 * Adds a message. The options, all of them optional:
	 *
	 * Option | What it is
	 * --- | ---
	 * title | a short title above the text
	 * inline | true prints it where the view asks for inline messages; false (the default) lets it float
	 * html | true prints the text as HTML: only for text that holds nothing a visitor typed
	 * duration | milliseconds until a floating message goes away; 0 never (default: the config)
	 * dismissible | can it be closed? (default: the config)
	 *
	 * @param   string                $type     one of [UB_Message::TYPES]
	 * @param   string                $text     the message
	 * @param   array<string, mixed>  $options  see above
	 * @return  void
	 * @throws  Kohana_Exception  for a kind of message that does not exist
	 */
	public static function add(string $type, string $text, array $options = []): void
	{
		if ( ! in_array($type, static::TYPES, true))
			throw new Kohana_Exception('A message is one of: :types', [':types' => implode(', ', static::TYPES)]);

		$duration    = $options['duration'] ?? Kohana::$config->load('ub_message.duration');
		$dismissible = $options['dismissible'] ?? Kohana::$config->load('ub_message.dismissible') ?? true;

		$messages   = static::_stored();
		$messages[] = [
			'type'        => $type,
			'text'        => $text,
			'title'       => is_string($options['title'] ?? null) ? $options['title'] : '',
			'inline'      => (bool) ($options['inline'] ?? false),
			'html'        => (bool) ($options['html'] ?? false),
			'duration'    => is_numeric($duration) ? max(0, (int) $duration) : 0,
			'dismissible' => (bool) $dismissible,
		];

		Session::instance()->set(static::SESSION, $messages);
	}

	/**
	 * Tells whether there are messages to show.
	 *
	 * @param   bool|null  $inline  true: inline ones; false: floating ones; null: any
	 * @return  bool
	 */
	public static function has(?bool $inline = null): bool
	{
		return static::get($inline, false) !== [];
	}

	/**
	 * Returns the messages, and forgets them: a message is shown once.
	 *
	 * @param   bool|null  $inline  true: inline ones; false: floating ones; null: all
	 * @param   bool       $clear   false keeps them for a later call
	 * @return  array<int, array{type: string, text: string, title: string, inline: bool, html: bool, duration: int, dismissible: bool}>
	 */
	public static function get(?bool $inline = null, bool $clear = true): array
	{
		$wanted = $kept = [];

		foreach (static::_stored() as $message)
		{
			if ($inline === null OR $message['inline'] === $inline)
			{
				$wanted[] = $message;
			}
			else
			{
				$kept[] = $message;
			}
		}

		if ($clear AND $wanted !== [])
		{
			Session::instance()->set(static::SESSION, $kept);
		}

		return $wanted;
	}

	/**
	 * Forgets all messages.
	 *
	 * @return  void
	 */
	public static function clear(): void
	{
		Session::instance()->delete(static::SESSION);
	}

	/**
	 * Returns the HTML of the messages, and forgets them. The first
	 * call in a request also returns the small style and script they
	 * need, with the nonce of the Content-Security-Policy.
	 *
	 *     echo UB_Message::render();       // the floating ones: once, in the layout
	 *     echo UB_Message::render(true);   // the inline ones: where they belong
	 *
	 * @param   bool  $inline  true: the inline messages; false: the floating ones
	 * @return  string  HTML; an empty text when there is nothing to show
	 */
	public static function render(bool $inline = false): string
	{
		$messages = static::get($inline);

		if ($messages === [])
			return '';

		$style = Kohana::$config->load('ub_message.style');
		$view  = Kohana::$config->load('ub_message.view');

		$html = View::factory(is_string($view) ? $view : 'ub_message/list', [
			'messages' => $messages,
			'inline'   => $inline,
			'classes'  => static::CLASSES[is_string($style) ? $style : 'theme'] ?? static::CLASSES['theme'],
		])->render();

		return static::assets().$html;
	}

	/**
	 * Returns the style and the script of the messages, once in a
	 * request; after that an empty text. [UB_Message::render] calls it:
	 * call it yourself only for a page that gets its messages later,
	 * as JSON.
	 *
	 * @return  string  HTML
	 */
	public static function assets(): string
	{
		if (static::$_assets)
			return '';

		static::$_assets = true;

		$nonce = HTML::chars(Security::nonce());

		return '<style nonce="'.$nonce.'">'.file_get_contents((string) Kohana::find_file('views', 'ub_message/message', 'css')).'</style>'
			.'<script nonce="'.$nonce.'">'.file_get_contents((string) Kohana::find_file('views', 'ub_message/message', 'js')).'</script>';
	}

	/**
	 * Returns all messages as JSON, for a request made by a script,
	 * and forgets them. A text that is not HTML is the plain text: the
	 * script must add it to the page as text (textContent), not as HTML.
	 *
	 * @return  string  JSON: a list of messages with type, text, title, inline, html, duration and dismissible
	 */
	public static function json(): string
	{
		return Json::encode(static::get());
	}

	/**
	 * Makes [UB_Message::assets] return the style and the script again.
	 * For tests, and for code that renders more than one page in a
	 * request.
	 *
	 * @return  void
	 */
	public static function reset(): void
	{
		static::$_assets = false;
	}

	/**
	 * Returns the messages of the session, and only what has their
	 * form.
	 *
	 * @return  array<int, array{type: string, text: string, title: string, inline: bool, html: bool, duration: int, dismissible: bool}>
	 */
	protected static function _stored(): array
	{
		$stored   = Session::instance()->get(static::SESSION);
		$messages = [];

		foreach (is_array($stored) ? $stored : [] as $message)
		{
			if ( ! is_array($message) OR ! in_array($message['type'] ?? null, static::TYPES, true) OR ! is_string($message['text'] ?? null))
				continue;

			$messages[] = [
				'type'        => $message['type'],
				'text'        => $message['text'],
				'title'       => is_string($message['title'] ?? null) ? $message['title'] : '',
				'inline'      => ($message['inline'] ?? false) === true,
				'html'        => ($message['html'] ?? false) === true,
				'duration'    => is_int($message['duration'] ?? null) ? $message['duration'] : 0,
				'dismissible' => ($message['dismissible'] ?? true) === true,
			];
		}

		return $messages;
	}

}
