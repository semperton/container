<?php

declare(strict_types=1);

namespace Semperton\Container;

interface FactoryInterface
{
	/**
	 * @param array<string, mixed> $params
	 * @return mixed
	 */
	public function create(string $id, array $params = []);
}
