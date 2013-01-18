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
	 * @return \SystemContainer
	 */
	public function createContainer($configFile)
	{
		$config = new Nette\Config\Configurator();
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
		Assert::equal(1, count($manager->getListeners()));
	}



	public function testValidate()
	{
		$me = $this;

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.direct');
		}, "Nette\\Utils\\AssertionException", 'Please, do not register listeners directly to service \'events.manager\'. %a%');

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.missing');
		}, "Nette\\Utils\\AssertionException", 'Please, specify existing class for service \'events.subscriber.%a%\' explicitly.');

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.fake');
		}, "Nette\\Utils\\AssertionException", 'Subscriber \'events.subscriber.%a%\' doesn\'t implement Kdyby\Events\Subscriber.');

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.invalid');
		}, "Nette\\Utils\\AssertionException", 'Event listener KdybyTests\Events\InvalidListenerMock::onFoo() is not implemented.');
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

}

\run(new ExtensionTest());
