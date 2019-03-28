<?php

namespace KdybyTests\Events;

class RouterFactory
{

	/**
	 * @return \KdybyTests\Events\SampleRouter
	 */
	public function createRouter()
	{
		return new SampleRouter('nemam');
	}

}
