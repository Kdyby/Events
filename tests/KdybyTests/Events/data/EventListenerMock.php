<?php

namespace KdybyTests\Events;

class EventListenerMock implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $calls = [];

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

	public function onFoo(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

	public function onBar(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
