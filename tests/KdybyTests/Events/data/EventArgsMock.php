<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class EventArgsMock extends \Kdyby\Events\EventArgs
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $calls = [];

}
