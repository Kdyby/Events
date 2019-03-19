<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class RouterFactory
{

	use \Kdyby\StrictObjects\Scream;

	public function createRouter(): SampleRouter
	{
		return new SampleRouter('nemam');
	}

}
