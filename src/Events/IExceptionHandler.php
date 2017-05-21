<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events;

/**
 * Optional way to handle exceptions which happen in events
 */
interface IExceptionHandler
{

	/**
	 * Invoked when uncaught exception occurs within event handler
	 *
	 * @param \Exception $exception
	 * @return void
	 */
	public function handleException(\Exception $exception);

}
