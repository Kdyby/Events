<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

/**
 * @method onCreate(string | NULL $arg)
 */
class ParentClass
{

	use \Nette\SmartObject;

	/**
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onCreate = [];

	/**
	 * @param mixed $arg
	 */
	public function create($arg = NULL): void
	{
		$this->onCreate($arg);
	}

}
