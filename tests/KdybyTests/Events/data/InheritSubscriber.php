<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class InheritSubscriber implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $eventCalls = [];

	public function getSubscribedEvents(): array
	{
		return [
			LeafClass::class . '::onCreate',
			ParentClass::class . '::onCreate',
		];
	}

	public function onCreate(): void
	{
		$backtrace = debug_backtrace();
		$event = $backtrace[2]['args'][0];
		$this->eventCalls[$event] = 1 + ($this->eventCalls[$event] ?? 0);
	}

}
