<?php

namespace KdybyTests\Events;

class MagicEventListenerMock implements \Kdyby\Events\CallableSubscriber
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
			'onQuux',
			'onCorge',
		];
	}

	public function __call($name, $arguments)
	{
		$args = $arguments[0];
		$args->calls[] = [__CLASS__ . '::' . $name, $arguments];
		$this->calls[] = [__CLASS__ . '::' . $name, $arguments];
	}

}
