<?php

return [
	'default' => [
		/**
		 * How messages leave: 'smtp' (a mail server), 'mail' (the mail()
		 * function of PHP) or 'sendmail'.
		 */
		'transport' => 'smtp',

		/**
		 * Who the messages come from: [address, name].
		 */
		'from' => ['no-reply@example.com', 'My Site'],

		/**
		 * Where answers go, when not to "from": an address, or
		 * [address, name]. null for none.
		 */
		'reply_to' => null,

		/**
		 * Addresses that get a hidden copy of every message.
		 */
		'bcc' => [],

		/**
		 * The mail server, for the transport 'smtp'.
		 *
		 * "encryption" is 'tls' (STARTTLS, usually port 587) or 'ssl'
		 * (usually port 465). Without one of them a user name and a
		 * password are refused: they would travel in the open. The
		 * certificate of the server is always checked.
		 *
		 * [!!] Keep the password out of a public repository.
		 */
		'smtp' => [
			'host'       => 'localhost',
			'port'       => 587,
			'username'   => '',
			'password'   => '',
			'encryption' => 'tls',
			'timeout'    => 15,
		],

		/**
		 * Signs the messages with DKIM, when "domain" is set: the
		 * domain, the selector, the path of the private key and its
		 * passphrase.
		 */
		'dkim' => [
			'domain'     => '',
			'selector'   => '',
			'private'    => '',
			'passphrase' => '',
		],

		/**
		 * The view that goes around every HTML message. It gets
		 * $content (the message, HTML) and $subject. null for none.
		 */
		'layout' => 'ub_email/layout',

		/**
		 * While you develop: one or more addresses that get every
		 * message in place of the real recipients, who are named in
		 * the subject. null sends to the real recipients.
		 */
		'redirect' => null,
	],
];
