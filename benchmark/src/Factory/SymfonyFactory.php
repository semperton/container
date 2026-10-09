<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Factory;

use Psr\Container\ContainerInterface;
use Semperton\Container\Benchmark\Fixture\Fixtures;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

use function class_exists;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function unlink;

final class SymfonyFactory implements ContainerFactory
{
	public const DIR = __DIR__ . '/../../var/symfony';

	private const CLASS_NAME = 'SempertonBenchmarkSymfonyContainer';

	/**
	 * Removes the compiled container, so it is rebuilt from the current fixtures
	 */
	public static function clear(): void
	{
		$file = self::DIR . '/' . self::CLASS_NAME . '.php';

		if (is_file($file)) {
			unlink($file);
		}
	}

	public function create(): ContainerInterface
	{
		if (!class_exists(self::CLASS_NAME, false)) {
			$file = self::DIR . '/' . self::CLASS_NAME . '.php';

			// compiling is a build step, it is done once and never measured
			if (!is_file($file)) {
				self::compile($file);
			}

			require $file;
		}

		/** @var ContainerInterface */
		return new (self::CLASS_NAME)();
	}

	private static function compile(string $file): void
	{
		$builder = new ContainerBuilder();

		foreach (Fixtures::classes() as $class) {
			$builder->autowire($class)->setPublic(true);
		}

		$builder->compile();

		if (!is_dir(self::DIR)) {
			mkdir(self::DIR, 0777, true);
		}

		file_put_contents($file, (new PhpDumper($builder))->dump(['class' => self::CLASS_NAME]));
	}
}
