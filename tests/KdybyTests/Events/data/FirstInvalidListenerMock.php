<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class FirstInvalidListenerMock implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	public function getSubscribedEvents(): array
	{
		return [
			'onFoo',
		];
	}

}
