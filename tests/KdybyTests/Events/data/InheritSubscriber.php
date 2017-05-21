<?php

namespace KdybyTests\Events;

class InheritSubscriber implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $eventCalls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			LeafClass::class . '::onCreate',
			ParentClass::class . '::onCreate',
		];
	}

	public function onCreate()
	{
		$backtrace = debug_backtrace();
		$event = $backtrace[2]['args'][0];
		$this->eventCalls[$event] = 1 + (isset($this->eventCalls[$event]) ? $this->eventCalls[$event] : 0);
	}

}
