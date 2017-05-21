<?php

namespace KdybyTests\Events;

class RouterFactory
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @return \KdybyTests\Events\SampleRouter
	 */
	public function createRouter()
	{
		return new SampleRouter('nemam');
	}

}
