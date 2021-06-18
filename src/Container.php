<?php

declare(strict_types=1);

namespace Semperton\Container;

use Psr\Container\ContainerInterface;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\ParameterResolveException;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Exception\NotInstantiableException;
use ReflectionFunctionAbstract;
use ReflectionFunction;
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
	 * @var array<string, mixed>
	 */
	protected $definitions = [];

	/**
	 * @var array<string, Closure>
	 */
	protected $factories = [];

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

		// register self
		$this->definitions[self::class] = $this;
		$this->definitions[ContainerInterface::class] = $this;

		/** @var mixed $entry */
		foreach ($definitions as $id => $entry) {
			$this->definitions[$id] = $entry;
		}
	}

	/**
	 * @param mixed $entry
	 */
	protected function set(string $id, $entry): void
	{
		unset($this->factories[$id], $this->entries[$id]);

		$this->definitions[$id] = $entry;
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

		$this->entries[$id] = $this->create($id);

		return $this->entries[$id];
	}

	/**
	 * @return mixed
	 */
	public function create(string $id)
	{
		if (isset($this->factories[$id])) {

			return $this->resolve($id);
		}

		if (isset($this->definitions[$id]) || array_key_exists($id, $this->definitions)) {

			if (is_callable($this->definitions[$id])) {

				$this->factories[$id] = $this->getClosureFactory($this->definitions[$id]);

				return $this->resolve($id);
			}

			return $this->definitions[$id];
		}

		if ($this->autowire && $this->canCreate($id)) {

			$this->factories[$id] = $this->getClassFactory($id);

			return $this->resolve($id);
		}

		throw new NotFoundException("Entry for < $id > could not be resolved");
	}

	/**
	 * @return mixed
	 */
	protected function resolve(string $id)
	{
		if (isset($this->resolving[$id])) {
			throw new CircularReferenceException("Circular reference detected for < $id >");
		}

		$this->resolving[$id] = true;

		/** @var mixed */
		$entry = $this->factories[$id]();

		unset($this->resolving[$id]);

		return $entry;
	}

	protected function getClosureFactory(callable $callable): Closure
	{
		$closure = Closure::fromCallable($callable);

		$func = new ReflectionFunction($closure);

		$args = $this->getFunctionArgs($func);

		return
			/** @return mixed */
			function () use ($func, $args) {
				return $func->invokeArgs($args);
			};
	}

	protected function getClassFactory(string $name): Closure
	{
		/**
		 * @psalm-suppress ArgumentTypeCoercion
		 */
		$class = new ReflectionClass($name);

		if (!$class->isInstantiable()) {
			throw new NotInstantiableException("Unable to create < $name >, not instantiable");
		}

		$constructor = $class->getConstructor();

		$args = $constructor ? $this->getFunctionArgs($constructor) : [];

		return function () use ($class, $args) {
			return $class->newInstanceArgs($args);
		};
	}

	/**
	 * @return array<int, mixed>
	 */
	protected function getFunctionArgs(ReflectionFunctionAbstract $function): array
	{
		$params = $function->getParameters();

		$args = [];

		foreach ($params as $param) {

			/** @var null|ReflectionNamedType */
			$type = $param->getType();

			if ($type && !$type->isBuiltin()) {

				$className = $type->getName();

				/** @var mixed */
				$args[] = $this->get($className);
			} else if ($param->isOptional()) {

				/** @var mixed */
				$args[] = $param->getDefaultValue();
			} else {
				$paramName = $param->getName();
				$functionName = $function->getName();
				$ofClass = isset($function->class) ? " of < {$function->class} >" : '';
				throw new ParameterResolveException("Unable to resolve < \$$paramName > for < $functionName >" . $ofClass);
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
			isset($this->definitions[$id]) ||
			isset($this->factories[$id]) ||
			array_key_exists($id, $this->definitions) ||
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
		$definitions = array_keys($this->definitions);
		$factories = array_keys($this->factories);
		$combined = array_unique(array_merge($definitions, $factories));

		sort($combined, SORT_NATURAL | SORT_FLAG_CASE);

		return $combined;
	}
}
