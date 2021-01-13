<?php

declare(strict_types=1);

namespace Semperton\Container;

final class Entry
{
	public $value;
	public $isResolved;

	public function __construct($value, bool $resolved)
	{
		$this->value = $value;
		$this->isResolved = $resolved;
	}
}
