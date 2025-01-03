<?php

declare(strict_types=1);

namespace Semperton\Container;

interface FactoryInterface
{
	/**
	 * @param array<string, mixed> $params
	 */
	public function create(string $id, array $params = []): mixed;
}
