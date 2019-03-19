<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class ListenerWithoutInterface
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $calls = [];

	public function onClear(): void
	{
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
