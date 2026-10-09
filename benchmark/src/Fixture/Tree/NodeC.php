<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Tree;

final class NodeC
{
	public function __construct(
		public readonly Leaf3 $first,
		public readonly Leaf4 $second,
		public readonly Leaf5 $third
	) {}
}
