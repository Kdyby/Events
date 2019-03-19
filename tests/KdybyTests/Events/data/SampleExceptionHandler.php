<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class SampleExceptionHandler implements \Kdyby\Events\IExceptionHandler
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \Exception[]
	 */
	public $exceptions = [];

	public function handleException(\Exception $exception): void
	{
		$this->exceptions[] = $exception;
	}

}
