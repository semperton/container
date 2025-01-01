<div align="center">
<a href="https://github.com/semperton">
<img width="140" src="https://raw.githubusercontent.com/semperton/.github/main/readme-logo.svg" alt="Semperton">
</a>
<h1>Semperton Container</h1>
<p>A lightweight PSR-11 dependency injection container<br>with reflection based autowiring.</p>
</div>

---

## Installation

Just use Composer:

```
composer require semperton/container
```
Container requires PHP 8.0+

## Interface

```php
new Container(iterable $definitions = [], bool $autowire = true)
```

The container ships with four public methods:

```php
with(string $id, $entry): Container // add a container entry
get(string $id) // get entry (PSR-11)
has(string $id): bool // has entry (PSR-11)
create(string $id, array $params = []); // create a class with optional constructor substitution args
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
$hello2 = $container->get(Hello::class);

$hello instanceof Hello::class // true
$hello === $hello2 // true
$hello->print(); // 'Hello World'
```

Note that the container only creates instances once. It does not work as a factory.
You should consider the [Factory Pattern](https://designpatternsphp.readthedocs.io/en/latest/Creational/SimpleFactory/README.html) or use the ```create()``` method instead:

```php
use Semperton\Container\Container;

class Mail
{
	public function __construct(Config $c, string $to)
	{
	}
}

class MailFactory
{
	public function createMail(string $to)
	{
		return new Mail(new Config(), $to);
	}
}

$mail1 = $container->get(MailFactory::class)->createMail('info@example.com');
$mail2 = $container->create(Mail::class, ['to' =>'info@example.com']);

```
The ```create()``` method will automatically resolve the ```Config``` dependency for ```Mail```.

## Configuration

You can configure the container with definitions. ```Callables``` (except invokable objects) are always treated as factories and can (!should) be used to bootstrap class instances:

```php
use Semperton\Container\Container;

$container = new Container([

	'mail' => 'local@host.local',
	'closure' => static function () { // closures must be wrapped in another closure
		return static function () {
			return 42;
		};
	},
	MailFactory::class => new MailFactory('local@host.local'), // avoid this, instead do
	MailFactory::class => static function (Container $c) { // lazy instantiation with a factory

		$sender = $c->get('mail');
		return new MailFactory($sender);
	}, // or
	// factory params are automatically resolved from the container
	MailFactory::class => static fn (string $mail) => new MailFactory($mail),
	Service::class => static fn (Dependency $dep) => new Service($dep)
]);

$container->get('mail'); // 'local@host.local'
$container->get('closure')(); // 42
$container->get(MailFactory::class); // instance of MailFactory
```

The ```with()``` method also treats ```callables``` as factories.

## Immutability

Once the container is created, it is immutable. If you like to add an entry after instantiation, keep in mind that the ```with()``` method always returns a new container instance:

```php
use Semperton\Container\Container;

$container1 = new Container();
$container2 = $container1->with('number', 42);

$container1->has('number'); // false
$container2->has('number'); // true

$container1 === $container2 // false
```
