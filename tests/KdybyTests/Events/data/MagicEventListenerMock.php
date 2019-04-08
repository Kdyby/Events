<?php

namespace KdybyTests\Events;

class MagicEventListenerMock implements \Kdyby\Events\CallableSubscriber
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
			'onQuux',
			'onCorge',
		];
	}

	public function __call($name, $arguments)
	{
		$args = $arguments[0];
		$args->calls[] = [self::class . '::' . $name, $arguments];
		$this->calls[] = [self::class . '::' . $name, $arguments];
	}

}
