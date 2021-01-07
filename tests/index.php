<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Semperton\Container\Container;

require_once __DIR__ . '/../vendor/autoload.php';

$container = new Container();

class A
{
	public $name;
	public function __construct(string $name)
	{
		$this->name = $name;
	}
}

class B
{
	protected $a;
	public function __construct(A $a)
	{
		$this->a = $a;
	}
	public function hello()
	{
		echo $this->a->name;
	}
}

// $container->{A::class} = new A('test');
$container->name = 'Semperton';
$container->{A::class} = function (ContainerInterface $c) {
	$name = $c->get('name');
	return new A($name);
};

$container->get(B::class)->hello();
