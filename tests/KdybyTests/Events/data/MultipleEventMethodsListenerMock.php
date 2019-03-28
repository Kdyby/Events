<?php

namespace KdybyTests\Events;

class MultipleEventMethodsListenerMock implements \Kdyby\Events\Subscriber
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
			'Article::onDiscard' => [
				['firstMethod', 25],
				['secondMethod', 10],
			],
		];
	}

	public function firstMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

	public function secondMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
