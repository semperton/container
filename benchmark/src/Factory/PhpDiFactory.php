<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Factory;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

final class PhpDiFactory implements ContainerFactory
{
	public function create(): ContainerInterface
	{
		return (new ContainerBuilder())
			->useAutowiring(true)
			->useAttributes(false)
			->build();
	}
}
