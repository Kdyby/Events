<?php

/**
 * Test: Kdyby\Events\EventArgs.
 *
 * @testCase Kdyby\Events\EventArgsTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Events
 */

namespace KdybyTests\Events;

use Doctrine\Common\EventArgs;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/mocks.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventArgsTest extends Tester\TestCase
{

	public function testImplementsDoctrineEventArgs()
	{
		$args = new EventArgsMock();
		Assert::true($args instanceof EventArgs);
	}

}

\run(new EventArgsTest());
