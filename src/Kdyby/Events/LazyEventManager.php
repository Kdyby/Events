<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * @license http://www.kdyby.org/license
 */

namespace Kdyby\Events;

use Doctrine;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LazyEventManager extends EventManager
{

	/**
	 * @var \Kdyby\Config\TaggedServices
	 */
	private $subscribers;



	/**
	 * @internal
	 * @param \Kdyby\Config\TaggedServices $subscribers
	 */
	public function addSubscribers(Kdyby\Config\TaggedServices $subscribers)
	{
		$this->subscribers = $subscribers;
	}



	/**
	 * Registers all found subscribers when needed
	 */
	private function registerSubscribers()
	{
		if ($this->subscribers) {
			$subscribers = $this->subscribers;
			$this->subscribers = NULL;

			foreach ($subscribers as $subscriber) {
				$this->addEventSubscriber($subscriber);
			}
		}
	}



	/**
	 * @param string $eventName
	 * @param \Doctrine\Common\EventArgs|NULL $eventArgs
	 */
	public function dispatchEvent($eventName, Doctrine\Common\EventArgs $eventArgs = NULL)
	{
		$this->registerSubscribers();
		parent::dispatchEvent($eventName, $eventArgs);
	}



	/**
	 * @param null $event
	 * @return array
	 */
	public function getListeners($event = null)
	{
		$this->registerSubscribers();
		return parent::getListeners($event);
	}



	/**
	 * @param string $event
	 * @return bool
	 */
	public function hasListeners($event)
	{
		$this->registerSubscribers();
		return parent::hasListeners($event);
	}

}
