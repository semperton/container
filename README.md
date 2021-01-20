<div align="center">
<a href="https://github.com/semperton">
<img src="https://avatars0.githubusercontent.com/u/76976189?s=140" alt="Semperton" />
</a>
<h1>Semperton Container</h1>
<h4>A lightweight PSR-11 container implementation with reflection based autowiring.</h4>
</div>

<hr/>

## Installation

Just use Composer:

```
composer require semperton/container
```
Container requires PHP 7.1+

## Interface

The container ships with five public methods:

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

class SimpleClass{
	public function __construct(stdclass $obj, int $num = 1){}
}

$container = new Container();
$simple = $container->get(SimpleClass::class);

$simple instanceof SimpleClass::class // true
```

Note that the container only creates instances once. It does not work as a factory. You should consider the Factory Pattern instead:

```php
use Semperton\Container\Container;

class Mail{
	public function send(){}
}

class MailFactory{
	public function createMail(){
		return new Mail();
	}
}

$container = new Container();
$factory = $container->get(MailFactory::class);

$mail1 = $factory->createMail();
$mail2 = $factory->createMail();

$mail1 === $mail2 // false
```

## Configuration

You can configure the container with definitions. Closures are always treated as factories and can (!should) be used to bootstrap class instances:

```php
use Semperton\Container\Container;

$container = new Container([

	'mail' => 'local@host.local',
	'closure' => function(){
		return function(){
			return 42;
		};
	},
	MailFactory::class => function(Container $c){

		$sender = $c->get('mail');
		return new MailFactory($sender);
	}
]);

$container->get('mail'); // 'local@host.local'
$container->get('closure')(); // 42
$container->get(MailFactory::class); // instance of MailFactory
```

## Immutability

Once the container ist created, it is immutable. If you like to add an entry after instantiation, remember that the ```with()``` method always returns a new container instance:

```php
use Semperton\Container\Container;

$container1 = new Container();
$container2 = $container1->with('number', 42);

$container1->has('number'); // false
$container2->has('number'); // true

$container1 === $container2 // false
```