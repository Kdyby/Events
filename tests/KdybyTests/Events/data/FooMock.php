<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

/**
 * @method onBar($lorem)
 * @method onMagic(\KdybyTests\Events\FooMock $foo, $int)
 * @method onStartup(\KdybyTests\Events\FooMock $foo, $int)
 */
class FooMock
{

	use \Nette\SmartObject;

	/**
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onBar = [];

	/**
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onMagic = [];

	/**
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onStartup = [];

}
