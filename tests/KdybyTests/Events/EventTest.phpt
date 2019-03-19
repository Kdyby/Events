<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Events\Event.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Kdyby\Events\Event;
use Kdyby\Events\EventManager;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class EventTest extends \Tester\TestCase
{

	public function dataParseName(): array
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
	public function testParseName(array $expected, string $name): void
	{
		Assert::same($expected, Event::parseName($name));
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param array $calls
	 */
	public function dataDispatch(&$calls): FooMock
	{
		$foo = new FooMock();
		$foo->onBar = new Event('bar');

		$foo->onBar[] = static function ($lorem) use (&$calls): void {
			$calls[] = [__METHOD__, func_get_args()];
		};
		$foo->onBar[] = static function ($lorem) use (&$calls): void {
			$calls[] = [__METHOD__, func_get_args()];
		};

		return $foo;
	}

	public function testDispatchMethod(): void
	{
		$foo = $this->dataDispatch($calls);
		$foo->onBar->dispatch([10]);

		Assert::count(2, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([10], $calls[0][1]);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[1][0]);
		Assert::same([10], $calls[1][1]);
	}

	public function testDispatchInvoke(): void
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
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param \KdybyTests\Events\FooMock $foo
	 * @param \KdybyTests\Events\LoremListener $listener
	 * @param array $calls
	 */
	public function dataToManagerDispatch(FooMock $foo, LoremListener $listener, &$calls): EventManager
	{
		// create
		$evm = new EventManager();
		$evm->addEventSubscriber($listener);

		// event
		$foo->onMagic = new Event('onMagic');
		$foo->onMagic->injectEventManager($evm);

		// listener
		$foo->onMagic[] = static function (FooMock $foo, $int) use (&$calls): void {
			$calls[] = [__METHOD__, func_get_args()];
		};

		// event
		$foo->onStartup = new Event('onStartup', [], StartupEventArgs::class);
		$foo->onStartup->injectEventManager($evm);

		// listener
		$foo->onStartup[] = static function (FooMock $foo, $int) use (&$calls): void {
			$calls[] = [__METHOD__, func_get_args()];
		};

		return $evm;
	}

	public function testDispatchToManagerInvoke(): void
	{
		$foo = new FooMock();
		$listener = new LoremListener();
		$this->dataToManagerDispatch($foo, $listener, $calls);

		$foo->onMagic($foo, 2);

		Assert::count(1, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([$foo, 2], $calls[0][1]);

		Assert::count(1, $listener->calls);
		Assert::same(LoremListener::class . '::onMagic', $listener->calls[0][0]);
		Assert::same([$foo, 2], $listener->calls[0][1]);
	}

	public function testDispatchToManagerDispatch(): void
	{
		$foo = new FooMock();
		$listener = new LoremListener();
		$this->dataToManagerDispatch($foo, $listener, $calls);

		$foo->onMagic->dispatch([$foo, 3]);

		Assert::count(1, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([$foo, 3], $calls[0][1]);

		Assert::count(1, $listener->calls);
		Assert::same(LoremListener::class . '::onMagic', $listener->calls[0][0]);
		Assert::same([$foo, 3], $listener->calls[0][1]);
	}

	public function testDispatchToManagerSecondInvoke(): void
	{
		$foo = new FooMock();
		$listener = new LoremListener();
		$this->dataToManagerDispatch($foo, $listener, $calls);

		$foo->onStartup($foo, 4);

		Assert::count(1, $calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $calls[0][0]);
		Assert::same([$foo, 4], $calls[0][1]);
		Assert::same(1, count($listener->calls));

		[$call] = $listener->calls;
		Assert::same(LoremListener::class . '::onStartup', $call[0]);
		[$args] = $call[1];
		Assert::true($args instanceof StartupEventArgs);
		Assert::same($foo, $args->foo);
		Assert::same(4, $args->int);
	}

	public function testDispatchOrderGlobalFirst(): void
	{
		$listener = new EventListenerMock();
		$evm = new EventManager();
		$evm->addEventSubscriber($listener);

		$event = new Event('onFoo');
		$event->injectEventManager($evm);
		$event->globalDispatchFirst = TRUE;

		$event[] = static function () use ($listener): void {
			$listener->calls[] = __METHOD__;
		};

		$args = new EventArgsMock();
		$event->dispatch($args);

		Assert::count(2, $listener->calls);
		Assert::same(EventListenerMock::class . '::onFoo', $listener->calls[0][0]);
		Assert::same([$args], $listener->calls[0][1]);
		Assert::match('KdybyTests\Events\%A?%{closure}', $listener->calls[1]);
	}

	public function testDispatchOrderGlobalLast(): void
	{
		$listener = new EventListenerMock();
		$evm = new EventManager();
		$evm->addEventSubscriber($listener);

		$event = new Event('onFoo');
		$event->injectEventManager($evm);
		$event->globalDispatchFirst = FALSE;

		$event[] = static function () use ($listener): void {
			$listener->calls[] = __METHOD__;
		};

		$args = new EventArgsMock();
		$event->dispatch($args);

		Assert::count(2, $listener->calls);
		Assert::match('KdybyTests\Events\%A?%{closure}', $listener->calls[0]);
		Assert::same(EventListenerMock::class . '::onFoo', $listener->calls[1][0]);
		Assert::same([$args], $listener->calls[1][1]);
	}

}

(new EventTest())->run();
