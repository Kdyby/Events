<?php

/**
 * Test: Kdyby\Events\IExceptionHandler.
 *
 * @testCase Kdyby\Events\IExceptionHandlerTestCase
 * @author Jan Dolecek <juzna.cz@gmail.com>
 * @package Kdyby\Events
 */

namespace KdybyTests\Events;

use Kdyby;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/mocks.php';



/**
 * @author Jan Dolecek <juzna.cz@gmail.com>
 */
class IExceptionHandlerTestCase extends Tester\TestCase
{

	/** @var Kdyby\Events\EventManager */
	private $evm;



	protected function setUp()
	{
		$this->evm = new Kdyby\Events\EventManager();
		$this->evm->addEventListener('testEvent', [$this, 'eventHandler']);
	}



	public function testNotCaught()
	{
		$evm = $this->evm;
		Assert::exception(function() use ($evm) {
			$evm->dispatchEvent('testEvent');
		}, 'Exception');
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
		throw new \Exception("dummy");
	}

}

\run(new IExceptionHandlerTestCase());
