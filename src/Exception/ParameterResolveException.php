<?php

declare(strict_types=1);

namespace Semperton\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use Exception;

class ParameterResolveException extends Exception implements ContainerExceptionInterface
{
}
