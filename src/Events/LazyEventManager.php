<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events;

use Closure;
use Doctrine\Common\EventSubscriber;
use Kdyby\Events\Diagnostics\Panel;
use Nette\DI\Container as DIContainer;

/**
 * Is aware of DI Container and accepts map of listener service ids which then loads when needed.
 */
class LazyEventManager extends \Kdyby\Events\EventManager
{

	/**
	 * @var array<string, string[]>
	 */
	private $listenerIds;

	/**
	 * @var \Nette\DI\Container
	 */
	private $container;

	/**
	 * @param array<string, string[]> $listenerIds
	 * @param \Nette\DI\Container $container
	 */
	public function __construct(array $listenerIds, DIContainer $container)
	{
		$this->listenerIds = $listenerIds;
		$this->container = $container;
	}

	public function setPanel(Panel $panel): void
	{
		parent::setPanel($panel);
		$panel->setServiceIds($this->listenerIds);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getListeners($eventName = NULL): array
	{
		if ($eventName === NULL) {
			while (($type = key($this->listenerIds)) !== NULL) {
				$this->initializeListener($type);
			}

		} elseif (!empty($this->listenerIds[$eventName])) {
			$this->initializeListener($eventName);
		}

		return parent::getListeners($eventName);
	}

	/**
	 * {@inheritdoc}
	 */
	public function removeEventListener($unsubscribe, $subscriber = NULL): void
	{
		if ($unsubscribe instanceof EventSubscriber) {
			[$unsubscribe, $subscriber] = $this->extractSubscriber($unsubscribe);
		} elseif ($unsubscribe instanceof Closure) {
			[$unsubscribe, $subscriber] = $this->extractCallable($unsubscribe);
		}

		foreach ((array) $unsubscribe as $eventName) {
			if (array_key_exists($eventName, $this->listenerIds)) {
				$this->initializeListener($eventName);
			}
		}

		parent::removeEventListener($unsubscribe, $subscriber);
	}

	private function initializeListener(?string $eventName): void
	{
		foreach ($this->listenerIds[$eventName] as $serviceName) {
			$listener = $this->container->getService($serviceName);
			if ($listener instanceof Closure) {
				$this->addEventListener((string) $eventName, $listener);
			} elseif ($listener instanceof EventSubscriber) {
				$this->addEventSubscriber($listener);
			}
		}

		unset($this->listenerIds[$eventName]);
	}

}
