<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Events\IExceptionHandler.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Kdyby\Events\EventManager;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class IExceptionHandlerTest extends \Tester\TestCase
{

	/** @var \Kdyby\Events\EventManager */
	private $evm;

	protected function setUp(): void
	{
		$this->evm = new EventManager();
		$this->evm->addEventListener('testEvent', [$this, 'eventHandler']);
	}

	public function testNotCaught(): void
	{
		Assert::exception(function (): void {
			$this->evm->dispatchEvent('testEvent');
		}, \Throwable::class);
	}

	public function testCaught(): void
	{
		$this->evm->setExceptionHandler($handler = new SampleExceptionHandler());
		$this->evm->dispatchEvent('testEvent');

		Assert::same(1, count($handler->exceptions));
		Assert::true($handler->exceptions[0] instanceof \Exception);
	}

	public function eventHandler(): void
	{
		throw new \Exception('dummy');
	}

}

(new IExceptionHandlerTest())->run();
