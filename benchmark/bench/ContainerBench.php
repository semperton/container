<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Bench;

use Generator;
use PhpBench\Attributes\BeforeClassMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\ParamProviders;
use Psr\Container\ContainerInterface;
use Semperton\Container\Benchmark\Factory\ContainerFactory;
use Semperton\Container\Benchmark\Factory\LeagueFactory;
use Semperton\Container\Benchmark\Factory\PhpDiCompiledFactory;
use Semperton\Container\Benchmark\Factory\PhpDiFactory;
use Semperton\Container\Benchmark\Factory\SempertonFactory;
use Semperton\Container\Benchmark\Factory\SymfonyFactory;
use Semperton\Container\Benchmark\Fixture\Chain\Chain01;
use Semperton\Container\Benchmark\Fixture\Fixtures;
use Semperton\Container\Benchmark\Fixture\Tree\Root;

#[BeforeClassMethods('compile')]
#[BeforeMethods('setUp')]
#[ParamProviders('provideContainers')]
final class ContainerBench
{
	private ContainerFactory $factory;

	private ContainerInterface $container;

	/**
	 * Runs once before all subjects, compiled containers are rebuilt from the current fixtures
	 */
	public static function compile(): void
	{
		PhpDiCompiledFactory::clear();
		SymfonyFactory::clear();

		(new PhpDiCompiledFactory())->create();
		(new SymfonyFactory())->create();
	}

	/**
	 * @return Generator<string, array{factory: class-string<ContainerFactory>}>
	 */
	public function provideContainers(): Generator
	{
		yield 'semperton' => ['factory' => SempertonFactory::class];
		yield 'php-di' => ['factory' => PhpDiFactory::class];
		yield 'php-di-compiled' => ['factory' => PhpDiCompiledFactory::class];
		yield 'symfony' => ['factory' => SymfonyFactory::class];
		yield 'league' => ['factory' => LeagueFactory::class];
	}

	/**
	 * @param array{factory: class-string<ContainerFactory>} $params
	 */
	public function setUp(array $params): void
	{
		Fixtures::preload();

		$this->factory = new $params['factory']();
		$this->container = $this->factory->create();
		$this->container->get(Root::class);
	}

	/**
	 * Creating an empty container
	 */
	public function benchBootstrap(): void
	{
		$this->factory->create();
	}

	/**
	 * Fresh container, resolves a linear graph 10 classes deep
	 */
	public function benchColdChain(): void
	{
		$this->factory->create()->get(Chain01::class);
	}

	/**
	 * Fresh container, resolves a fan-out graph of 9 classes with shared leaves
	 */
	public function benchColdTree(): void
	{
		$this->factory->create()->get(Root::class);
	}

	/**
	 * Already resolved service
	 */
	public function benchWarmGet(): void
	{
		$this->container->get(Root::class);
	}

	/**
	 * Already resolved service
	 */
	public function benchHasKnown(): void
	{
		$this->container->has(Root::class);
	}

	/**
	 * Id that is neither an entry nor a class
	 */
	public function benchHasUnknown(): void
	{
		$this->container->has('unknown');
	}
}
