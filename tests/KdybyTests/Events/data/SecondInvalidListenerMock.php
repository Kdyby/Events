<?php

namespace KdybyTests\Events;

class SecondInvalidListenerMock implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'Application::onBar',
		];
	}

}
