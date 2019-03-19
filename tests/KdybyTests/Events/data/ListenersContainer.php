<?php

namespace KdybyTests\Events;

class ListenersContainer extends \Nette\DI\Container
{

	protected function createServiceFirst()
	{
		return new NamespacedEventListenerMock();
	}

	protected function createServiceSecond()
	{
		return new EventListenerMock();
	}

	protected function createServiceThird()
	{
		return new MethodAliasListenerMock();
	}

	protected function createServiceFourth()
	{
		return static function () {
		};
	}

	protected function createServiceFifth()
	{
		return new MagicEventListenerMock();
	}

}
