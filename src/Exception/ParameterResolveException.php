<?php

declare(strict_types=1);

namespace Semperton\Container\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ParameterResolveException extends Exception implements ContainerExceptionInterface
{
}
