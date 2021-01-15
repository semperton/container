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
		foreach ($definitions as $name => $object) {
			$this->add($name, $object);
		}
	}

	protected function add(string $name, $object): void
	{
		unset($this->entries[$name], $this->factories[$name]);

		if ($object instanceof Closure) {

			$this->factories[$name] = $object;
		} else {
			$this->entries[$name] = $object;
		}
	}

	public function with(string $name, $object): Container
	{
		$container = clone $this;

		$container->add($name, $object);

		return $container;
	}

	public function get($name)
	{
		if (isset($this->entries[$name]) || array_key_exists($name, $this->entries)) {

			return $this->entries[$name];
		}

		if (isset($this->factories[$name])) {

			$this->entries[$name] = $this->resolve($name);

			return $this->entries[$name];
		}

		if ($this->canCreate($name)) {

			$this->factories[$name] = $this->getClassFactory($name);
			$this->entries[$name] = $this->resolve($name);

			return $this->entries[$name];
		}

		throw new NotFoundException("Entry for < $name > could not be resolved");
	}

	protected function resolve(string $name)
	{
		if (isset($this->resolving[$name])) {
			throw new ContainerException("Circular reference detected for < $name >");
		}

		$this->resolving[$name] = true;

		$object = $this->factories[$name]($this);

		unset($this->resolving[$name]);

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
			$args = $constructor ? $this->getFunctionArgs($constructor) : [];
		} catch (ParameterResolveException $e) {
			throw new ContainerException($e->getMessage() . " of < $name >");
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
				throw new ParameterResolveException("Unable to resolve < $pname > for < $fname >");
			}
		}

		return $args;
	}

	protected function canCreate(string $name): bool
	{
		return class_exists($name);
	}

	public function has($name): bool
	{
		if (
			isset($this->entries[$name]) || array_key_exists($name, $this->entries) ||
			isset($this->factories[$name]) || $this->canCreate($name)
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
