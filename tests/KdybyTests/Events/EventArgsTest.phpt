<?php

/**
 * Test: Kdyby\Events\EventArgs.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Doctrine\Common\EventArgs;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class EventArgsTest extends \Tester\TestCase
{

	public function testImplementsDoctrineEventArgs()
	{
		$args = new EventArgsMock();
		Assert::true($args instanceof EventArgs);
	}

}

(new EventArgsTest())->run();
