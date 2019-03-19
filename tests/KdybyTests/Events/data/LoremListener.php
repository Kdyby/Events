<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class LoremListener implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $calls = [];

	/**
	 * Returns an array of events this subscriber wants to listen to.
	 */
	public function getSubscribedEvents(): array
	{
		return [
			'onMagic',
			'onStartup',
		];
	}

	public function onMagic(FooMock $foo, int $int): void
	{
		$this->calls[] = [__METHOD__, func_get_args()];
	}

	public function onStartup(StartupEventArgs $args): void
	{
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
