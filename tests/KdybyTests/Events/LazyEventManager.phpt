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

		Assert::false($sl->isCreated('first'));
		Assert::true($sl->isCreated('second'));

		Assert::same(array($sl->getService('second')), $fooListener);
	}



	/**
	 * @dataProvider dateGetListeners
	 */
	public function testGetListeners_all(Container $sl, LazyEventManager $lazy)
	{
		Assert::false($sl->isCreated('first'));
		Assert::false($sl->isCreated('second'));

		$all = $lazy->getListeners();

		Assert::true($sl->isCreated('first'));
		Assert::true($sl->isCreated('second'));

		Assert::same(array(
			'onFoo' => array(
				$sl->getService('first'),
				$sl->getService('second'),
			),
			'onBar' => array(
				$sl->getService('second'),
			),
		), $all);
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

}



\run(new LazyEventManagerTest());
