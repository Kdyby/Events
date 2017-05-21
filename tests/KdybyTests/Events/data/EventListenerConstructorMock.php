<?php

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

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'onFoo',
		];
	}

	public function onFoo(EventArgsMock $args)
	{
		$this->routerFactory->createRouter(); // pass
	}

}
