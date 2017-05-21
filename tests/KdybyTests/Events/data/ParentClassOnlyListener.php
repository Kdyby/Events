<?php

namespace KdybyTests\Events;

class ParentClassOnlyListener implements \Kdyby\Events\Subscriber
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
		return [ParentClass::class . '::onCreate'];
	}

	public function onCreate()
	{
		$this->eventCalls[] = func_get_args();
	}

}
