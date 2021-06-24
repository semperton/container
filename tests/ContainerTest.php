<?php

declare(strict_types=1);

namespace Semperton\Container\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Semperton\Container\Container;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\NotInstantiableException;
use Semperton\Container\Exception\ParameterResolveException;
use Semperton\Container\Test\Mock\DepA;
use Semperton\Container\Test\Mock\DepB;
use Semperton\Container\Test\Mock\DepC;
use Semperton\Container\Test\Mock\DepP;

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
		$this->assertTrue($container->has(DepA::class));
	}

	public function testFactoryClosure()
	{
		$container = new Container([
			'count' => 5,
			'count*2' => static fn (int $count) => $count * 2
		]);

		$num = $container->get('count*2');
		$this->assertEquals(10, $num);
	}

	public function testGetFactory()
	{
		$container = new Container(['foo' => static function () {
			return 42;
		}]);
		$this->assertEquals(42, $container->get('foo'));
	}

	public function testGetClosure()
	{
		$container = new Container(['foo' => static function () {
			return static function () {
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
		$b = $container->get(DepB::class);
		$this->assertInstanceOf(DepB::class, $b);
	}

	public function testGetSingleInstance()
	{
		$container = new Container();
		$obj1 = $container->get(DepA::class);
		$obj2 = $container->get(DepA::class);
		$this->assertEquals($obj1, $obj2);
	}

	public function testGetCircularReference()
	{
		$this->expectException(CircularReferenceException::class);
		$container = new Container(['foo' => static function (ContainerInterface $c) {
			return $c->get('foo');
		}]);
		$container->get('foo');
	}

	public function testClassNotInstantiable()
	{
		$this->expectException(NotInstantiableException::class);
		$container = new Container();
		$container->get(DepP::class);
	}

	public function testParameterResolve()
	{
		$this->expectException(ParameterResolveException::class);
		$container = new Container();
		$container->get(DepC::class);
	}

	public function testContainerImmutability()
	{
		$container = new Container();
		$oldContainer = $container;
		$newContainer = $container->with('foo', 'bar');
		$this->assertEquals($container, $oldContainer);
		$this->assertNotEquals($container, $newContainer);
	}

	public function testListEntries()
	{
		$container = new Container([
			'foo' => null,
			'bar' => static function () {
				return 42;
			},
			DepC::class => static function (Container $c) {
				$b = $c->get(DepB::class);
				return  new DepC($b, 'test');
			}
		]);
		$c = $container->get(DepC::class);
		$this->assertInstanceOf(DepC::class, $c);
		$entries = $container->entries();
		$expected = [
			'bar',
			'foo',
			DepA::class,
			DepB::class,
			DepC::class
		];
		$this->assertSame($expected, $entries);
	}
}
