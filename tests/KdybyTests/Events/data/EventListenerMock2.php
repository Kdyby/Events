<?php

namespace KdybyTests\Events;

class EventListenerMock2 implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'onFoo',
			'onBar',
		];
	}

	public function onFoo()
	{
	}

	public function onBar()
	{
	}

}
