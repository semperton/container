<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Chain;

final class Chain02
{
	public function __construct(public readonly Chain03 $next) {}
}
