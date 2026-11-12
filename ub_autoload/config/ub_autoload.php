<?php
/**
 * Where the classes are that Kohana does not find by itself: libraries
 * of others, and helpers and classes of your own that live outside
 * "classes".
 *
 * Copy this file to application/config/ and fill it in. A module can
 * bring a config/ub_autoload.php for the libraries it carries: all of
 * them are used together.
 *
 * A path that is not absolute is looked for in the "vendor" directory of
 * the application (application/vendor/) and of every module.
 */
return [
	/**
	 * A namespace and its directory (PSR-4). What most libraries of today
	 * use: look for "psr-4" in the composer.json of the library.
	 */
	'psr4' => [
		// 'PHPMailer\\PHPMailer\\' => 'phpmailer/src',
	],

	/**
	 * A prefix and its directory (PSR-0), for older libraries whose
	 * classes are named Some_Class: "psr-0" in their composer.json.
	 */
	'psr0' => [
		// 'HTMLPurifier' => 'htmlpurifier/library',
	],

	/**
	 * A class and its file, for libraries that follow no rule.
	 */
	'classmap' => [
		// 'TCPDF' => 'tcpdf/tcpdf.php',
	],

	/**
	 * Files that are included in every request: files of functions, and
	 * the files a library asks to be included ("files" in its
	 * composer.json). Keep the list short: each one costs time in every
	 * request, used or not.
	 */
	'files' => [
		// 'htmlpurifier/library/HTMLPurifier.composer.php',
		// 'helpers/money.php',
	],
];
