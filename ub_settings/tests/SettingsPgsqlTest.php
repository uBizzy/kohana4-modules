<?php

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__.'/SettingsTest.php';

/**
 * Runs the tests of the settings on PostgreSQL, when the environment
 * variable KOHANA_TEST_PGSQL_HOST is set.
 *
 * @package    UB_Settings
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4-modules/blob/main/LICENSE.md
 */
#[Group('ub')]
#[Group('ub.settings')]
class UB_SettingsPgsqlTest extends UB_SettingsTest
{
	protected function config(): ?array
	{
		if ( ! getenv('KOHANA_TEST_PGSQL_HOST') OR ! extension_loaded('pdo_pgsql'))
			return null;

		return [
			'type'       => 'PDO',
			'connection' => [
				'dsn' => 'pgsql:host='.getenv('KOHANA_TEST_PGSQL_HOST')
					.';port='.(getenv('KOHANA_TEST_PGSQL_PORT') ?: 5432)
					.';dbname='.(getenv('KOHANA_TEST_PGSQL_DATABASE') ?: 'kohana_test'),
				'username' => getenv('KOHANA_TEST_PGSQL_USER') ?: 'postgres',
				'password' => (string) getenv('KOHANA_TEST_PGSQL_PASSWORD'),
			],
			'identifier'   => '"',
			'table_prefix' => '',
		];
	}
}
