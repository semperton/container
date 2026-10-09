<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Tree;

final class NodeA
{
	public function __construct(
		public readonly Leaf1 $first,
		public readonly Leaf2 $second,
		public readonly Leaf3 $third
	) {}
}
