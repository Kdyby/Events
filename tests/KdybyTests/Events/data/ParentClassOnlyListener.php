<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class ParentClassOnlyListener implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $eventCalls = [];

	public function getSubscribedEvents(): array
	{
		return [ParentClass::class . '::onCreate'];
	}

	public function onCreate(): void
	{
		$this->eventCalls[] = func_get_args();
	}

}
