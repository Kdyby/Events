<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class PriorityMethodAliasListenerMock implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $calls = [];

	public function getSubscribedEvents(): array
	{
		return [
			'Article::onDiscard' => ['customMethod', 10],
		];
	}

	public function customMethod(EventArgsMock $args): void
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
