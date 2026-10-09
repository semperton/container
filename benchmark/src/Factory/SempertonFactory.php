<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Factory;

use Psr\Container\ContainerInterface;
use Semperton\Container\Container;

final class SempertonFactory implements ContainerFactory
{
	public function create(): ContainerInterface
	{
		return (new Container())->withAutowiring(true);
	}
}
