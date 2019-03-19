<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class StartupEventArgs extends \Kdyby\Events\EventArgs
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \KdybyTests\Events\FooMock
	 */
	public $foo;

	/**
	 * @var int
	 */
	public $int;

	public function __construct(FooMock $foo, int $int)
	{
		$this->foo = $foo;
		$this->int = $int;
	}

}
