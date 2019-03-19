<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class CustomNamespacedEventListenerMock implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $calls = [];

	public function getSubscribedEvents(): array
	{
		return [
			'domain.users.updated',
		];
	}

	public function updated(EventArgsMock $args): void
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
