<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Fixture\Interop;

final class MemoryLogger implements Logger
{
	/** @var list<string> */
	public array $messages = [];

	public function log(string $message): void
	{
		$this->messages[] = $message;
	}
}
