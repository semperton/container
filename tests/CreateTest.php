<?php

declare(strict_types=1);

namespace Semperton\Container\Test;

use PHPUnit\Framework\TestCase;
use Semperton\Container\Container;
use Semperton\Container\Exception\ParameterResolveException;
use Semperton\Container\Test\Mock\DepB;
use Semperton\Container\Test\Mock\DepA;
use Semperton\Container\Test\Mock\DepC;

final class CreateTest extends TestCase
{
	public function testInstance()
	{
		$container = new Container();
		$a = $container->create(DepA::class);

		$this->assertInstanceOf(DepA::class, $a);
	}

	public function testNewInstance()
	{
		$container = new Container();
		$a = $container->create(DepA::class);
		$a2 = $container->create(DepA::class);

		$this->assertNotSame($a, $a2);
	}

	public function testParameterException()
	{
		$this->expectException(ParameterResolveException::class);

		$container = new Container();
		$container->create(DepC::class);
	}

	public function testParameterException2()
	{
		$this->expectException(ParameterResolveException::class);

		$container = new Container([
			'name' => 'Semperton'
		]);

		$container->get(DepC::class);
	}

	public function testCreateArgs()
	{
		$container = new Container();
		$c = $container->create(DepC::class, [
			'name' => 'Semperton'
		]);

		$this->assertInstanceOf(DepC::class, $c);
		$this->assertInstanceOf(DepB::class, $c->b);
		$this->assertEquals('Semperton', $c->name);
	}
}
