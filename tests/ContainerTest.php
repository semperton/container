<?php

declare(strict_types=1);

namespace Semperton\Container\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Semperton\Container\Container;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Exception\DependencyException;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\NotInstantiableException;
use Semperton\Container\Exception\ParameterResolveException;
use Semperton\Container\Test\Mock\DepA;
use Semperton\Container\Test\Mock\DepB;
use Semperton\Container\Test\Mock\DepC;
use Semperton\Container\Test\Mock\DepN;
use Semperton\Container\Test\Mock\DepV;
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
		$container = (new Container(['foo' => 'bar']))->withAutowiring(true);
		$this->assertTrue($container->has('foo'));
		$this->assertFalse($container->has('bar'));
		$this->assertTrue($container->has(DepA::class));
	}

	public function testFactoryClosure()
	{
		$container = new Container([
			'count' => 5,
			'count*2' => static fn(Container $c) => $c->get('count') * 2
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
		$container = (new Container())->withAutowiring(true);
		$b = $container->get(DepB::class);
		$this->assertInstanceOf(DepB::class, $b);
	}

	public function testGetSingleInstance()
	{
		$container = (new Container())->withAutowiring(true);
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
		$container = (new Container())->withAutowiring(true);
		$container->get(DepP::class);
	}

	public function testParameterResolve()
	{
		$this->expectException(ParameterResolveException::class);
		$container = (new Container())->withAutowiring(true);
		$container->get(DepC::class);
	}

	public function testContainerImmutability()
	{
		$container = new Container();
		$oldContainer = $container;
		$newContainer = $container->withEntry('foo', 'bar');
		$this->assertEquals($container, $oldContainer);
		$this->assertNotEquals($container, $newContainer);
		$this->assertEquals('bar', $newContainer->get('foo'));

		$this->expectException(NotFoundException::class);
		$container->get('foo');
	}

	public function testListEntries()
	{
		$container = (new Container([
			'foo' => null,
			'bar' => static function () {
				return 42;
			},
			DepC::class => static function (Container $c) {
				$b = $c->get(DepB::class);
				return  new DepC($b, 'test');
			}
		]))->withAutowiring(true);
		$c = $container->get(DepC::class);
		$this->assertInstanceOf(DepC::class, $c);
		$entries = $container->entries();
		$expected = [
			'bar',
			'foo',
			ContainerInterface::class,
			Container::class,
			DepA::class,
			DepB::class,
			DepC::class
		];
		$this->assertSame($expected, $entries);
	}

	public function testClonedSelfReference()
	{
		$container = new Container();
		$newContainer = $container->withEntry('number', 42);

		$this->assertSame($newContainer, $newContainer->get(Container::class));
		$this->assertSame($newContainer, $newContainer->get(ContainerInterface::class));
		$this->assertSame($container, $container->get(Container::class));
	}

	public function testClonedFactoryCache()
	{
		$container = (new Container([
			'factory' => static fn(DepA $a) => $a
		]))->withAutowiring(true);
		$container->create('factory');

		$a = new DepA();
		$newContainer = $container->withEntry(DepA::class, $a);

		$this->assertSame($a, $newContainer->get('factory'));
	}

	public function testClonedResolvedInstances()
	{
		$container = (new Container())->withAutowiring(true);
		$b = $container->get(DepB::class);

		$a = new DepA();
		$newContainer = $container->withEntry(DepA::class, $a);

		$this->assertSame($b, $container->get(DepB::class));
		$this->assertSame($a, $newContainer->get(DepB::class)->a);
	}

	public function testAbstractOptionalDependency()
	{
		$container = (new Container())->withAutowiring(true);
		$n = $container->get(DepN::class);

		$this->assertNull($n->abs);
	}

	public function testCaughtCircularReference()
	{
		$runs = 0;
		$container = new Container([
			'foo' => static function (Container $c) use (&$runs) {
				$runs++;
				try {
					$c->get('foo');
				} catch (CircularReferenceException) {
				}
				return $c->get('bar');
			},
			'bar' => static fn(Container $c) => $c->get('foo')
		]);

		try {
			$container->get('foo');
			$this->fail('Expected CircularReferenceException');
		} catch (CircularReferenceException $e) {
			$this->assertSame('Circular reference detected: foo -> bar -> foo', $e->getMessage());
		}

		$this->assertSame(1, $runs);
	}

	public function testAutowiringDisabledByDefault()
	{
		$container = new Container();

		$this->assertFalse($container->has(DepA::class));

		$this->expectException(NotFoundException::class);
		$container->get(DepA::class);
	}

	public function testExplicitDefinitionsWithoutAutowiring()
	{
		$container = new Container([
			DepA::class => new DepA(),
			DepB::class => static fn(DepA $a) => new DepB($a)
		]);

		$this->assertSame($container, $container->get(Container::class));
		$this->assertSame($container->get(DepA::class), $container->get(DepB::class)->a);
		$this->assertInstanceOf(DepB::class, $container->create(DepB::class));
	}

	public function testNotInstantiableIsNotFound()
	{
		$container = (new Container())->withAutowiring(true);

		$this->assertFalse($container->has(DepP::class));

		$this->expectException(NotFoundExceptionInterface::class);
		$container->get(DepP::class);
	}

	public function testMissingDependencyIsWrapped()
	{
		$container = new Container([
			'svc' => static fn(Container $c) => $c->get('missing')
		]);

		$this->assertTrue($container->has('svc'));

		try {
			$container->get('svc');
			$this->fail('Expected DependencyException');
		} catch (DependencyException $e) {
			$this->assertNotInstanceOf(NotFoundExceptionInterface::class, $e);
			$this->assertInstanceOf(NotFoundException::class, $e->getPrevious());
		}
	}

	public function testVariadicNotAutowired()
	{
		$container = (new Container())->withAutowiring(true);
		$v = $container->create(DepV::class);

		$this->assertSame([], $v->deps);
	}

	public function testVariadicExplicitParam()
	{
		$container = new Container([
			DepV::class => static fn(DepA ...$deps) => new DepV(...$deps)
		]);
		$a1 = new DepA();
		$a2 = new DepA();
		$v = $container->create(DepV::class, ['deps' => [$a1, $a2]]);

		$this->assertSame([$a1, $a2], $v->deps);
	}

	public function testVariadicInvalidParam()
	{
		$this->expectException(ParameterResolveException::class);

		$container = (new Container())->withAutowiring(true);
		$container->create(DepV::class, ['deps' => new DepA()]);
	}
}
