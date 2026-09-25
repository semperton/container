<?php

declare(strict_types=1);

namespace Semperton\Container\Test\Mock;

final class DepU
{
	public DepA|DepB $dep;

	public function __construct(DepA|DepB $dep)
	{
		$this->dep = $dep;
	}
}
