<?php

declare(strict_types=1);

namespace Semperton\Container\Benchmark\Test;

use DI\ContainerBuilder as PhpDiBuilder;
use League\Container\Container as LeagueContainer;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Semperton\Container\Benchmark\Fixture\Interop\Logger;
use Semperton\Container\Benchmark\Fixture\Interop\Mailer;
use Semperton\Container\Benchmark\Fixture\Interop\MemoryLogger;
use Semperton\Container\Container;

use function DI\get;

/**
 * Other containers delegate lookups to Semperton, that holds the Logger binding.
 * Symfony has no delegate lookup, so it can only be used as a delegate (see SempertonDelegatesTest).
 */
final class DelegatesToSempertonTest extends TestCase
{
	private Container $semperton;

	protected function setUp(): void
	{
		$this->semperton = new Container([
			Logger::class => static fn() => new MemoryLogger()
		]);
	}

	public function testLeagueGetsFromSemperton(): void
	{
		$league = new LeagueContainer();
		$league->delegate($this->semperton);

		$this->assertTrue($league->has(Logger::class));
		$this->assertSame($this->semperton->get(Logger::class), $league->get(Logger::class));
	}

	public function testLeagueArgumentFromSemperton(): void
	{
		$league = new LeagueContainer();
		$league->delegate($this->semperton);
		$league->add(Mailer::class)->addArgument(Logger::class);

		$this->assertSame($this->semperton->get(Logger::class), $league->get(Mailer::class)->logger);
	}

	public function testLeagueUnknownIdThrowsNotFound(): void
	{
		$league = new LeagueContainer();
		$league->delegate($this->semperton);

		$this->assertFalse($league->has('unknown'));

		$this->expectException(NotFoundExceptionInterface::class);
		$league->get('unknown');
	}

	public function testPhpDiAutowiresFromSemperton(): void
	{
		$phpDi = (new PhpDiBuilder())
			->useAutowiring(true)
			->wrapContainer($this->semperton)
			->build();

		$this->assertSame($this->semperton->get(Logger::class), $phpDi->get(Mailer::class)->logger);
	}

	public function testPhpDiReferenceFromSemperton(): void
	{
		$phpDi = (new PhpDiBuilder())
			->wrapContainer($this->semperton)
			->addDefinitions(['logger' => get(Logger::class)])
			->build();

		$this->assertSame($this->semperton->get(Logger::class), $phpDi->get('logger'));
	}

	public function testPhpDiUnknownIdThrowsNotFound(): void
	{
		$phpDi = (new PhpDiBuilder())
			->wrapContainer($this->semperton)
			->build();

		$this->assertFalse($phpDi->has('unknown'));

		$this->expectException(NotFoundExceptionInterface::class);
		$phpDi->get('unknown');
	}
}
