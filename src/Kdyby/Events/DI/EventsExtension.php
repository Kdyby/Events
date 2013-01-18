<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events\DI;

use Doctrine\Common\EventSubscriber;
use Kdyby;
use Nette;
use Nette\PhpGenerator\PhpLiteral;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventsExtension extends Nette\Config\CompilerExtension
{
	const EVENT_TAG = 'kdyby.event';
	const SUBSCRIBER_TAG = 'kdyby.subscriber';

	/**
	 * @var array
	 */
	public $defaults = array(
		'subscribers' => array(),
		'validate' => TRUE,
		'autowire' => TRUE,
		'optimize' => TRUE,
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$builder->addDefinition($this->prefix('manager'))
			->setClass('Kdyby\Events\EventManager');

		$builder->addDefinition($this->prefix('eventFactory'))
			->setClass('Kdyby\Events\Event', array('%name%', '%defaults%'))
			->addSetup('injectEventManager', array($this->prefix('@manager')))
			->setInject(FALSE)
			->setParameters(array('name', 'defaults' => array()))
			->setShared(FALSE);

		Nette\Utils\Validators::assert($config, 'array');
		foreach ($config['subscribers'] as $subscriber) {
			$def = $builder->addDefinition($this->prefix('subscriber.' . md5(Nette\Utils\Json::encode($subscriber))));
			list($def->factory) = Nette\Config\Compiler::filterArguments(array(
				is_string($subscriber) ? new Nette\DI\Statement($subscriber) : $subscriber
			));

			list($subscriberClass) = (array) $builder->normalizeEntity($def->factory->entity);
			if (class_exists($subscriberClass)) {
				$def->class = $subscriberClass;
			}

			$def->setAutowired(FALSE);
			$def->addTag(self::SUBSCRIBER_TAG);
		}
	}



	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$manager = $builder->getDefinition($this->prefix('manager'));
		foreach (array_keys($builder->findByTag(self::SUBSCRIBER_TAG)) as $serviceName) {
			$manager->addSetup('addEventSubscriber', array('@' . $serviceName));
		}

		Nette\Utils\Validators::assertField($config, 'validate', 'bool');
		if ($config['validate']) {
			$this->validateSubscribers($builder, $manager);
		}

		Nette\Utils\Validators::assertField($config, 'autowire', 'bool');
		if ($config['autowire']) {
			$this->autowireEvents($builder);
		}

		Nette\Utils\Validators::assertField($config, 'optimize', 'bool');
		if ($config['optimize']) {
			throw new Nette\NotImplementedException;
		}
	}



	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 * @param \Nette\DI\ServiceDefinition $manager
	 * @throws \Nette\Utils\AssertionException
	 */
	private function validateSubscribers(Nette\DI\ContainerBuilder $builder, Nette\DI\ServiceDefinition $manager)
	{
		foreach ($manager->setup as $stt) {
			if ($stt->entity !== 'addEventSubscriber') {
				continue;
			}

			try {
				$serviceName = $builder->getServiceName(reset($stt->arguments));
				$def = $builder->getDefinition($serviceName);

			} catch (\Exception $e) {
				throw new Nette\Utils\AssertionException(
					"Please, do not register listeners directly to service '" . $this->prefix('manager') . "'. " .
					"Use section '" . $this->name . ": subscribers: ', or tag the service as '" . self::SUBSCRIBER_TAG . "'.",
					0, $e
				);
			}

			if (!$def->class || !class_exists($def->class)) {
				throw new Nette\Utils\AssertionException("Please, specify existing class for service '$serviceName' explicitly.");
			}

			if (!in_array('Doctrine\Common\EventSubscriber' , class_implements($def->class))) {
				// the minimum is Doctrine EventSubscriber, but recommend is Kdyby Subscriber
				throw new Nette\Utils\AssertionException("Subscriber '$serviceName' doesn't implement Kdyby\\Events\\Subscriber.");
			}

			$listenerInst = Nette\PhpGenerator\Helpers::createObject($def->class, array());
			/** @var EventSubscriber $listenerInst */
			foreach ($listenerInst->getSubscribedEvents() as $eventName) {
				if (!method_exists($listenerInst, $eventName)) {
					throw new Nette\Utils\AssertionException("Event listener " . $def->class . "::{$eventName}() is not implemented.");
				}
			}
		}
	}



	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 */
	private function autowireEvents(Nette\DI\ContainerBuilder $builder)
	{
		foreach ($builder->getDefinitions() as $def) {
			/** @var Nette\DI\ServiceDefinition $def */
			if (!class_exists($class = $builder->expand($def->class))) {
				continue;
			}

			$properties = Nette\Reflection\ClassType::from($class)->getProperties(Nette\Reflection\Property::IS_PUBLIC);
			foreach ($properties as $property) {
				if (!preg_match('#^on[A-Z]#', $name = $property->getName())) {
					continue 1;
				}

				$def->addSetup('$' . $name, array(
					new Nette\DI\Statement($this->prefix('@eventFactory'), array(
						$property->getDeclaringClass()->getName() . '::' . $name,
						new PhpLiteral('$service->' . $name)
					))
				));
			}
		}
	}



	/**
	 * @param \Nette\Config\Configurator $configurator
	 */
	public static function register(Nette\Config\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\Config\Compiler $compiler) {
			$compiler->addExtension('events', new EventsExtension());
		};
	}

}
