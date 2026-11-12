<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/LogReaderTest.php';

/**
 * Tests the index of the logs. Every test of the reader runs here a
 * second time, with the index switched on: a search must give the same
 * with it as without it. The tests of this class are about the index
 * itself: how it follows the files as they grow, change and go.
 *
 * @package    UB_Log_Reader
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.log_reader')]
class UB_LogIndexTest extends UB_LogReaderTest
{
	public function setUp(): void
	{
		parent::setUp();

		if ( ! UB_Log_Index::available())
		{
			$this->markTestSkipped('The index needs the pdo_sqlite extension.');
		}

		$this->setEnvironment(['ub_log_reader.index' => true]);
	}

	/**
	 * Adds text to the log file of a day.
	 */
	protected function append(string $day, string $text, bool $json = false): void
	{
		file_put_contents($this->directory.'/'.str_replace('-', '/', $day).($json ? '.json' : '').'.php', $text, FILE_APPEND);
	}

	/**
	 * A reader that reads the files, to compare with.
	 */
	protected function without_index(): UB_Log_Reader
	{
		$this->setEnvironment(['ub_log_reader.index' => false]);

		$reader = new UB_Log_Reader;

		$this->setEnvironment(['ub_log_reader.index' => true]);

		$this->assertNull($reader->index());

		return $reader;
	}

	public function test_the_index_is_used_and_kept_private(): void
	{
		$this->logs();

		$reader = new UB_Log_Reader;
		$index  = $reader->index();

		$this->assertInstanceOf(UB_Log_Index::class, $index);
		$this->assertSame(0, $index->count(), 'Nothing is read before the first search');

		$reader->search();

		$this->assertSame(9, $index->count());
		$this->assertSame(realpath($this->directory).DIRECTORY_SEPARATOR.'ub_log_reader.sqlite', $index->path());
		$this->assertFileExists($index->path());

		if (DIRECTORY_SEPARATOR === '/')
		{
			$this->assertSame('0600', substr(sprintf('%o', fileperms($index->path())), -4));
		}

		// The index file is no day of logs
		$this->assertSame(['2026-11-12', '2026-11-11', '2026-11-10'], $reader->days());
		$this->assertStringContainsString('The index is used.', $this->page()->body());
	}

	/**
	 * Searches of every kind.
	 *
	 * @return array<string, array{array<string, mixed>, int}>
	 */
	public static function provider_searches(): array
	{
		return [
			'everything'            => [[], 100],
			'a limit'               => [[], 4],
			'one'                   => [[], 1],
			'a level'               => [['level' => 'warning'], 100],
			'text'                  => [['text' => 'payment'], 100],
			'text in other letters' => [['text' => 'PAYMENT'], 100],
			'text in a trace'       => [['text' => 'Shop.php(31)'], 100],
			'text in an address'    => [['text' => 'orders/save'], 100],
			'text with a percent'   => [['text' => '100%'], 100],
			'text with a line'      => [['text' => 'a_b'], 100],
			'text that is not ASCII' => [['text' => 'ÁGUA'], 100],
			'a reference'           => [['reference' => 'a1b2c3d4'], 100],
			'a request'             => [['request' => 'BBBB2222'], 100],
			'from'                  => [['from' => '2026-11-11 10:05:00'], 100],
			'to'                    => [['to' => '2026-11-11'], 100],
			'between'               => [['from' => '2026-11-11', 'to' => '2026-11-11 11:00:00', 'level' => 'notice'], 100],
			'all at once'           => [['level' => 'error', 'text' => 'gateway', 'reference' => 'A1B2C3D4', 'from' => '2026-11-01'], 100],
			'nothing fits'          => [['text' => 'no such text'], 100],
			'a level that is none'  => [['level' => 'loud'], 100],
		];
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	#[DataProvider('provider_searches')]
	public function test_the_index_gives_what_the_files_give(array $filters, int $limit): void
	{
		$this->logs();

		$this->day('2026-11-09', [
			'2026-11-09 08:00:00 --- INFO: Discount of 100% given in /app/classes/Shop.php:5',
			'2026-11-09 08:00:01 --- INFO: Discount of 1000 given in /app/classes/Shop.php:5',
			'2026-11-09 08:00:02 --- INFO: Field a_b is empty in /app/classes/Form.php:9',
			'2026-11-09 08:00:03 --- INFO: Field axb is empty in /app/classes/Form.php:9',
			'2026-11-09 08:00:04 --- WARNING: Falta água no depósito in /app/classes/Monitor.php:12',
		]);

		$expected = $this->without_index()->search($filters, $limit);
		$reader   = new UB_Log_Reader;

		$this->assertSame($expected, $reader->search($filters, $limit));

		// And again, from an index that is already there
		$this->assertSame($expected, (new UB_Log_Reader)->search($filters, $limit));
		$this->assertSame($this->without_index()->top($filters), $reader->top($filters));

		if (isset($filters['text']) AND $filters['text'] !== 'no such text')
		{
			$this->assertNotSame([], $expected, 'The search of this test finds something');
		}
	}

	public function test_what_is_added_to_a_log_is_found(): void
	{
		$this->day('2026-11-12', ['2026-11-12 08:00:00 --- INFO: First in /app/a.php:1']);

		$this->assertSame(['First'], $this->messages());

		$this->append('2026-11-12', PHP_EOL.'2026-11-12 08:00:01 --- INFO: Second in /app/a.php:2'.PHP_EOL);

		$this->assertSame(['Second', 'First'], $this->messages());

		// The last entry gets more lines after it was read: an error, then its trace
		$this->append('2026-11-12', PHP_EOL.'2026-11-12 08:00:02 --- ERROR: [AAAA0001] Kohana_Exception [ 0 ]: Broken ~ APPPATH/b.php [ 3 ] in /app/b.php:3'.PHP_EOL);

		$this->assertNull((new UB_Log_Reader)->search()[0]['trace']);

		$this->append('2026-11-12', '2026-11-12 08:00:02 --- DEBUG: #0 /app/c.php(9): broken()'.PHP_EOL.'#1 {main} in /app/b.php:3'.PHP_EOL);

		$entries = (new UB_Log_Reader)->search();

		$this->assertCount(3, $entries);
		$this->assertSame("#0 /app/c.php(9): broken()\n#1 {main}", $entries[0]['trace']);
		$this->assertSame($this->without_index()->search(), $entries);
		$this->assertSame(3, (new UB_Log_Reader)->index()?->count());
	}

	public function test_a_json_log_that_grows(): void
	{
		$this->day('2026-11-12', [['time' => '2026-11-12T09:00:00+00:00', 'level' => 'INFO', 'message' => 'First']]);

		$this->assertSame(['First'], $this->messages());

		$this->append('2026-11-12', json_encode(['time' => '2026-11-12T09:00:01+00:00', 'level' => 'INFO', 'message' => 'Second']).PHP_EOL, true);
		$this->append('2026-11-12', json_encode(['time' => '2026-11-12T09:00:02+00:00', 'level' => 'INFO', 'message' => 'Third']).PHP_EOL, true);

		$this->assertSame(['Third', 'Second', 'First'], $this->messages());
		$this->assertSame(3, (new UB_Log_Reader)->index()?->count());
	}

	public function test_files_that_change_or_go_are_followed(): void
	{
		$this->logs();

		$this->assertCount(9, $this->messages());

		// A day is deleted
		unlink($this->directory.'/2026/11/10.php');

		$this->assertCount(7, $this->messages());

		// A file is written again, shorter: another file under the same name
		$this->day('2026-11-11', ['2026-11-11 10:00:00 --- INFO: Only one in /app/a.php:1']);

		$this->assertSame(['Order not saved', '[FFEE0011] Database_Exception [ 0 ]: The database is gone', 'Page shown', 'Only one'], $this->messages());

		// A day gets a JSON file: that one is read, the text file no more
		$this->day('2026-11-11', [['time' => '2026-11-11T10:00:00+00:00', 'level' => 'INFO', 'message' => 'From JSON']]);

		$this->assertSame(['Order not saved', '[FFEE0011] Database_Exception [ 0 ]: The database is gone', 'Page shown', 'From JSON'], $this->messages());
		$this->assertSame(4, (new UB_Log_Reader)->index()?->count());
		$this->assertSame($this->without_index()->search(), (new UB_Log_Reader)->search());

		// All logs are gone
		unlink($this->directory.'/2026/11/11.php');
		unlink($this->directory.'/2026/11/11.json.php');
		unlink($this->directory.'/2026/11/12.json.php');

		$this->assertSame([], $this->messages());
		$this->assertSame(0, (new UB_Log_Reader)->index()?->count());
	}

	public function test_a_deleted_or_broken_index_is_made_again(): void
	{
		$this->logs();

		$this->assertCount(9, $this->messages());

		unlink($this->directory.'/ub_log_reader.sqlite');

		$this->assertCount(9, $this->messages());

		// An index of another version of the module
		$db = new PDO('sqlite:'.$this->directory.'/ub_log_reader.sqlite');
		$db->exec('PRAGMA user_version = 999');
		$db->exec('DELETE FROM entries WHERE severity > 3');
		$db = null;

		$this->assertCount(9, $this->messages());

		// A file that is no database at all is an error that says so
		file_put_contents($this->directory.'/ub_log_reader.sqlite', str_repeat('this is no database ', 100));

		try
		{
			new UB_Log_Reader;

			$this->fail('A broken index was accepted');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('The index of the logs cannot be opened', $e->getMessage());
		}
	}

	public function test_index_task(): void
	{
		$this->logs();

		$this->assertStringContainsString('9 entries in ', $this->task(['task' => 'log:index']));

		// Entries taken out behind its back come again with --rebuild
		$db = new PDO('sqlite:'.$this->directory.'/ub_log_reader.sqlite');
		$db->exec('DELETE FROM entries WHERE severity > 3');
		$db = null;

		$this->assertStringContainsString('2 entries in ', $this->task(['task' => 'log:index']));
		$this->assertStringContainsString('9 entries in ', $this->task(['task' => 'log:index', 'rebuild' => null]));
		$this->assertStringContainsString('ub_log_reader.sqlite', $this->task(['task' => 'log:index']));
	}

	public function test_index_task_says_when_no_index_is_used(): void
	{
		$this->setEnvironment(['ub_log_reader.index' => false]);

		$this->expectException(Kohana_Exception::class);
		$this->expectExceptionMessage('No index is used');

		$this->task(['task' => 'log:index']);
	}

	public function test_what_is_typed_is_never_part_of_the_sql(): void
	{
		$this->logs();

		foreach (["' OR 1=1 --", '%', '_', '\\', "x'; DROP TABLE entries; --", '%%%'] as $text)
		{
			$filters = ['text' => $text, 'reference' => $text, 'request' => $text, 'level' => $text, 'from' => $text, 'to' => $text];

			$this->assertSame([], (new UB_Log_Reader)->search($filters));
			$this->assertSame($this->without_index()->search(['text' => $text]), (new UB_Log_Reader)->search(['text' => $text]));
		}

		$this->assertSame(9, (new UB_Log_Reader)->index()?->count());
	}
}
