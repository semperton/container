<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Factory;

use Psr\Container\ContainerInterface;

/**
 * Builds a container that resolves all fixture classes as shared services
 */
interface ContainerFactory
{
	public function create(): ContainerInterface;
}
