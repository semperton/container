<?php

declare(strict_types=1);

namespace Semperton\Container\Test\Mock;

final class DepO
{
	public ?DepA $a;

	public function __construct(?DepA $a)
	{
		$this->a = $a;
	}
}
