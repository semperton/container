<?php

declare(strict_types=1);

namespace Semperton\Container\Test\Mock;

final class DepV
{
	/** @var list<DepA> */
	public array $deps;

	public function __construct(DepA ...$deps)
	{
		$this->deps = $deps;
	}
}
