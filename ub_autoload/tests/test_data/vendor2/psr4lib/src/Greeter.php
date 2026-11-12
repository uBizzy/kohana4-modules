<?php

namespace UBTest\Psr4;

/**
 * The same class in a vendor directory that comes later: never loaded.
 */
class Greeter {

	public function greet(): string
	{
		return 'hello from the second vendor directory';
	}

}
