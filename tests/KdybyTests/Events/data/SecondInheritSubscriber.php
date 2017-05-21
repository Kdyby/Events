<?php

namespace KdybyTests\Events;

use Kdyby\Events\EventManager;
use Tracy\Helpers as TracyHelpers;

class SecondInheritSubscriber implements \Kdyby\Events\Subscriber
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
			ParentClass::class . '::onCreate',
		];
	}

	public function onCreate()
	{
		$event = TracyHelpers::findTrace(debug_backtrace(), EventManager::class . '::dispatchEvent');
		if ($event === NULL) {
			$this->eventCalls['unknown'] += 1;
		} else {
			$eventName = $event['args'][0];
			$this->eventCalls[$eventName] = 1 + (isset($this->eventCalls[$eventName]) ? $this->eventCalls[$eventName] : 0);
		}
	}

}
