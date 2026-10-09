<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Interop;

interface Logger
{
	public function log(string $message): void;
}
