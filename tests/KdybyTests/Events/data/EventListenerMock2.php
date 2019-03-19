<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class EventListenerMock2 implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	public function getSubscribedEvents(): array
	{
		return [
			'onFoo',
			'onBar',
		];
	}

	public function onFoo(): void
	{
	}

	public function onBar(): void
	{
	}

}
