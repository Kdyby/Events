<?php

/**
 * Test: Kdyby\Events\Event.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Kdyby\Events\DI\EventsExtension;
use Kdyby\Events\Diagnostics\Panel;
use Nette\Configurator;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class PanelTest extends \Tester\TestCase
{

	/**
	 * @param string $configFile
	 * @return \Nette\DI\Container
	 */
	public function createContainer($configFile)
	{
		$config = new Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5($configFile)]]);
		EventsExtension::register($config);
		$config->addConfig(__DIR__ . '/../nette-reset.neon');
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		return $config->createContainer();
	}

	public function testRender()
	{
		$container = $this->createContainer('panel');

		$panel = new Panel($container);

		Assert::noError(function () use ($panel) {
			$panel->getTab();
			$panel->getPanel();
		});
	}

}

(new PanelTest())->run();
