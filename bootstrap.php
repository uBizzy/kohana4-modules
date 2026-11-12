<?php
/**
 * Starts the tests of the extra modules: the framework as for its own
 * tests, with every module of this directory enabled as well.
 *
 *     vendor/bin/phpunit -c extra/phpunit.xml      (from the project root)
 */
$unittest_modules = [];

foreach (glob(__DIR__.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $directory)
{
	$unittest_modules[basename($directory)] = $directory;
}

require __DIR__.'/../modules/unittest/bootstrap.php';

// The guide tests of the framework run here too, for the guides of the
// extra modules only (see phpunit.xml)
require_once MODPATH.'userguide/tests/GuideTest.php';

Kohana_GuideTest::$guides = array_keys($unittest_modules);

unset($unittest_modules, $directory);
