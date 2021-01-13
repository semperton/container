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

	public function __construct(array $definitions = [])
	{
		$entry = new Entry();
		$entry->value = $this;
		$entry->isResolved = true;

		$this->entries[self::class] = $entry;
		$this->entries[ContainerInterface::class] = $entry;

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
		$resolved = ($object instanceof Closure) ? false : true;

		$entry = new Entry();
		$entry->value = $object;
		$entry->isResolved = $resolved;

		$this->entries[$id] = $entry;

		return $this;
	}

	public function get($id)
	{
		if (isset($this->entries[$id]) || array_key_exists($id, $this->entries)) {

			/** @var Entry */
			$entry = $this->entries[$id];

			if (!$entry->isResolved) {

				$entry->value = ($entry->value)($this);
				$entry->isResolved = true;
			}

			return $entry->value;
		}

		if ($this->canCreate($id)) {

			$instance = $this->create($id);

			$entry = new Entry();
			$entry->value = $instance;
			$entry->isResolved = true;

			$this->entries[$id] = $entry;

			return $instance;
		}

		throw new NotFoundException("Entry for '$id' could not be resolved");
	}

	protected function create(string $name)
	{
		$class = new ReflectionClass($name);

		if ($class->isInstantiable()) {

			$constructor = $class->getConstructor();

			try {
				$args = $constructor ? $this->getFunctionParams($constructor) : [];
			} catch (ParameterResolveException $e) {
				throw new ContainerException($e->getMessage() . " of \"$name\"");
			}

			return $class->newInstanceArgs($args);
		}

		throw new ContainerException("Unable to create '$name', missing or not instantiable");
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
				throw new ParameterResolveException("Unable to resolve '$pname' for '$fname'");
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
		if (isset($this->entries[$id]) || array_key_exists($id, $this->entries) || $this->canCreate($id)) {
			return true;
		}

		return false;
	}

	public function entries(): array
	{
		return array_keys($this->entries);
	}
}
