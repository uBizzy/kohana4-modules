<?php
/**
 * Log writer that keeps the messages of the request in memory, for the
 * "Logs" panel of the debug bar. Nothing is written anywhere.
 *
 * @package    UB_Debug
 * @category   Logging
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
class UB_Debug_Log extends Log_Writer {

	/**
	 * @var  array<int, array<string, mixed>>  Messages of this request: level name, text, file and line
	 */
	public static array $messages = [];

	/**
	 * Keeps a batch of messages.
	 *
	 * @param   array<int, array<string, mixed>>  $messages  messages as [Log] hands them to its writers
	 * @return  void
	 */
	public function write(array $messages): void
	{
		foreach ($messages as $message)
		{
			/** @var array{level: int|string, body: string, file?: string|null, line?: int|null} $message */
			UB_Debug_Log::$messages[] = [
				'level' => $this->_log_levels[$message['level']] ?? (string) $message['level'],
				'body'  => (string) $message['body'],
				'file'  => $message['file'] ?? null,
				'line'  => $message['line'] ?? null,
			];
		}
	}

}
