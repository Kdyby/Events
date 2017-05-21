<?php

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

	/**
	 * @param \KdybyTests\Events\FooMock $foo
	 * @param int $int
	 */
	public function __construct(FooMock $foo, $int)
	{
		$this->foo = $foo;
		$this->int = $int;
	}

}
