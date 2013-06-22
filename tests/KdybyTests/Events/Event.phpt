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

	public function dataParseName()
	{
		return array(
			array(array(NULL, 'onFoo'), 'onFoo'),
			array(array('App', 'onFoo'), 'App::onFoo'),
			array(array('App', 'onFoo'), '\\App::onFoo'),
			array(array('app.blog', 'foo'), 'app.blog.foo'),
		);
	}



	/**
	 * @dataProvider dataParseName
	 */
	public function testParseName($expected, $name)
	{
		Assert::same($expected, Event::parseName($name));
	}



	/**
	 * @param array $calls
	 * @return FooMock
	 */
	public function dataDispatch(&$calls)
	{
		$foo = new FooMock();
		$foo->onBar = new Event('bar');

		$foo->onBar[] = function ($lorem) use (&$calls) {
			$calls[] = array(__METHOD__, func_get_args());
		};
		$foo->onBar[] = function ($lorem) use (&$calls) {
			$calls[] = array(__METHOD__, func_get_args());
		};

		return $foo;
	}



	public function testDispatch_Method()
	{
		$foo = $this->dataDispatch($calls);
		$foo->onBar->dispatch(array(10));

		Assert::same(array(
			array('KdybyTests\Events\EventTest::KdybyTests\Events\{closure}', array(10)),
			array('KdybyTests\Events\EventTest::KdybyTests\Events\{closure}', array(10)),
		), $calls);
	}



	public function testDispatch_Invoke()
	{
		$foo = $this->dataDispatch($calls);
		$foo->onBar(15);

		Assert::same(array(
			array('KdybyTests\Events\EventTest::KdybyTests\Events\{closure}', array(15)),
			array('KdybyTests\Events\EventTest::KdybyTests\Events\{closure}', array(15)),
		), $calls);
	}



	/**
	 * @param FooMock $foo
	 * @param LoremListener $listener
	 * @param array $calls
	 * @return \Kdyby\Events\EventManager
	 */
	public function dataToManagerDispatch(FooMock $foo, LoremListener $listener, &$calls)
	{
		// create
		$evm = new Kdyby\Events\EventManager();
		$evm->addEventSubscriber($listener);

		// event
		$foo->onMagic = new Event('onMagic');
		$foo->onMagic->injectEventManager($evm);

		// listener
		$foo->onMagic[] = function (FooMock $foo, $int) use (&$calls) {
			$calls[] = array(__METHOD__, func_get_args());
		};

		// event
		$foo->onStartup = new Event('onStartup', array(), __NAMESPACE__ . '\\StartupEventArgs');
		$foo->onStartup->injectEventManager($evm);

		// listener
		$foo->onStartup[] = function (FooMock $foo, $int) use (&$calls) {
			$calls[] = array(__METHOD__, func_get_args());
		};

		return $evm;
	}



	public function testDispatch_toManager_invoke()
	{
		$this->dataToManagerDispatch($foo = new FooMock, $listener = new LoremListener, $calls);

		$foo->onMagic($foo, 2);
		Assert::same(array(array('KdybyTests\Events\EventTest::KdybyTests\Events\{closure}', array($foo, 2))), $calls);
		Assert::same(array(array('KdybyTests\Events\LoremListener::onMagic', array($foo, 2))), $listener->calls);
	}



	public function testDispatch_toManager_dispatch()
	{
		$this->dataToManagerDispatch($foo = new FooMock, $listener = new LoremListener, $calls);

		$foo->onMagic->dispatch(array($foo, 3));
		Assert::same(array(array('KdybyTests\Events\EventTest::KdybyTests\Events\{closure}', array($foo, 3))), $calls);
		Assert::same(array(array('KdybyTests\Events\LoremListener::onMagic', array($foo, 3))), $listener->calls);
	}



	public function testDispatch_toManager_secondInvoke()
	{
		$this->dataToManagerDispatch($foo = new FooMock, $listener = new LoremListener, $calls);

		$foo->onStartup($foo, 4);
		Assert::same(array(array('KdybyTests\Events\EventTest::KdybyTests\Events\{closure}', array($foo, 4))), $calls);
		Assert::same(1, count($listener->calls));
		list($call) = $listener->calls;
		Assert::same('KdybyTests\Events\LoremListener::onStartup', $call[0]);
		list($args) = $call[1];
		Assert::true($args instanceof StartupEventArgs);
		Assert::same($foo, $args->foo);
		Assert::same(4, $args->int);
	}

}

\run(new EventTest());
