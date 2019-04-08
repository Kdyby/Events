<?php

namespace KdybyTests\Events;

class FirstInvalidListenerMock implements \Kdyby\Events\Subscriber
{

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
