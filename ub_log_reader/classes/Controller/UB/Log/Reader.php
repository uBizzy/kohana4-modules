<?php
/**
 * The page that searches the logs, at "ub-log-reader".
 *
 * [!!] Logs hold what must not be read by visitors. The page is there
 * in the DEVELOPMENT environment. Anywhere else the address is not
 * found, unless config/ub_log_reader.php both switches the page on for
 * production and lists the address of the visitor: see
 * [UB_Log_Reader::allowed].
 *
 * @package    UB_Log_Reader
 * @category   Controllers
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class Controller_UB_Log_Reader extends Controller {

	/**
	 * Refuses the page to whoever may not read the logs. The answer is
	 * "not found", the same as for an address that does not exist: it
	 * does not tell that there is a page.
	 *
	 * @throws  HTTP_Exception_404
	 */
	public function before(): void
	{
		parent::before();

		if ( ! UB_Log_Reader::allowed(Request::$client_ip))
		{
			throw HTTP_Exception::factory(404, 'The requested URL :uri was not found on this server.', [':uri' => $this->request->uri()])
				->request($this->request);
		}

		if (Kohana::$environment !== Kohana::DEVELOPMENT)
		{
			// Who read the logs of a live site is worth knowing
			Kohana::$log?->add(Log::NOTICE, 'ub_log_reader: the logs were read from :ip', [':ip' => Request::$client_ip]);
		}
	}

	/**
	 * Shows the form and the entries that fit it.
	 */
	public function action_index(): void
	{
		$filters = [];

		// What the form sent is text, or it is nothing
		foreach (['level', 'text', 'reference', 'request', 'from', 'to'] as $name)
		{
			$value = $this->request->query($name);

			$filters[$name] = is_string($value) ? trim($value) : '';
		}

		$limit = $this->request->query('limit');
		$limit = (is_string($limit) AND ctype_digit($limit)) ? (int) $limit : 100;

		$reader = new UB_Log_Reader;

		// The entries themselves, or the messages that come most often
		$top = ($this->request->query('show') === 'top');

		// The page: its view sees one variable, $page
		View::set_global('page', [
			'title'   => 'Log Reader',
			'filters' => $filters,
			'limit'   => $limit,
			'entries' => $top ? [] : $reader->search($filters, $limit),
			'top'     => $top ? $reader->top($filters, min($limit, 100)) : null,
			'indexed' => ($reader->index() !== null),
			'days'    => $reader->days(),
			'levels'  => array_keys(UB_Log_Reader::LEVELS),
		]);

		// What the logs say is not for a cache to keep
		$this->response
			->headers('cache-control', 'no-store')
			->body(View::factory('ub_log_reader/page')->render());
	}

}
