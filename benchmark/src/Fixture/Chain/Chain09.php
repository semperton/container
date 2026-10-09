<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Chain;

final class Chain09
{
	public function __construct(public readonly Chain10 $next) {}
}
