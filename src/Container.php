<?php

declare(strict_types=1);

namespace Semperton\Container;

use Psr\Container\ContainerInterface;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\ParameterResolveException;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Exception\NotInstantiableException;
use ReflectionClass;
use ReflectionFunctionAbstract;
use Closure;

class Container implements ContainerInterface
{
	protected $entries = [];

	protected $factories = [];

	protected $resolving = [];

	protected $autowire;

	public function __construct(iterable $definitions = [], bool $autowire = true)
	{
		$this->autowire = $autowire;

		// register self
		$this->entries[self::class] = $this;
		$this->entries[ContainerInterface::class] = $this;

		foreach ($definitions as $id => $object) {
			$this->set($id, $object);
		}
	}

	protected function set(string $id, $object): void
	{
		unset($this->entries[$id], $this->factories[$id]);

		if ($object instanceof Closure) {

			$this->factories[$id] = $object;
		} else {
			$this->entries[$id] = $object;
		}
	}

	public function with(string $id, $object): Container
	{
		$container = clone $this;

		$container->set($id, $object);

		return $container;
	}

	public function get(string $id)
	{
		if (isset($this->entries[$id]) || array_key_exists($id, $this->entries)) {

			return $this->entries[$id];
		}

		if (isset($this->factories[$id])) {

			$this->entries[$id] = $this->resolve($id);

			return $this->entries[$id];
		}

		if ($this->autowire && $this->canCreate($id)) {

			$this->factories[$id] = $this->getClassFactory($id);
			$this->entries[$id] = $this->resolve($id);

			return $this->entries[$id];
		}

		throw new NotFoundException("Entry for < $id > could not be resolved");
	}

	protected function resolve(string $id)
	{
		if (isset($this->resolving[$id])) {
			throw new CircularReferenceException("Circular reference detected for < $id >");
		}

		$this->resolving[$id] = true;

		$object = $this->factories[$id]($this);

		unset($this->resolving[$id]);

		return $object;
	}

	protected function getClassFactory(string $name): Closure
	{
		$class = new ReflectionClass($name);

		if (!$class->isInstantiable()) {
			throw new NotInstantiableException("Unable to create < $name >, not instantiable");
		}

		$constructor = $class->getConstructor();

		try {
			$args = $constructor ? $this->getFunctionArgs($constructor) : [];
		} catch (ParameterResolveException $e) {
			throw new ParameterResolveException($e->getMessage() . " of < $name >");
		}

		return function () use ($name, $args) {
			return new $name(...$args);
			// return $class->newInstanceArgs($args);
		};
	}

	protected function getFunctionArgs(ReflectionFunctionAbstract $function): array
	{
		$params = $function->getParameters();

		$args = [];

		foreach ($params as $param) {

			/** @var ReflectionNamedType */
			$type = $param->getType();

			if ($type && !$type->isBuiltin()) {

				$className = $type->getName();

				$args[] = $this->get($className);
			} else if ($param->isOptional()) {

				$args[] = $param->getDefaultValue();
			} else {
				$pname = $param->getName();
				$fname = $function->getName();
				throw new ParameterResolveException("Unable to resolve < \$$pname > for < $fname >");
			}
		}

		return $args;
	}

	protected function canCreate(string $name): bool
	{
		return class_exists($name);
	}

	public function has(string $id): bool
	{
		if (
			isset($this->entries[$id]) || array_key_exists($id, $this->entries) ||
			isset($this->factories[$id]) || $this->canCreate($id)
		) {
			return true;
		}

		return false;
	}

	public function entries(): array
	{
		$entries = array_unique(
			array_merge(
				array_keys($this->entries),
				array_keys($this->factories)
			)
		);

		sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

		return $entries;
	}
}
