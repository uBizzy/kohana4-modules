<?php
/**
 * The places files are kept in. Copy this file to application/config/
 * and set up the ones you use; UB_Storage::instance('name') gives one.
 *
 * Keys and passwords do not belong in version control: keep the file
 * with the real ones out of it.
 */
return [
	// The place UB_Storage::instance() gives without a name
	'default' => 'local',

	'disks' => [

		// A directory of the server. Keep it outside the document root.
		'local' => [
			'driver' => 'local',
			'root'   => APPPATH.'files',
		],

		// An FTP server
		// 'ftp' => [
		// 	'driver'   => 'ftp',
		// 	'host'     => 'ftp.example.com',
		// 	'port'     => 21,
		// 	'username' => '',
		// 	'password' => '',
		// 	'ssl'      => true,     // FTPS; without it the password travels unprotected
		// 	'passive'  => true,
		// 	'timeout'  => 30,
		// 	'root'     => '',       // directory on the server, the one of the login when empty
		// ],

		// Amazon S3, or a service that speaks the same language
		// 's3' => [
		// 	'driver'   => 's3',
		// 	'key'      => '',
		// 	'secret'   => '',
		// 	'region'   => 'eu-west-1',
		// 	'bucket'   => '',
		// 	'root'     => '',       // start of every key, such as "site/files"
		// 	'timeout'  => 30,
		//
		// 	// For other services than Amazon: their address
		// 	// 'endpoint' => 'https://ams3.digitaloceanspaces.com',
		// ],
	],
];
