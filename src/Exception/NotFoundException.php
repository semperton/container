<?php

declare(strict_types=1);

namespace Semperton\Container\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}
