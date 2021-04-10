<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Semperton\Container\Container;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\NotInstantiableException;
use Semperton\Container\Exception\ParameterResolveException;

final class A
{
}

final class B
{
	public function __construct(A $a, int $count = 0)
	{
	}
}

final class C
{
	public function __construct(B $b, ?int $count)
	{
	}
}

final class P
{
	private function __construct()
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
		$this->assertTrue($container->has(A::class));
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

	public function testGetInlineFactory()
	{
		$container = new Container();
		$container = $container->with('factory', function (ContainerInterface $c) {
			$b = $c->get(B::class);
			return new class($b)
			{
				protected $b;
				public function __construct(B $b)
				{
					$this->b = $b;
				}
				public function create()
				{
					return new C($this->b, 42);
				}
			};
		});
		$factory = $container->get('factory');
		$obj1 = $factory->create();
		$obj2 = $factory->create();
		$this->assertInstanceOf(C::class, $obj1);
		$this->assertFalse($obj1 === $obj2);
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
		$this->assertInstanceOf(B::class, $container->get(B::class));
	}

	public function testGetSingleInstance()
	{
		$container = new Container();
		$obj1 = $container->get(A::class);
		$obj2 = $container->get(A::class);
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
		$container->get(P::class);
	}

	public function testParameterResolve()
	{
		$this->expectException(ParameterResolveException::class);
		$container = new Container();
		$container->get(C::class);
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
			'bar' => function () {
				return 42;
			},
			C::class => function (Container $c) {
				$b = $c->get(B::class);
				return new C($b, 1);
			}
		]);
		$obj = $container->get(C::class);
		$entries = $container->entries();
		$this->assertInstanceOf(C::class, $obj);
		$expected = [
			A::class,
			B::class,
			'bar',
			C::class,
			'foo',
			ContainerInterface::class,
			Container::class
		];
		$this->assertSame($expected, $entries);
	}
}
