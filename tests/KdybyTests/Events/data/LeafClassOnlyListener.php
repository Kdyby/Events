<?php

namespace KdybyTests\Events;

class LeafClassOnlyListener implements \Kdyby\Events\Subscriber
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
		return [LeafClass::class . '::onCreate'];
	}

	public function onCreate()
	{
		$this->eventCalls[] = func_get_args();
	}

}
