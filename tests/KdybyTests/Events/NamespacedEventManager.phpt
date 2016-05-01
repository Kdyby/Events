<?php

/**
 * Test: Kdyby\Events\NamespacedEventManager.
 *
 * @testCase KdybyTests\Events\NamespacedEventManagerTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Events
 */

namespace KdybyTests\Events;

use Kdyby;
use Kdyby\Events\NamespacedEventManager;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/mocks.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NamespacedEventManagerTest extends Tester\TestCase
{

	public function testHasListeners()
	{
		$evm = new Kdyby\Events\EventManager();
		$evm->addEventSubscriber($first = new EventListenerMock());

		Assert::true($evm->hasListeners('onFoo'));
		Assert::false($evm->hasListeners('App::onFoo'));

		$ns = new NamespacedEventManager('App::', $evm);

		Assert::true($ns->hasListeners('onFoo'));
		Assert::true($ns->hasListeners('App::onFoo'));
	}



	public function testHasListeners_withNamespace()
	{
		$evm = new Kdyby\Events\EventManager();
		$evm->addEventSubscriber($second = new NamespacedEventListenerMock());

		Assert::false($evm->hasListeners('onFoo'));
		Assert::true($evm->hasListeners('App::onFoo'));

		$ns = new NamespacedEventManager('App::', $evm);

		Assert::true($ns->hasListeners('onFoo'));
		Assert::true($ns->hasListeners('App::onFoo'));
	}



	public function testDispatch()
	{
		$evm = new Kdyby\Events\EventManager();
		$evm->addEventSubscriber($first = new EventListenerMock());
		$evm->addEventSubscriber($second = new NamespacedEventListenerMock());

		$ns = new NamespacedEventManager('App::', $evm);

		$ns->dispatchEvent('onFoo', new Kdyby\Events\EventArgsList([$args = new EventArgsMock()]));

		Assert::same([], $first->calls);

		Assert::same([
			['KdybyTests\\Events\\NamespacedEventListenerMock::onFoo', [$args]],
		], $second->calls);

		Assert::same([
			['KdybyTests\\Events\\NamespacedEventListenerMock::onFoo', [$args]],
		], $args->calls);
	}



	public function testDispatch_global()
	{
		$evm = new Kdyby\Events\EventManager();
		$evm->addEventSubscriber($first = new EventListenerMock());
		$evm->addEventSubscriber($second = new NamespacedEventListenerMock());

		$ns = new NamespacedEventManager('App::', $evm);
		$ns->dispatchGlobalEvents = TRUE;

		$ns->dispatchEvent('onFoo', new Kdyby\Events\EventArgsList([$args = new EventArgsMock()]));

		Assert::same([
			['KdybyTests\\Events\\EventListenerMock::onFoo', [$args]],
		], $first->calls);

		Assert::same([
			['KdybyTests\\Events\\NamespacedEventListenerMock::onFoo', [$args]],
		], $second->calls);

		Assert::same([
			['KdybyTests\\Events\\NamespacedEventListenerMock::onFoo', [$args]],
			['KdybyTests\\Events\\EventListenerMock::onFoo', [$args]],
		], $args->calls);
	}

}

\run(new NamespacedEventManagerTest());
