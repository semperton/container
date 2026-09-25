<?php

declare(strict_types=1);

namespace Semperton\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Semperton\Container\Exception\NotFoundException;
use Semperton\Container\Exception\ParameterResolveException;
use Semperton\Container\Exception\CircularReferenceException;
use Semperton\Container\Exception\DependencyException;
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
use function array_push;
use function array_unique;
use function array_values;
use function implode;
use function is_array;
use function sort;

final class Container implements ContainerInterface, FactoryInterface
{
	/**
	 * @var array<string, mixed>
	 */
	private array $entries = [];

	/**
	 * @var array<string, Closure>
	 */
	private array $factories = [];

	/**
	 * @var array<string, mixed>
	 */
	private array $resolved = [];

	/**
	 * @var array<string, true>
	 */
	private array $resolving = [];

	/**
	 * @var array<string, list<ReflectionParameter>>
	 */
	private array $params = [];

	private bool $autowire = false;

	private ?ContainerInterface $delegate = null;

	/**
	 * @param iterable<string, mixed> $definitions
	 */
	public function __construct(iterable $definitions = [])
	{
		/** @var mixed $entry */
		foreach ($definitions as $id => $entry) {
			$this->set($id, $entry);
		}
	}

	public function __clone()
	{
		// resolved instances depend on the configuration of the original container
		$this->resolved = [];
		$this->resolving = [];
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
		// resolved services are the hot path, both arrays never share an id
		if (array_key_exists($id, $this->resolved)) {
			return $this->resolved[$id];
		}

		if (array_key_exists($id, $this->entries)) {
			return $this->entries[$id];
		}

		if (isset($this->factories[$id])) {
			return $this->resolved[$id] = $this->create($id);
		}

		if ($this->isSelf($id)) {
			return $this;
		}

		if ($this->delegate?->has($id)) {
			return $this->delegate->get($id);
		}

		return $this->resolved[$id] = $this->create($id);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function create(string $id, array $params = []): mixed
	{
		if (isset($this->resolving[$id])) {
			$path = implode(' -> ', [...array_keys($this->resolving), $id]);
			throw new CircularReferenceException("Circular reference detected: $path");
		}

		$factory = $this->factories[$id] ?? null;
		$reflectedParams = $this->getParams($id, $factory);

		$this->resolving[$id] = true;

		try {
			$args = $this->resolveParams($reflectedParams, $params);

			if ($factory !== null) {
				return $factory(...$args);
			}

			/**
			 * @var class-string $id checked by getParams()
			 * @psalm-suppress MixedMethodCall constructor args are resolved via reflection
			 */
			return new $id(...$args);
		} catch (NotFoundExceptionInterface $e) {
			// < $id > itself is known, only one of its dependencies is missing (PSR-11)
			throw new DependencyException("Unable to resolve < $id >, a dependency could not be found: {$e->getMessage()}", 0, $e);
		} finally {
			unset($this->resolving[$id]);
		}
	}

	public function has(string $id): bool
	{
		return array_key_exists($id, $this->resolved)
			|| array_key_exists($id, $this->entries)
			|| isset($this->factories[$id])
			|| $this->isSelf($id)
			|| $this->delegate?->has($id) === true
			|| $this->canAutowire($id);
	}

	/**
	 * @return list<string>
	 */
	public function entries(): array
	{
		$combined = array_unique([
			self::class,
			ContainerInterface::class,
			...array_keys($this->entries),
			...array_keys($this->resolved),
			...array_keys($this->factories)
		]);

		sort($combined, SORT_NATURAL | SORT_FLAG_CASE);

		return $combined;
	}

	private function set(string $id, mixed $entry): void
	{
		unset($this->entries[$id], $this->factories[$id], $this->resolved[$id], $this->params[$id]);

		if ($entry instanceof Closure) {
			$this->factories[$id] = $entry;
		} else {
			$this->entries[$id] = $entry;
		}
	}

	/**
	 * Returns the params of the factory or constructor that builds < $id >
	 *
	 * @return list<ReflectionParameter>
	 */
	private function getParams(string $id, ?Closure $factory): array
	{
		if ($factory !== null) {
			return $this->params[$id] ??= (new ReflectionFunction($factory))->getParameters();
		}

		if ($this->canAutowire($id)) {
			return $this->params[$id];
		}

		if ($this->autowire && class_exists($id)) {
			throw new NotInstantiableException("Unable to create < $id >, not instantiable");
		}

		throw new NotFoundException("Entry, factory or class for < $id > could not be resolved");
	}

	/**
	 * Checks whether < $id > can be autowired and caches its constructor params.
	 * Only called for ids without a factory, so cached params always belong to a class.
	 */
	private function canAutowire(string $id): bool
	{
		if (!$this->autowire) {
			return false;
		}

		if (isset($this->params[$id])) {
			return true;
		}

		if (!class_exists($id)) {
			return false;
		}

		$class = new ReflectionClass($id);

		if (!$class->isInstantiable()) {
			return false;
		}

		$this->params[$id] = $class->getConstructor()?->getParameters() ?? [];
		return true;
	}

	/**
	 * @param list<ReflectionParameter> $params
	 * @param array<string, mixed> $replace
	 * @return list<mixed>
	 */
	private function resolveParams(array $params, array $replace): array
	{
		$args = [];

		foreach ($params as $param) {
			$name = $param->getName();

			// variadic params are always last, they only receive explicitly passed values
			if ($param->isVariadic()) {
				$values = array_key_exists($name, $replace) ? $replace[$name] : [];

				if (!is_array($values)) {
					throw new ParameterResolveException("Unable to resolve variadic param < \$$name >, value must be an array");
				}

				array_push($args, ...array_values($values));
				break;
			}

			if (array_key_exists($name, $replace)) {
				/** @var mixed */
				$args[] = $replace[$name];
				continue;
			}

			$type = $param->getType();

			// union / intersection types are ambiguous, they must be configured explicitly
			if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
				$className = $type->getName();

				// shortcut for already resolved services, avoids has() + get()
				if (array_key_exists($className, $this->resolved)) {
					/** @var mixed */
					$args[] = $this->resolved[$className];
					continue;
				}

				if ($this->has($className)) {
					/** @var mixed */
					$args[] = $this->get($className);
					continue;
				}
			}

			if ($param->isDefaultValueAvailable()) {
				/** @var mixed */
				$args[] = $param->getDefaultValue();
				continue;
			}

			throw $this->unresolvableParam($param);
		}

		return $args;
	}

	private function unresolvableParam(ReflectionParameter $param): ParameterResolveException
	{
		$functionName = $param->getDeclaringFunction()->getName();
		$className = $param->getDeclaringClass()?->getName();
		$type = $param->getType();

		$message = "Unable to resolve param < \${$param->getName()} > for < $functionName >";

		if ($className !== null) {
			$message .= " of < $className >";
		}

		if ($type !== null && !$type instanceof ReflectionNamedType) {
			$message .= ", union / intersection types are not autowired, use a factory or pass the param explicitly";
		}

		return new ParameterResolveException($message);
	}

	private function isSelf(string $id): bool
	{
		return $id === self::class || $id === ContainerInterface::class;
	}
}
