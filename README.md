<div align="center">
<a href="https://github.com/semperton">
<img src="https://avatars0.githubusercontent.com/u/76976189?s=140" alt="Semperton">
</a>
<h1>Semperton Container</h1>
<h4>A lightweight PSR-11 container implementation<br>with reflection based autowiring.</h4>
</div>
<br>
<hr>

## Installation

Just use Composer:

```
composer require semperton/container
```
Container requires PHP 7.1+

## Interface

The container ships with four public methods:

```php
with(string $id, $value): Container // add a container entry
get(string $id) // get entry (PSR-11)
has(string $id): bool // has entry (PSR-11)
entries(): array // list all container entries
```

## Usage

Classes can be resolved automatically as long as they do not require any special configuration (autowiring).

```php
use Semperton\Container\Container;

class World
{
	public function __toString()
	{
		return 'World';
	}
}

class Hello
{
	protected $world;
	public function __construct(World $world)
	{
		$this->world = $world;
	}
	public function print()
	{
		echo "Hello {$this->world}";
	}
}

$container = new Container();
$hello = $container->get(Hello::class);

$hello instanceof Hello::class // true
$hello->print(); // 'Hello World'
```

Note that the container only creates instances once. It does not work as a factory. You should consider the Factory Pattern instead:

```php
use Semperton\Container\Container;

class Mail
{
}

class MailFactory
{
	public function createMail()
	{
		return new Mail();
	}
}

$container = new Container();
$factory1 = $container->get(MailFactory::class);
$factory2 = $container->get(MailFactory::class);

$factory1 === $factory2 // true

$mail1 = $factory1->createMail();
$mail2 = $factory1->createMail();

$mail1 === $mail2 // false
```

## Configuration

You can configure the container with definitions. Closures are always treated as factories and can (!should) be used to bootstrap class instances:

```php
use Semperton\Container\Container;

$container = new Container([

	'mail' => 'local@host.local',
	'closure' => function () {
		return function () {
			return 42;
		};
	},
	MailFactory::class => new MailFactory('local@host.local'), // avoid this, instead do
	MailFactory::class => function (Container $c) { // lazy instantiation with a factory

		$sender = $c->get('mail');
		return new MailFactory($sender);
	}
]);

$container->get('mail'); // 'local@host.local'
$container->get('closure')(); // 42
$container->get(MailFactory::class); // instance of MailFactory
```

## Immutability

Once the container ist created, it is immutable. If you like to add an entry after instantiation, keep in mind that the ```with()``` method always returns a new container instance:

```php
use Semperton\Container\Container;

$container1 = new Container();
$container2 = $container1->with('number', 42);

$container1->has('number'); // false
$container2->has('number'); // true

$container1 === $container2 // false
```
