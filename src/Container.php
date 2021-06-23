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

use function is_callable;
use function class_exists;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_merge;
use function sort;

final class Container implements ContainerInterface
{
	/**
	 * @var array<string, callable>
	 */
	protected $factories = [];

	/**
	 * @var array<string, Closure>
	 */
	protected $cache = [];

	/**
	 * @var array<string, mixed>
	 */
	protected $entries = [];

	/**
	 * @var array<string, true>
	 */
	protected $resolving = [];

	/**
	 * @var bool
	 */
	protected $autowire;

	/**
	 * @param iterable<string, mixed> $definitions
	 */
	public function __construct(iterable $definitions = [], bool $autowire = true)
	{
		$this->autowire = $autowire;

		/** @var mixed $entry */
		foreach ($definitions as $id => $entry) {
			$this->set($id, $entry);
		}
	}

	/**
	 * @param mixed $entry
	 */
	protected function set(string $id, $entry): void
	{
		unset($this->factories[$id], $this->entries[$id]);

		if ($entry instanceof Closure || (is_callable($entry) && !is_object($entry))) {

			$this->factories[$id] = $entry;
		} else {
			$this->entries[$id] = $entry;
		}
	}

	/**
	 * @param mixed $entry
	 */
	public function with(string $id, $entry): Container
	{
		$container = clone $this;

		$container->set($id, $entry);

		return $container;
	}

	/**
	 * @return mixed
	 */
	public function get(string $id)
	{
		if (isset($this->entries[$id]) || array_key_exists($id, $this->entries)) {

			return $this->entries[$id];
		}

		if (isset($this->factories[$id])) {

			$factory = $this->getFactoryClosure($this->factories[$id]);

			$this->entries[$id] = $this->resolve($id, $factory);

			return $this->entries[$id];
		}

		if ($id === self::class || $id === ContainerInterface::class) {

			return $this;
		}

		if ($this->autowire) {

			$this->entries[$id] = $this->create($id);

			return $this->entries[$id];
		}

		throw new NotFoundException("Entry for < $id > could not be resolved");
	}

	/**
	 * @param array<string, mixed> $params
	 * @return mixed
	 */
	public function create(string $id, array $params = [])
	{
		if (isset($this->cache[$id])) {

			return $this->cache[$id]($params);
		}

		if ($this->canCreate($id)) {

			$this->cache[$id] = $this->getClassFactory($id);

			return $this->cache[$id]($params);
		}

		throw new NotFoundException("Class < $id > could not be resolved");
	}

	/**
	 * @return mixed
	 */
	protected function resolve(string $id, callable $func)
	{
		if (isset($this->resolving[$id])) {
			throw new CircularReferenceException("Circular reference detected for < $id >");
		}

		$this->resolving[$id] = true;

		/** @var mixed */
		$entry = $func();

		unset($this->resolving[$id]);

		return $entry;
	}

	protected function getFactoryClosure(callable $callable): Closure
	{
		$closure = Closure::fromCallable($callable);

		$function = new ReflectionFunction($closure);

		$params = $function->getParameters();
		$args = $params ? $this->resolveFunctionParams($params) : [];

		return
			/** @return mixed */
			static function () use ($function, $args) {

				return $function->invokeArgs($args);
			};
	}

	protected function getClassFactory(string $name): Closure
	{
		/** @psalm-suppress ArgumentTypeCoercion */
		$class = new ReflectionClass($name);

		if (!$class->isInstantiable()) {
			throw new NotInstantiableException("Unable to create < $name >, not instantiable");
		}

		$constructor = $class->getConstructor();
		$params = $constructor ? $constructor->getParameters() : [];

		return function (array $args) use ($class, $params) {

			/** @var array<int, mixed> */
			$newArgs = $params ? $this->resolveFunctionParams($params, $args) : [];

			return $class->newInstanceArgs($newArgs);
		};
	}

	/**
	 * @param array<array-key, ReflectionParameter> $params
	 */
	protected function resolveFunctionParams(array $params, array $replace = []): array
	{
		$args = [];

		foreach ($params as $param) {

			$paramName = $param->getName();

			if ($replace && (isset($replace[$paramName]) || array_key_exists($paramName, $replace))) {

				/** @var mixed */
				$args[] = $replace[$paramName];
				continue;
			}

			/** @var null|ReflectionNamedType */
			$type = $param->getType();

			if ($type && !$type->isBuiltin()) {

				$className = $type->getName();

				/** @var mixed */
				$args[] = $this->get($className);
				continue;
			}

			if ($this->has($paramName)) {

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
			$ofClass = isset($function->class) ? " of < {$function->class} >" : '';
			throw new ParameterResolveException("Unable to resolve < \$$paramName > for < $functionName >" . $ofClass);
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
			isset($this->entries[$id]) ||
			isset($this->factories[$id]) ||
			array_key_exists($id, $this->entries) ||
			$this->canCreate($id)
		) {
			return true;
		}

		return false;
	}

	/**
	 * @return array<int, string>
	 */
	public function entries(): array
	{
		$entries = array_keys($this->entries);
		$factories = array_keys($this->factories);
		$combined = array_unique(array_merge($entries, $factories));

		sort($combined, SORT_NATURAL | SORT_FLAG_CASE);

		return $combined;
	}
}
