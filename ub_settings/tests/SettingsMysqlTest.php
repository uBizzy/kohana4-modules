<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/SettingsTest.php';

/**
 * Runs the tests of the settings on MySQL or MariaDB, when the
 * environment variable KOHANA_TEST_MYSQL_HOST is set.
 *
 * @package    UB_Settings
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.settings')]
class UB_SettingsMysqlTest extends UB_SettingsTest
{
	protected function config(): ?array
	{
		if ( ! getenv('KOHANA_TEST_MYSQL_HOST'))
			return null;

		return [
			'type'       => 'MySQLi',
			'connection' => [
				'hostname' => getenv('KOHANA_TEST_MYSQL_HOST'),
				'port'     => getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306,
				'username' => getenv('KOHANA_TEST_MYSQL_USER') ?: 'root',
				'password' => (string) getenv('KOHANA_TEST_MYSQL_PASSWORD'),
				'database' => getenv('KOHANA_TEST_MYSQL_DATABASE') ?: 'kohana_test',
			],
			'charset'      => 'utf8mb4',
			'table_prefix' => '',
		];
	}
}
