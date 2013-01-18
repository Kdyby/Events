<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Doctrine\Common;

if (!class_exists('Doctrine\Common\EventManager')) {

	class EventArgs
	{

	}

	abstract class EventManager
	{

		/**
		 * Adds an EventSubscriber. The subscriber is asked for all the events he is
		 * interested in and added as a listener for these events.
		 *
		 * @param \Doctrine\Common\EventSubscriber $subscriber The subscriber.
		 */
		public function addEventSubscriber(EventSubscriber $subscriber)
		{
			$this->addEventListener($subscriber->getSubscribedEvents(), $subscriber);
		}



		/**
		 * Removes an EventSubscriber. The subscriber is asked for all the events it is
		 * interested in and removed as a listener for these events.
		 *
		 * @param \Doctrine\Common\EventSubscriber $subscriber The subscriber.
		 */
		public function removeEventSubscriber(EventSubscriber $subscriber)
		{
			$this->removeEventListener($subscriber->getSubscribedEvents(), $subscriber);
		}



		/**
		 * @param array $events
		 * @param EventSubscriber $listener
		 */
		abstract public function addEventListener($events, $listener);



		/**
		 * @param array $events
		 * @param EventSubscriber $listener
		 */
		abstract public function removeEventListener($events, $listener = NULL);

	}

	interface EventSubscriber
	{

	}

}
