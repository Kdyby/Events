<?php

/**
 * Test: Kdyby\Events\NamespacedEventManager.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Kdyby\Events\EventArgsList;
use Kdyby\Events\EventManager;
use Kdyby\Events\NamespacedEventManager;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class NamespacedEventManagerTest extends \Tester\TestCase
{

	public function testHasListeners()
	{
		$evm = new EventManager();
		$first = new EventListenerMock();
		$evm->addEventSubscriber($first);

		Assert::true($evm->hasListeners('onFoo'));
		Assert::false($evm->hasListeners('App::onFoo'));

		$ns = new NamespacedEventManager('App::', $evm);

		Assert::true($ns->hasListeners('onFoo'));
		Assert::true($ns->hasListeners('App::onFoo'));
	}

	public function testHasListenersWithNamespace()
	{
		$evm = new EventManager();
		$second = new NamespacedEventListenerMock();
		$evm->addEventSubscriber($second);

		Assert::false($evm->hasListeners('onFoo'));
		Assert::true($evm->hasListeners('App::onFoo'));

		$ns = new NamespacedEventManager('App::', $evm);

		Assert::true($ns->hasListeners('onFoo'));
		Assert::true($ns->hasListeners('App::onFoo'));
	}

	public function testDispatch()
	{
		$evm = new EventManager();
		$first = new EventListenerMock();
		$evm->addEventSubscriber($first);
		$second = new NamespacedEventListenerMock();
		$evm->addEventSubscriber($second);

		$ns = new NamespacedEventManager('App::', $evm);

		$args = new EventArgsMock();
		$ns->dispatchEvent('onFoo', new EventArgsList([$args]));

		Assert::same([], $first->calls);

		Assert::same([
			[NamespacedEventListenerMock::class . '::onFoo', [$args]],
		], $second->calls);

		Assert::same([
			[NamespacedEventListenerMock::class . '::onFoo', [$args]],
		], $args->calls);
	}

	public function testDispatchGlobal()
	{
		$evm = new EventManager();
		$first = new EventListenerMock();
		$evm->addEventSubscriber($first);
		$second = new NamespacedEventListenerMock();
		$evm->addEventSubscriber($second);

		$ns = new NamespacedEventManager('App::', $evm);
		$ns->dispatchGlobalEvents = TRUE;

		$args = new EventArgsMock();
		$ns->dispatchEvent('onFoo', new EventArgsList([$args]));

		Assert::same([
			[EventListenerMock::class . '::onFoo', [$args]],
		], $first->calls);

		Assert::same([
			[NamespacedEventListenerMock::class . '::onFoo', [$args]],
		], $second->calls);

		Assert::same([
			[NamespacedEventListenerMock::class . '::onFoo', [$args]],
			[EventListenerMock::class . '::onFoo', [$args]],
		], $args->calls);
	}

}

(new NamespacedEventManagerTest())->run();
