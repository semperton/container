<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Semperton\Container\Container;

require_once __DIR__ . '/../vendor/autoload.php';

class A
{
	public $name;
	private $active;
	public function __construct(string $name, bool $active = true)
	{
		$this->name = $name;
		$this->active = $active;
	}
}

class B
{
	protected $salute;
	protected $a;
	public function __construct(string $salute, A $a)
	{
		$this->salute = $salute;
		$this->a = $a;
	}
	public function hello(): string
	{
		return $this->salute . ' ' . $this->a->name;
	}
}

class C
{
	protected $b;
	public function __construct(B $b)
	{
		$this->b = $b;
	}
	public function hello(): string
	{
		return $this->b->hello() . ' Container';
	}
}

$container = new Container([

	'name' => 'Semperton',
	A::class => function (ContainerInterface $c) {

		$name = $c->get('name');
		return new A($name);
	}
]);

$container = $container->with(B::class, function (ContainerInterface $c) {

	$a = $c->get(A::class);
	return new B('Hello', $a);
});

echo $container->get(C::class)->hello();
