<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Tree;

final class NodeB
{
	public function __construct(
		public readonly Leaf2 $first,
		public readonly Leaf3 $second,
		public readonly Leaf4 $third
	) {}
}
