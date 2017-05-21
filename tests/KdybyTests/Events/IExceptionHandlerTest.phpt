<?php

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

	protected function setUp()
	{
		$this->evm = new EventManager();
		$this->evm->addEventListener('testEvent', [$this, 'eventHandler']);
	}

	public function testNotCaught()
	{
		$evm = $this->evm;
		Assert::exception(function () use ($evm) {
			$evm->dispatchEvent('testEvent');
		}, \Exception::class);
	}

	public function testCaught()
	{
		$this->evm->setExceptionHandler($handler = new SampleExceptionHandler());
		$this->evm->dispatchEvent('testEvent');

		Assert::same(1, count($handler->exceptions));
		Assert::true($handler->exceptions[0] instanceof \Exception);
	}

	public function eventHandler()
	{
		throw new \Exception('dummy');
	}

}

(new IExceptionHandlerTest())->run();
