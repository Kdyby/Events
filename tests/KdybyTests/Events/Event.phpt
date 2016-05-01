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
		return [
			[[NULL, 'onFoo', NULL], 'onFoo'],
			[['App', 'onFoo', '::'], 'App::onFoo'],
			[['App', 'onFoo', '::'], '\\App::onFoo'],
			[['app.blog', 'foo', '.'], 'app.blog.foo'],
		];
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
			$calls[] = [__METHOD__, func_get_args()];
		};
		$foo->onBar[] = function ($lorem) use (&$calls) {
			$calls[] = [__METHOD__, func_get_args()];
		};

		return $foo;
	}



	public function testDispatch_Method()
	{
		$foo = $this->dataDispatch($calls);
		$foo->onBar->dispatch([10]);

		Assert::count(2, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([10], $calls[0][1]);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[1][0]);
		Assert::same([10], $calls[1][1]);
	}



	public function testDispatch_Invoke()
	{
		$foo = $this->dataDispatch($calls);
		$foo->onBar(15);

		Assert::count(2, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([15], $calls[0][1]);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[1][0]);
		Assert::same([15], $calls[1][1]);
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
			$calls[] = [__METHOD__, func_get_args()];
		};

		// event
		$foo->onStartup = new Event('onStartup', [], __NAMESPACE__ . '\\StartupEventArgs');
		$foo->onStartup->injectEventManager($evm);

		// listener
		$foo->onStartup[] = function (FooMock $foo, $int) use (&$calls) {
			$calls[] = [__METHOD__, func_get_args()];
		};

		return $evm;
	}



	public function testDispatch_toManager_invoke()
	{
		$this->dataToManagerDispatch($foo = new FooMock, $listener = new LoremListener, $calls);

		$foo->onMagic($foo, 2);

		Assert::count(1, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([$foo, 2], $calls[0][1]);

		Assert::count(1, $listener->calls);
		Assert::same('KdybyTests\Events\LoremListener::onMagic', $listener->calls[0][0]);
		Assert::same([$foo, 2], $listener->calls[0][1]);
	}



	public function testDispatch_toManager_dispatch()
	{
		$this->dataToManagerDispatch($foo = new FooMock, $listener = new LoremListener, $calls);

		$foo->onMagic->dispatch([$foo, 3]);

		Assert::count(1, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([$foo, 3], $calls[0][1]);

		Assert::count(1, $listener->calls);
		Assert::same('KdybyTests\Events\LoremListener::onMagic', $listener->calls[0][0]);
		Assert::same([$foo, 3], $listener->calls[0][1]);
	}



	public function testDispatch_toManager_secondInvoke()
	{
		$this->dataToManagerDispatch($foo = new FooMock, $listener = new LoremListener, $calls);

		$foo->onStartup($foo, 4);

		Assert::count(1, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([$foo, 4], $calls[0][1]);
		Assert::same(1, count($listener->calls));

		list($call) = $listener->calls;
		Assert::same('KdybyTests\Events\LoremListener::onStartup', $call[0]);
		list($args) = $call[1];
		Assert::true($args instanceof StartupEventArgs);
		Assert::same($foo, $args->foo);
		Assert::same(4, $args->int);
	}



	public function testDispatchOrderGlobalFirst()
	{
		$listener = new EventListenerMock();
		$evm = new Kdyby\Events\EventManager();
		$evm->addEventSubscriber($listener);

		$event = new Event('onFoo');
		$event->injectEventManager($evm);
		$event->globalDispatchFirst = TRUE;

		$event[] = function () use ($listener) {
			$listener->calls[] = __METHOD__;
		};

		$args = new EventArgsMock();
		$event->dispatch($args);

		Assert::count(2, $listener->calls);
		Assert::same('KdybyTests\Events\EventListenerMock::onFoo', $listener->calls[0][0]);
		Assert::same([$args], $listener->calls[0][1]);
		Assert::match('KdybyTests\Events\%A?%{closure}', $listener->calls[1]);
	}



	public function testDispatchOrderGlobalLast()
	{
		$listener = new EventListenerMock();
		$evm = new Kdyby\Events\EventManager();
		$evm->addEventSubscriber($listener);

		$event = new Event('onFoo');
		$event->injectEventManager($evm);
		$event->globalDispatchFirst = FALSE;

		$event[] = function () use ($listener) {
			$listener->calls[] = __METHOD__;
		};

		$args = new EventArgsMock();
		$event->dispatch($args);

		Assert::count(2, $listener->calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $listener->calls[0]);
		Assert::same('KdybyTests\Events\EventListenerMock::onFoo', $listener->calls[1][0]);
		Assert::same([$args], $listener->calls[1][1]);
	}

}

\run(new EventTest());
