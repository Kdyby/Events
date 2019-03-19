<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class SampleRouter extends \Nette\Application\Routers\Route
{

	/**
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onMatch = [];

	/**
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onConstruct = [];

}
