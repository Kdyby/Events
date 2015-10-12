<?php

/**
 * Test: Kdyby\Events\LazyEventManager.
 *
 * @testCase KdybyTests\Events\LazyEventManagerTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Events
 */

namespace KdybyTests\Events;

use Kdyby;
use Kdyby\Events\LazyEventManager;
use Nette;
use Nette\DI\Container;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/mocks.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LazyEventManagerTest extends Tester\TestCase
{


	public function dateGetListeners()
	{
		$sl = new ListenersContainer();

		$lazy = new LazyEventManager(array(
			'App::onFoo' => array(
				'first',
			),
			'onFoo' => array(
				'second',
			),
			'onBar' => array(
				'second',
			),
			'Article::onDiscard' => array(
				'third',
			),
			'onBaz' => array(
				'fourth',
			),
		), $sl);

		return array(array($sl, $lazy));
	}



	/**
	 * @dataProvider dateGetListeners
	 */
	public function testGetListeners_single(Container $sl, LazyEventManager $lazy)
	{
		Assert::false($sl->isCreated('first'));
		Assert::false($sl->isCreated('second'));

		$fooListener = $lazy->getListeners('onFoo');
		$bazListener = $lazy->getListeners('onBaz');

		Assert::false($sl->isCreated('first'));
		Assert::true($sl->isCreated('second'));

		Assert::same(array($sl->getService('second')), $fooListener);
		Assert::same(array($sl->getService('fourth')), $bazListener);
	}



	/**
	 * @dataProvider dateGetListeners
	 */
	public function testGetListeners_all(Container $sl, LazyEventManager $lazy)
	{
		Assert::false($sl->isCreated('first'));
		Assert::false($sl->isCreated('second'));
		Assert::false($sl->isCreated('third'));

		$all = $lazy->getListeners();

		Assert::true($sl->isCreated('first'));
		Assert::true($sl->isCreated('second'));
		Assert::true($sl->isCreated('third'));

		Assert::same(array(
			'App::onFoo' => array(
				$sl->getService('first'),
			),
			'onFoo' => array(
				$sl->getService('second'),
			),
			'onBar' => array(
				$sl->getService('second'),
			),
			'Article::onDiscard' => array(
				array(
					$sl->getService('third'),
					'customMethod'
				)
			),
			'onBaz' => array(
				$sl->getService('fourth'),
			),
		), $all);
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

		Assert::true($lazy->hasListeners('App::onFoo'));
		Assert::true($lazy->hasListeners('onFoo'));
		Assert::true($lazy->hasListeners('onBar'));
		Assert::true($lazy->hasListeners('onBaz'));
		Assert::true($lazy->hasListeners('Article::onDiscard'));

		$lazy->removeEventSubscriber($first);
		$lazy->removeEventSubscriber($second);
		$lazy->removeEventSubscriber($third);
		$lazy->removeEventListener($fourth); // callable is listener, not subscriber

		Assert::false($lazy->hasListeners('App::onFoo'));
		Assert::false($lazy->hasListeners('onFoo'));
		Assert::false($lazy->hasListeners('onBar'));
		Assert::false($lazy->hasListeners('onBaz'));
		Assert::false($lazy->hasListeners('Article::onDiscard'));
	}

}



class ListenersContainer extends Container
{

	protected function createServiceFirst()
	{
		return new NamespacedEventListenerMock();
	}



	protected function createServiceSecond()
	{
		return new EventListenerMock();
	}



	protected function createServiceThird()
	{
		return new MethodAliasListenerMock();
	}



	protected function createServiceFourth()
	{
		return function () {};
	}

}



\run(new LazyEventManagerTest());
