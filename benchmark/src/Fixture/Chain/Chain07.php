<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Chain;

final class Chain07
{
	public function __construct(public readonly Chain08 $next) {}
}
