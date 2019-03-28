<?php

namespace KdybyTests\Events;

class ListenerWithoutInterface
{

	/**
	 * @var array
	 */
	public $calls = [];

	public function onClear()
	{
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
