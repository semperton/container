<?php

declare(strict_types=1);

namespace Semperton\Container;

use Psr\Container\ContainerInterface;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\ParameterResolveException;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Exception\NotInstantiableException;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionClass;
use Closure;

use const SORT_NATURAL;
use const SORT_FLAG_CASE;

use function class_exists;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function sort;

final class Container implements ContainerInterface, FactoryInterface
{
	/**
	 * @var array<string, Closure>
	 */
	protected array $factories = [];

	/**
	 * @var array<string, Closure>
	 */
	protected array $cache = [];

	/**
	 * @var array<string, mixed>
	 */
	protected array $entries = [];

	/**
	 * @var array<string, true>
	 */
	protected array $resolving = [];

	protected bool $autowire = true;

	protected ?ContainerInterface $delegate = null;

	/**
	 * @param iterable<string|class-string, mixed> $definitions
	 */
	public function __construct(iterable $definitions = [])
	{
		$this->set(self::class, $this);
		$this->set(ContainerInterface::class, $this);

		/** @var mixed $entry */
		foreach ($definitions as $id => $entry) {
			$this->set($id, $entry);
		}
	}

	/**
	 * @param mixed $entry
	 */
	protected function set(string $id, mixed $entry): void
	{
		unset($this->factories[$id], $this->cache[$id], $this->entries[$id]);

		if ($entry instanceof Closure) {
			$this->factories[$id] = $entry;
		} else {
			$this->entries[$id] = $entry;
		}
	}

	public function withAutowiring(bool $flag): Container
	{
		$container = clone $this;
		$container->autowire = $flag;
		return $container;
	}

	public function withDelegate(ContainerInterface $delegate): Container
	{
		$container = clone $this;
		$container->delegate = $delegate;
		return $container;
	}

	public function withEntry(string $id, mixed $entry): Container
	{
		$container = clone $this;
		$container->set($id, $entry);
		return $container;
	}

	public function get(string $id): mixed
	{
		if (isset($this->entries[$id]) || array_key_exists($id, $this->entries)) {
			return $this->entries[$id];
		}

		if (isset($this->factories[$id])) {
			$this->entries[$id] = $this->create($id);
			return $this->entries[$id];
		}

		if ($this->delegate?->has($id)) {
			return $this->delegate->get($id);
		}

		$this->entries[$id] = $this->create($id);
		return $this->entries[$id];
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function create(string $id, array $params = []): mixed
	{
		if (isset($this->cache[$id])) {
			return $this->resolve($id, $params);
		}

		if (isset($this->factories[$id])) {
			$this->cache[$id] = $this->getClosureFactory($this->factories[$id]);
			return $this->resolve($id, $params);
		}

		if ($this->canCreate($id)) {
			/** @var class-string $id */
			$this->cache[$id] = $this->getClassFactory($id);
			return $this->resolve($id, $params);
		}

		throw new NotFoundException("Entry, factory or class for < $id > could not be resolved");
	}

	protected function resolve(string $id, array $params): mixed
	{
		try {
			if (isset($this->resolving[$id])) {
				$entries = array_keys($this->resolving);
				$path = implode(' -> ', [...$entries, $id]);
				throw new CircularReferenceException("Circular reference detected: $path");
			}

			$this->resolving[$id] = true;

			/** @var mixed */
			$entry = $this->cache[$id]($params);
		} finally {
			unset($this->resolving[$id]);
		}

		return $entry;
	}

	protected function getClosureFactory(Closure $closure): Closure
	{
		$function = new ReflectionFunction($closure);
		$params = $function->getParameters();

		return function (array $args) use ($function, $params): mixed {
			$newArgs = $this->resolveFunctionParams($params, $args, true);
			return $function->invokeArgs($newArgs);
		};
	}

	/**
	 * @param class-string $name
	 */
	protected function getClassFactory(string $name): Closure
	{
		$class = new ReflectionClass($name);

		if (!$class->isInstantiable()) {
			throw new NotInstantiableException("Unable to create < $name >, not instantiable");
		}

		$constructor = $class->getConstructor();
		$params = $constructor?->getParameters() ?? [];

		return function (array $args) use ($class, $params) {
			$newArgs = $this->resolveFunctionParams($params, $args, false);
			return $class->newInstanceArgs($newArgs);
		};
	}

	/**
	 * @param array<array-key, ReflectionParameter> $params
	 * @return array<int, mixed>
	 */
	protected function resolveFunctionParams(array $params, array $replace, bool $allowNames): array
	{
		$args = [];

		foreach ($params as $param) {

			$paramName = $param->getName();

			if (isset($replace[$paramName]) || array_key_exists($paramName, $replace)) {
				/** @var mixed */
				$args[] = $replace[$paramName];
				continue;
			}

			$type = $param->getType();

			// we do not support union / intersection types for now
			if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
				$className = $type->getName();
				/** @var mixed */
				$args[] = $this->get($className);
				continue;
			}

			if ($allowNames && $this->has($paramName)) {
				/** @var mixed */
				$args[] = $this->get($paramName);
				continue;
			}

			if ($param->isOptional()) {
				/** @var mixed */
				$args[] =  $param->getDefaultValue();
				continue;
			}

			$function = $param->getDeclaringFunction();
			$functionName = $function->getName();
			/** @disregard P1014 Undefined type */
			$ofClass = isset($function->class) ? " of < {$function->class} >" : '';
			throw new ParameterResolveException("Unable to resolve param < \$$paramName > for < $functionName >" . $ofClass);
		}

		return $args;
	}

	protected function canCreate(string $name): bool
	{
		return $this->autowire && class_exists($name);
	}

	public function has(string $id): bool
	{
		if (
			isset($this->entries[$id]) ||
			isset($this->factories[$id]) ||
			isset($this->cache[$id]) ||
			array_key_exists($id, $this->entries)
		) {
			return true;
		}

		if ($this->delegate?->has($id)) {
			return true;
		}

		return $this->canCreate($id);
	}

	/**
	 * @return array<int, string>
	 */
	public function entries(): array
	{
		$entries = array_keys($this->entries);
		$factories = array_keys($this->factories);
		$combined = array_unique([...$entries, ...$factories]);

		sort($combined, SORT_NATURAL | SORT_FLAG_CASE);

		return $combined;
	}
}
