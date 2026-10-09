# Semperton Container benchmarks

Performance comparison and PSR-11 interop tests against
[PHP-DI](https://php-di.org), [Symfony DependencyInjection](https://symfony.com/doc/current/components/dependency_injection.html)
and [League Container](https://container.thephpleague.com).

This is a separate Composer project. It loads the library straight from `../src`, so it always measures the working copy.

```
cd benchmark
composer install
composer bench  # PHPBench
composer test   # interop tests
```

Disable Xdebug / PCOV for meaningful numbers.

## Containers

All containers are configured to autowire the fixture classes as shared services.

| set               | setup                                                                  |
|-------------------|------------------------------------------------------------------------|
| `semperton`       | `withAutowiring(true)`                                                 |
| `php-di`          | runtime autowiring, attributes disabled                                |
| `php-di-compiled` | `enableCompilation()`, every fixture declared with `autowire()`        |
| `symfony`         | compiled and dumped container (build time is not measured)             |
| `league`          | `ReflectionContainer` delegate with cached resolutions                 |

Compiled containers are written to `var/` and rebuilt at the start of every bench run.

## Subjects

| subject           | measures                                                              |
|-------------------|-----------------------------------------------------------------------|
| `benchBootstrap`  | creating the container                                                |
| `benchColdChain`  | new container + resolving a linear graph 10 classes deep              |
| `benchColdTree`   | new container + resolving a fan-out graph of 9 classes, shared leaves |
| `benchWarmGet`    | `get()` of an already resolved service                                |
| `benchHasKnown`   | `has()` of an already resolved service                                |
| `benchHasUnknown` | `has()` of an id that is neither an entry nor a class                 |

Run a single subject or container with `--filter` / `--variant`:

```
vendor/bin/phpbench run --report=containers --filter=benchColdTree --variant=semperton
```

## Tracking regressions

Store a baseline before changing `../src` and compare against it afterwards:

```
vendor/bin/phpbench run --tag=before --variant=semperton
# change the library
vendor/bin/phpbench run --ref=before --variant=semperton --report=aggregate
```

## Interop tests

- `SempertonDelegatesTest`: Semperton uses PHP-DI, Symfony or League as `withDelegate()` container
- `DelegatesToSempertonTest`: League (`delegate()`) and PHP-DI (`wrapContainer()`) look up entries in Semperton.
  Symfony has no delegate lookup.
