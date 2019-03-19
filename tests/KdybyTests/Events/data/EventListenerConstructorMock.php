<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class EventListenerConstructorMock implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \KdybyTests\Events\RouterFactory
	 */
	private $routerFactory;

	public function __construct(RouterFactory $routerFactory)
	{
		$this->routerFactory = $routerFactory;
	}

	public function getSubscribedEvents(): array
	{
		return [
			'onFoo',
		];
	}

	public function onFoo(EventArgsMock $args): void
	{
		$this->routerFactory->createRouter(); // pass
	}

}
