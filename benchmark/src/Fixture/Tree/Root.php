<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Tree;

final class Root
{
	public function __construct(
		public readonly NodeA $a,
		public readonly NodeB $b,
		public readonly NodeC $c
	) {}
}
