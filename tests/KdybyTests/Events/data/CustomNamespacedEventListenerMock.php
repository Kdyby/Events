<?php

namespace KdybyTests\Events;

class CustomNamespacedEventListenerMock implements \Kdyby\Events\Subscriber
{

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
			'domain.users.updated',
		];
	}

	public function updated(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
