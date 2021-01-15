<?php

declare(strict_types=1);

namespace Semperton\Container;

use Semperton\Container\Exception\ContainerException;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\ParameterResolveException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunctionAbstract;
use Closure;

/**
 * Simple autowiring dependency container.
 * It holds values and creates instances ONCE.
 * It does not work as a factory class.
 */
class Container implements ContainerInterface
{
	protected $entries = [];

	protected $factories = [];

	protected $resolving = [];

	public function __construct(array $definitions = [])
	{
		$this->entries[self::class] = $this;
		$this->entries[ContainerInterface::class] = $this;

		foreach ($definitions as $def => $val) {
			$this->set($def, $val);
		}
	}

	public function __set(string $id, $object)
	{
		$this->set($id, $object);
	}

	public function __get(string $id)
	{
		return $this->get($id);
	}

	public function set(string $id, $object): self
	{
		if ($object instanceof Closure) {

			$this->factories[$id] = $object;
			unset($this->entries[$id]);
		} else {
			$this->entries[$id] = $object;
		}

		return $this;
	}

	public function get($id)
	{
		if (isset($this->entries[$id]) || array_key_exists($id, $this->entries)) {

			return $this->entries[$id];
		}

		if (isset($this->factories[$id])) {

			$this->entries[$id] = $this->resolve($id);

			return $this->entries[$id];
		}

		if ($this->canCreate($id)) {

			$this->factories[$id] = $this->getClassFactory($id);
			$this->entries[$id] = $this->resolve($id);

			return $this->entries[$id];
		}

		throw new NotFoundException("Entry for < $id > could not be resolved");
	}

	protected function resolve(string $id)
	{
		if (isset($this->resolving[$id])) {
			throw new ContainerException("Circular reference detected for < $id >");
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
			throw new ContainerException("Unable to create < $name >, not instantiable");
		}

		$constructor = $class->getConstructor();

		try {
			$args = $constructor ? $this->getFunctionParams($constructor) : [];
		} catch (ParameterResolveException $e) {
			throw new ContainerException($e->getMessage() . " of < $name >");
		}

		return function () use ($name, $args) {
			return new $name(...$args);
			// return $class->newInstanceArgs($args);
		};
	}

	protected function getFunctionParams(ReflectionFunctionAbstract $function): array
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
				throw new ParameterResolveException("Unable to resolve < $pname > for < $fname >");
			}
		}

		return $args;
	}

	protected function canCreate(string $class): bool
	{
		return class_exists($class);
	}

	public function has($id): bool
	{
		if (
			isset($this->entries[$id]) || array_key_exists($id, $this->entries) ||
			isset($this->factories[$id]) || $this->canCreate($id)
		) {
			return true;
		}

		return false;
	}

	public function listEntries(): array
	{
		return array_keys($this->entries);
	}

	public function listFactories(): array
	{
		return array_keys($this->factories);
	}
}
