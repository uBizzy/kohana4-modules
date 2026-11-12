<?php

/**
 * A file of functions: included by "files".
 */
function ubtest_autoload_helper(): string
{
	// Counts how often the file was included
	$GLOBALS['ubtest_autoload_included'] = ($GLOBALS['ubtest_autoload_included'] ?? 0) + 1;

	return 'helped';
}

$GLOBALS['ubtest_autoload_included'] = ($GLOBALS['ubtest_autoload_included'] ?? 0) + 1;
