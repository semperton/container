<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Chain;

final class Chain08
{
	public function __construct(public readonly Chain09 $next) {}
}
