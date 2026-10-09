<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Interop;

final class Mailer
{
	public function __construct(public readonly Logger $logger) {}
}
