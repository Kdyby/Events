<?php

/**
 * Test: Kdyby\Events\Extension.
 *
 * @testCase Kdyby\Events\ExtensionTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Events
 */

namespace KdybyTests\Events;

use Kdyby;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/mocks.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends Tester\TestCase
{

	/**
	 * @param string $configFile
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	public function createContainer($configFile)
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(array('container' => array('class' => 'SystemContainer_' . md5($configFile))));
		Kdyby\Events\DI\EventsExtension::register($config);
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		return $config->createContainer();
	}



	public function testRegisterListeners()
	{
		$container = $this->createContainer('subscribers');
		$manager = $container->getService('events.manager');
		/** @var Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof Kdyby\Events\EventManager);
		Assert::equal(2, count($manager->getListeners()));
	}



	public function testValidate()
	{
		$me = $this;

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.direct');
		}, "Nette\\Utils\\AssertionException", 'Please, do not register listeners directly to service \'events.manager\'. %a%');

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.missing');
		}, "Nette\\Utils\\AssertionException", 'Please, specify existing class for service \'events.subscriber.%a%\' explicitly, and make sure, that the class exists and can be autoloaded.');

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.fake');
		}, "Nette\\Utils\\AssertionException", 'Subscriber \'events.subscriber.%a%\' doesn\'t implement Kdyby\Events\Subscriber.');

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.invalid');
		}, "Nette\\Utils\\AssertionException", 'Event listener KdybyTests\Events\FirstInvalidListenerMock::onFoo() is not implemented.');

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.invalid2');
		}, "Nette\\Utils\\AssertionException", 'Event listener KdybyTests\Events\SecondInvalidListenerMock::onBar() is not implemented.');
	}



	public function testAutowire()
	{
		$container = $this->createContainer('autowire');

		$app = $container->getService('application');
		/** @var Nette\Application\Application $app */
		Assert::true($app->onStartup instanceof Kdyby\Events\Event);
		Assert::same('Nette\Application\Application::onStartup', $app->onStartup->getName());

		Assert::true($app->onRequest instanceof Kdyby\Events\Event);
		Assert::same('Nette\Application\Application::onRequest', $app->onRequest->getName());

		Assert::true($app->onResponse instanceof Kdyby\Events\Event);
		Assert::same('Nette\Application\Application::onResponse', $app->onResponse->getName());

		Assert::true($app->onError instanceof Kdyby\Events\Event);
		Assert::same('Nette\Application\Application::onError', $app->onError->getName());

		Assert::true($app->onShutdown instanceof Kdyby\Events\Event);
		Assert::same('Nette\Application\Application::onShutdown', $app->onShutdown->getName());

		// not all properties are affected
		Assert::true(is_bool($app->catchExceptions));
		Assert::true(!is_object($app->errorPresenter));

		$user = $container->getService('user');
		/** @var Nette\Security\User $user */
		Assert::true($user->onLoggedIn instanceof Kdyby\Events\Event);
		Assert::same('Nette\Security\User::onLoggedIn', $user->onLoggedIn->getName());

		Assert::true($user->onLoggedOut instanceof Kdyby\Events\Event);
		Assert::same('Nette\Security\User::onLoggedOut', $user->onLoggedOut->getName());
	}



	public function testOptimize()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof Kdyby\Events\EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$manager->dispatchEvent('onFoo', $bazArgs = new EventArgsMock());
		Assert::false($container->isCreated('foo'));
		Assert::true($container->isCreated('bar'));
		Assert::true($container->isCreated('baz'));
		Assert::same(2, count($manager->getListeners('onFoo')));

		$manager->dispatchEvent('App::onFoo', $bazArgsSecond = new EventArgsMock());
		Assert::same(1, count($manager->getListeners('App::onFoo')));

		$baz = $container->getService('baz');
		/** @var NamespacedEventListenerMock $baz */
		$bar = $container->getService('bar');
		/** @var EventListenerMock $bar */

		Assert::same(array(
			array('KdybyTests\Events\EventListenerMock::onFoo', array($bazArgs))
		), $bar->calls);

		Assert::same(array(
			array('KdybyTests\Events\NamespacedEventListenerMock::onFoo', array($bazArgs)),
			array('KdybyTests\Events\NamespacedEventListenerMock::onFoo', array($bazArgsSecond)),
		), $baz->calls);
	}



	public function testOptimize_dispatchNamespaceFirst()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof Kdyby\Events\EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$manager->dispatchEvent('App::onFoo', $bazArgs = new EventArgsMock());
		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::true($container->isCreated('baz'));
		Assert::same(1, count($manager->getListeners('App::onFoo')));

		$baz = $container->getService('baz');
		/** @var NamespacedEventListenerMock $baz */

		Assert::same(array(
			array('KdybyTests\Events\NamespacedEventListenerMock::onFoo', array($bazArgs))
		), $baz->calls);
	}



	public function testOptimize_standalone()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof Kdyby\Events\EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$manager->dispatchEvent('onStartup', $bazArgs = new StartupEventArgs($foo = new FooMock(), $num = 123));
		Assert::true($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		Assert::same(1, count($manager->getListeners('onStartup')));

		$baz = $container->getService('foo');
		/** @var NamespacedEventListenerMock $baz */

		Assert::same(array(
			array('KdybyTests\Events\LoremListener::onStartup', array($bazArgs))
		), $baz->calls);
	}



	public function testExceptionHandler()
	{
		$container = $this->createContainer('exceptionHandler');
		$manager = $container->getService('events.manager');

		// getter not needed, so hack it via reflection
		$rp = new \ReflectionProperty('Kdyby\Events\EventManager', 'exceptionHandler');
		$rp->setAccessible(TRUE);
		$handler = $rp->getValue($manager);

		Assert::true($handler instanceof Kdyby\Events\IExceptionHandler);
	}

}

\run(new ExtensionTest());
