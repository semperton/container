<?php

declare(strict_types=1);

namespace Semperton\Container\Exception;

use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;

class CreationFailException extends InvalidArgumentException implements ContainerExceptionInterface
{
}
