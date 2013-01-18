<?php

/**
 * Test: Kdyby\Events\Event.
 *
 * @testCase Kdyby\Events\EventTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Events
 */

namespace KdybyTests\Events;

use Kdyby;
use Kdyby\Events\Event;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/mocks.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventTest extends Tester\TestCase
{

	/**
	 * @return FooMock
	 */
	public function dataDispatch()
	{
		$foo = new FooMock();
		$foo->onBar = new Event('bar');
		$foo->onBar[] = function ($lorem) {
			echo $lorem;
		};
		$foo->onBar[] = function ($lorem) {
			echo $lorem + 1;
		};

		return $foo;
	}



	public function testDispatch_Method()
	{
		ob_start();
		$foo = $this->dataDispatch();
		$foo->onBar->dispatch(array(10));
		Assert::same('1011', ob_get_clean());
	}



	public function testDispatch_Invoke()
	{
		ob_start();
		$foo = $this->dataDispatch();
		$foo->onBar(15);
		Assert::same('1516', ob_get_clean());
	}



	/**
	 */
	public function testDispatch_toManager()
	{
		// create
		$evm = new Kdyby\Events\EventManager();
		$foo = new FooMock();
		$foo->onMagic = new Event('onMagic');
		$foo->onMagic->injectEventManager($evm);

		// register
		$evm->addEventSubscriber(new LoremListener());
		$foo->onMagic[] = function (FooMock $foo, $int) {
			echo $int * 3;
		};

		ob_start();
		$foo->onMagic($foo, 2);
		Assert::same('64', ob_get_clean());


		ob_start();
		$foo->onMagic->dispatch(array($foo, 2));
		Assert::same('64', ob_get_clean());

		$foo->onStartup = new Event('onStartup', array(), __NAMESPACE__ .  '\\StartupEventArgs');
		$foo->onStartup->injectEventManager($evm);

		$foo->onStartup[] = function (FooMock $foo, $int) {
			echo $int * 3;
		};

		ob_start();
		$foo->onStartup($foo, 4);
		Assert::same('1240', ob_get_clean());
	}

}

\run(new EventTest());
