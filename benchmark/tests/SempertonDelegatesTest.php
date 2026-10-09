<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Test;

use DI\ContainerBuilder as PhpDiBuilder;
use League\Container\Container as LeagueContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Semperton\Container\Benchmark\Fixture\Interop\Logger;
use Semperton\Container\Benchmark\Fixture\Interop\Mailer;
use Semperton\Container\Benchmark\Fixture\Interop\MemoryLogger;
use Semperton\Container\Container;
use Semperton\Container\Exception\DependencyException;
use Semperton\Container\Exception\NotFoundException;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyBuilder;

use function DI\create;

/**
 * Semperton delegates lookups to another PSR-11 container, that only holds the Logger binding
 */
final class SempertonDelegatesTest extends TestCase
{
	/**
	 * @return iterable<string, array{ContainerInterface}>
	 */
	public static function delegateProvider(): iterable
	{
		yield 'php-di' => [
			(new PhpDiBuilder())
				->useAutowiring(false)
				->addDefinitions([Logger::class => create(MemoryLogger::class)])
				->build()
		];

		$symfony = new SymfonyBuilder();
		$symfony->register(MemoryLogger::class)->setPublic(true);
		$symfony->setAlias(Logger::class, MemoryLogger::class)->setPublic(true);
		$symfony->compile();

		yield 'symfony' => [$symfony];

		$league = new LeagueContainer();
		$league->addShared(Logger::class, MemoryLogger::class);

		yield 'league' => [$league];
	}

	#[DataProvider('delegateProvider')]
	public function testGetFromDelegate(ContainerInterface $delegate): void
	{
		$container = (new Container())->withDelegate($delegate);

		$this->assertTrue($container->has(Logger::class));
		$this->assertSame($delegate->get(Logger::class), $container->get(Logger::class));
	}

	#[DataProvider('delegateProvider')]
	public function testAutowiredDependencyFromDelegate(ContainerInterface $delegate): void
	{
		$container = (new Container())->withAutowiring(true)->withDelegate($delegate);

		$this->assertFalse($delegate->has(Mailer::class));
		$this->assertTrue($container->has(Mailer::class));

		$mailer = $container->get(Mailer::class);

		$this->assertInstanceOf(Mailer::class, $mailer);
		$this->assertSame($delegate->get(Logger::class), $mailer->logger);
	}

	#[DataProvider('delegateProvider')]
	public function testFactoryDependencyFromDelegate(ContainerInterface $delegate): void
	{
		$container = (new Container([
			Mailer::class => static fn(Logger $logger) => new Mailer($logger)
		]))->withDelegate($delegate);

		$this->assertSame($delegate->get(Logger::class), $container->get(Mailer::class)->logger);
	}

	#[DataProvider('delegateProvider')]
	public function testLocalEntryWins(ContainerInterface $delegate): void
	{
		$logger = new MemoryLogger();
		$container = (new Container([Logger::class => $logger]))->withDelegate($delegate);

		$this->assertSame($logger, $container->get(Logger::class));
		$this->assertNotSame($delegate->get(Logger::class), $container->get(Logger::class));
	}

	#[DataProvider('delegateProvider')]
	public function testUnknownIdThrowsNotFound(ContainerInterface $delegate): void
	{
		$container = (new Container())->withAutowiring(true)->withDelegate($delegate);

		$this->assertFalse($container->has('unknown'));

		$this->expectException(NotFoundException::class);
		$container->get('unknown');
	}

	#[DataProvider('delegateProvider')]
	public function testMissingDependencyIsWrapped(ContainerInterface $delegate): void
	{
		$container = (new Container([
			Mailer::class => static fn(ContainerInterface $c) => new Mailer($c->get('unknown'))
		]))->withDelegate($delegate);

		try {
			$container->get(Mailer::class);
			$this->fail('Expected DependencyException');
		} catch (DependencyException $e) {
			$this->assertInstanceOf(NotFoundExceptionInterface::class, $e->getPrevious());
		}
	}
}
