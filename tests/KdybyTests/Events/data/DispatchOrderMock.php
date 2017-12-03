<?php

namespace KdybyTests\Events;

class DispatchOrderMock
{

	use \Nette\SmartObject;

	/**
	 * @globalDispatchFirst
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onGlobalDispatchFirst = [];

	/**
	 * @globalDispatchFirst false
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onGlobalDispatchLast = [];

	/**
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public $onGlobalDispatchDefault = [];

}
