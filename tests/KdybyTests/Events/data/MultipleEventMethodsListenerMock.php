<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class MultipleEventMethodsListenerMock implements \Kdyby\Events\Subscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $calls = [];

	public function getSubscribedEvents(): array
	{
		return [
			'Article::onDiscard' => [
				['firstMethod', 25],
				['secondMethod', 10],
			],
		];
	}

	public function firstMethod(EventArgsMock $args): void
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

	public function secondMethod(EventArgsMock $args): void
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
