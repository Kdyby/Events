<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class InheritClassOnlyListener implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $eventCalls = [];

	public function getSubscribedEvents(): array
	{
		return [InheritedClass::class . '::onCreate'];
	}

	public function onCreate(): void
	{
		$this->eventCalls[] = func_get_args();
	}

}
