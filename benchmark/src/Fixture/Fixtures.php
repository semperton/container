<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture;

use Semperton\Container\Benchmark\Fixture\Chain;
use Semperton\Container\Benchmark\Fixture\Tree;

final class Fixtures
{
	/**
	 * Concrete classes of the benchmark graphs, containers without autowiring of
	 * undeclared classes (Symfony, compiled PHP-DI) register these explicitly
	 *
	 * @return list<class-string>
	 */
	public static function classes(): array
	{
		return [
			Chain\Chain01::class,
			Chain\Chain02::class,
			Chain\Chain03::class,
			Chain\Chain04::class,
			Chain\Chain05::class,
			Chain\Chain06::class,
			Chain\Chain07::class,
			Chain\Chain08::class,
			Chain\Chain09::class,
			Chain\Chain10::class,
			Tree\Root::class,
			Tree\NodeA::class,
			Tree\NodeB::class,
			Tree\NodeC::class,
			Tree\Leaf1::class,
			Tree\Leaf2::class,
			Tree\Leaf3::class,
			Tree\Leaf4::class,
			Tree\Leaf5::class,
		];
	}

	/**
	 * Loads all fixture classes, so autoloading is not part of a measurement
	 */
	public static function preload(): void
	{
		foreach (self::classes() as $class) {
			class_exists($class);
		}
	}
}
