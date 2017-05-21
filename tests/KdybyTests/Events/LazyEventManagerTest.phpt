<?php

/**
 * Test: Kdyby\Events\LazyEventManager.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Kdyby\Events\LazyEventManager;
use Nette\DI\Container;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class LazyEventManagerTest extends \Tester\TestCase
{

	public function dateGetListeners()
	{
		$sl = new ListenersContainer();

		$lazy = new LazyEventManager([
			'App::onFoo' => [
				'first',
			],
			'onFoo' => [
				'second',
			],
			'onBar' => [
				'second',
			],
			'Article::onDiscard' => [
				'third',
			],
			'onBaz' => [
				'fourth',
			],
			'onQuux' => [
				'fifth',
			],
		], $sl);

		return [[$sl, $lazy]];
	}

	/**
	 * @dataProvider dateGetListeners
	 */
	public function testGetListenersSingle(Container $sl, LazyEventManager $lazy)
	{
		Assert::false($sl->isCreated('first'));
		Assert::false($sl->isCreated('second'));
		Assert::false($sl->isCreated('fourth'));
		Assert::false($sl->isCreated('fifth'));

		$fooListener = $lazy->getListeners('onFoo');
		$bazListener = $lazy->getListeners('onBaz');
		$quuxListener = $lazy->getListeners('onQuux');

		Assert::false($sl->isCreated('first'));
		Assert::true($sl->isCreated('second'));
		Assert::true($sl->isCreated('fourth'));
		Assert::true($sl->isCreated('fifth'));

		Assert::same([$sl->getService('second')], $fooListener);
		Assert::same([$sl->getService('fourth')], $bazListener);
		Assert::same([$sl->getService('fifth')], $quuxListener);
	}

	/**
	 * @dataProvider dateGetListeners
	 */
	public function testGetListenersAll(Container $sl, LazyEventManager $lazy)
	{
		Assert::false($sl->isCreated('first'));
		Assert::false($sl->isCreated('second'));
		Assert::false($sl->isCreated('third'));
		Assert::false($sl->isCreated('fourth'));
		Assert::false($sl->isCreated('fifth'));

		$all = $lazy->getListeners();

		Assert::true($sl->isCreated('first'));
		Assert::true($sl->isCreated('second'));
		Assert::true($sl->isCreated('third'));
		Assert::true($sl->isCreated('fourth'));
		Assert::true($sl->isCreated('fifth'));

		Assert::same([
			'App::onFoo' => [
				$sl->getService('first'),
			],
			'onFoo' => [
				$sl->getService('second'),
			],
			'onBar' => [
				$sl->getService('second'),
			],
			'Article::onDiscard' => [
				[
					$sl->getService('third'),
					'customMethod',
				],
			],
			'onBaz' => [
				$sl->getService('fourth'),
			],
			'onQuux' => [
				$sl->getService('fifth'),
			],
			'onCorge' => [
				$sl->getService('fifth'),
			],
		], $all);
	}

	/**
	 * @dataProvider dateGetListeners
	 */
	public function testRemoveSubscriber(Container $sl, LazyEventManager $lazy)
	{
		$first = $sl->getService('first');
		$second = $sl->getService('second');
		$third = $sl->getService('third');
		$fourth = $sl->getService('fourth');
		$fifth = $sl->getService('fifth');

		Assert::true($lazy->hasListeners('App::onFoo'));
		Assert::true($lazy->hasListeners('onFoo'));
		Assert::true($lazy->hasListeners('onBar'));
		Assert::true($lazy->hasListeners('Article::onDiscard'));
		Assert::true($lazy->hasListeners('onBaz'));
		Assert::true($lazy->hasListeners('onQuux'));

		$lazy->removeEventSubscriber($first);
		$lazy->removeEventSubscriber($second);
		$lazy->removeEventSubscriber($third);
		$lazy->removeEventListener($fourth); // callable is listener, not subscriber
		$lazy->removeEventSubscriber($fifth);

		Assert::false($lazy->hasListeners('App::onFoo'));
		Assert::false($lazy->hasListeners('onFoo'));
		Assert::false($lazy->hasListeners('onBar'));
		Assert::false($lazy->hasListeners('Article::onDiscard'));
		Assert::false($lazy->hasListeners('onQuux'));
	}

}

(new LazyEventManagerTest())->run();
