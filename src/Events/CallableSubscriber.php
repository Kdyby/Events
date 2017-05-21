<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Events;

/**
 * Bypasses the method_exists() check and expects you to either implement the method, or handle the event in magic __call().
 */
interface CallableSubscriber extends \Kdyby\Events\Subscriber
{

}
