<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class ListenersContainer extends \Nette\DI\Container
{

	protected function createServiceFirst(): NamespacedEventListenerMock
	{
		return new NamespacedEventListenerMock();
	}

	protected function createServiceSecond(): EventListenerMock
	{
		return new EventListenerMock();
	}

	protected function createServiceThird(): MethodAliasListenerMock
	{
		return new MethodAliasListenerMock();
	}

	protected function createServiceFourth(): callable
	{
		return static function (): void {
		};
	}

	protected function createServiceFifth(): MagicEventListenerMock
	{
		return new MagicEventListenerMock();
	}

}
