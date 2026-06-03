<?php

declare(strict_types=1);

namespace Semperton\Container\Test\Mock;

final class DepC
{
	public DepB $b;
	public string $name;

	public function __construct(DepB $b, string $name, int $age = 22)
	{
		$this->b = $b;
		$this->name = $name;
	}
}
