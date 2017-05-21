<?php

namespace KdybyTests\Events;

class FirstInvalidListenerMock implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'onFoo',
		];
	}

}
