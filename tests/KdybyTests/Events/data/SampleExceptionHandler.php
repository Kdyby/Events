<?php

namespace KdybyTests\Events;

class SampleExceptionHandler implements \Kdyby\Events\IExceptionHandler
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \Exception[]
	 */
	public $exceptions = [];

	public function handleException(\Exception $exception)
	{
		$this->exceptions[] = $exception;
	}

}
