<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Semperton\Container\Container;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\NotInstantiableException;
use Semperton\Container\Exception\ParameterResolveException;

require_once __DIR__ . '/../vendor/autoload.php';

final class PrivateClass
{
	private function __construct()
	{
	}
}

final class ParameterClass
{
	public function __construct(stdClass $obj, int $count)
	{
	}
}

final class ContainerTest extends TestCase
{
	public function testContainerInstance()
	{
		$this->assertInstanceOf(Container::class, new Container());
	}

	public function testGetValue()
	{
		$container = new Container(['foo' => 'bar']);
		$this->assertEquals('bar', $container->get('foo'));
	}

	public function testHasValue()
	{
		$container = new Container(['foo' => 'bar']);
		$this->assertTrue($container->has('foo'));
		$this->assertFalse($container->has('bar'));
		$this->assertTrue($container->has(stdClass::class));
	}

	public function testGetFactory()
	{
		$container = new Container(['foo' => function () {
			return 42;
		}]);
		$this->assertEquals(42, $container->get('foo'));
	}

	public function testGetClosure()
	{
		$container = new Container(['foo' => function () {
			return function () {
				return 42;
			};
		}]);
		$closure = $container->get('foo');
		$this->assertInstanceOf(Closure::class, $closure);
		$this->assertEquals(42, $closure());
	}

	public function testGetNotFound()
	{
		$this->expectException(NotFoundException::class);
		$container = new Container();
		$container->get('foo');
	}

	public function testGetAutowire()
	{
		$container = new Container();
		$this->assertInstanceOf(stdClass::class, $container->get(stdClass::class));
	}

	public function testGetSingleInstance()
	{
		$container = new Container();
		$obj1 = $container->get(stdClass::class);
		$obj2 = $container->get(stdClass::class);
		$this->assertEquals($obj1, $obj2);
	}

	public function testGetCircularReference()
	{
		$this->expectException(CircularReferenceException::class);
		$container = new Container(['foo' => function (ContainerInterface $c) {
			return $c->get('foo');
		}]);
		$container->get('foo');
	}

	public function testClassNotInstantiable()
	{
		$this->expectException(NotInstantiableException::class);
		$container = new Container();
		$container->get(PrivateClass::class);
	}

	public function testParameterResolve()
	{
		$this->expectException(ParameterResolveException::class);
		$container = new Container();
		$container->get(ParameterClass::class);
	}

	public function testContainerImmutability()
	{
		$container = new Container();
		$oldContainer = $container;
		$newContainer = $container->with('foo', 'bar');
		$this->assertEquals($container, $oldContainer);
		$this->assertNotEquals($container, $newContainer);
	}
}
