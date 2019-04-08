<?php

namespace KdybyTests\Events;

class SampleExceptionHandler implements \Kdyby\Events\IExceptionHandler
{

	/**
	 * @var \Exception[]
	 */
	public $exceptions = [];

	public function handleException(\Exception $exception)
	{
		$this->exceptions[] = $exception;
	}

}
