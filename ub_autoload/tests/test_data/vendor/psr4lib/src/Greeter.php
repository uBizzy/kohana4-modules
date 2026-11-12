<?php

namespace UBTest\Psr4;

/**
 * A class of a made-up PSR-4 library, for the tests of ub_autoload.
 */
class Greeter {

	public function greet(): string
	{
		return 'hello from the first vendor directory';
	}

}
