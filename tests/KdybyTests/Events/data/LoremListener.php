<?php

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
	 *
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'onMagic',
			'onStartup',
		];
	}

	/**
	 * @param \KdybyTests\Events\FooMock $foo
	 * @param int $int
	 */
	public function onMagic(FooMock $foo, $int)
	{
		$this->calls[] = [__METHOD__, func_get_args()];
	}

	/**
	 * @param \KdybyTests\Events\StartupEventArgs $args
	 */
	public function onStartup(StartupEventArgs $args)
	{
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
