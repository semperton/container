<?php

declare(strict_types=1);

namespace Semperton\Container\Test\Mock;

final class DepN
{
	public ?DepAbs $abs;

	public function __construct(?DepAbs $abs = null)
	{
		$this->abs = $abs;
	}
}
