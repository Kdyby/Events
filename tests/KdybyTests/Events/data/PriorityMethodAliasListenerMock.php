<?php

namespace KdybyTests\Events;

class PriorityMethodAliasListenerMock implements \Kdyby\Events\Subscriber
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
			'Article::onDiscard' => ['customMethod', 10],
		];
	}

	public function customMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
