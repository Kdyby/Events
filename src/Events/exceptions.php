<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Events;

interface Exception
{

}

class InvalidListenerException extends \RuntimeException implements \Kdyby\Events\Exception
{

}

class InvalidStateException extends \RuntimeException implements \Kdyby\Events\Exception
{

}

class InvalidArgumentException extends \InvalidArgumentException implements \Kdyby\Events\Exception
{

}

class OutOfRangeException extends \OutOfRangeException implements \Kdyby\Events\Exception
{

}

class MemberAccessException extends \LogicException implements \Kdyby\Events\Exception
{

}

class NotSupportedException extends \LogicException implements \Kdyby\Events\Exception
{

}

class UnexpectedValueException extends \UnexpectedValueException implements \Kdyby\Events\Exception
{

}
