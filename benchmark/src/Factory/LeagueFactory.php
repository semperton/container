<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Factory;

use League\Container\Container;
use League\Container\ReflectionContainer;
use Psr\Container\ContainerInterface;

final class LeagueFactory implements ContainerFactory
{
	public function create(): ContainerInterface
	{
		$container = new Container();
		// cached resolutions make autowired services shared like in the other containers
		$container->delegate(new ReflectionContainer(true, ReflectionContainer::AUTO_WIRING));

		return $container;
	}
}
