<?php

declare(strict_types=1);

use support\Container;

/**
 * Resolve a typed service from the DI container.
 *
 * @template T of object
 * @param class-string<T> $class
 * @return T
 */
function resolve(string $class): object
{
    $instance = Container::get($class);
    if (!$instance instanceof $class) {
        throw new \RuntimeException("Container did not return an instance of {$class}");
    }
    return $instance;
}
