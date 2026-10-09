<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Factory;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Semperton\Container\Benchmark\Fixture\Fixtures;

use function DI\autowire;
use function is_file;
use function unlink;

final class PhpDiCompiledFactory implements ContainerFactory
{
	public const DIR = __DIR__ . '/../../var/php-di';

	/**
	 * Removes the compiled container, so it is rebuilt from the current fixtures
	 */
	public static function clear(): void
	{
		$file = self::DIR . '/CompiledContainer.php';

		if (is_file($file)) {
			unlink($file);
		}
	}

	public function create(): ContainerInterface
	{
		$definitions = [];

		// only declared definitions are compiled, undeclared classes would be autowired at runtime
		foreach (Fixtures::classes() as $class) {
			$definitions[$class] = autowire();
		}

		// the compiled class is reused once it exists (in memory or in DIR)
		return (new ContainerBuilder())
			->useAutowiring(true)
			->useAttributes(false)
			->enableCompilation(self::DIR)
			->addDefinitions($definitions)
			->build();
	}
}
