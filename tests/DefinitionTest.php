<?php

declare(strict_types=1);

namespace Semperton\Container\Test;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Semperton\Container\Container;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Test\Mock\DepA;
use Semperton\Container\Test\Mock\DepB;
use Semperton\Container\Test\Mock\DepC;

final class DefinitionTest extends TestCase
{
	public function testScalarDefinition()
	{
		$container = new Container([
			'name' => 'Semperton',
			'number' => 42
		]);
		$this->assertEquals('Semperton', $container->get('name'));
		$this->assertEquals(42, $container->get('number'));
	}

	public function testSimpleFactory()
	{
		$container = new Container([
			DepA::class => static fn () => new DepA()
		]);
		$this->assertInstanceOf(DepA::class, $container->get(DepA::class));
	}

	public function testFactoryContainer()
	{
		$container = new Container([
			'container' => static fn (Container $c) => $c,
			'interface' => static fn (ContainerInterface $c) => $c
		]);
		$this->assertSame($container, $container->get('container'));
		$this->assertSame($container, $container->get('interface'));

		$container = $container->with(ContainerInterface::class, fn () => new Container());

		$this->assertNotSame($container->get('interface'), $container->get(ContainerInterface::class));
	}

	public function testFactoryDependency()
	{
		$container = new Container([
			DepB::class => static fn (DepA $a) => new DepB($a)
		]);

		$b = $container->get(DepB::class);

		$this->assertInstanceOf(DepB::class, $b);
		$this->assertInstanceOf(DepA::class, $b->a);
	}

	public function testFactoryCircularReference()
	{
		$this->expectException(CircularReferenceException::class);

		$container = new Container([
			DepB::class => static fn (DepB $b) => $b
		]);

		$container->get(DepB::class);
	}

	public function testFactoryCreate()
	{
		$this->expectException(CircularReferenceException::class);

		$container = new Container([
			DepC::class => static fn (Container $c) => $c->create(DepC::class, ['name' => 'Semperton'])
		]);

		$c = $container->get(DepC::class);
	}
}
