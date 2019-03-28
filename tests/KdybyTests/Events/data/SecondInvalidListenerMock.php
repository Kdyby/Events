<?php

namespace KdybyTests\Events;

class SecondInvalidListenerMock implements \Kdyby\Events\Subscriber
{

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
