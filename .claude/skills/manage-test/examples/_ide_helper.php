<?php

/**
 * IDE stubs for Pest functions used in example test files.
 * Not loaded at runtime; only for Intelephense/IDE support.
 *
 * @see https://pestphp.com/
 */

if (! function_exists('uses')) {
    /** @param  class-string ...$classNames */
    function uses(string ...$classNames): void {}
}

if (! function_exists('beforeEach')) {
    function beforeEach(callable $callback): void {}
}

if (! function_exists('it')) {
    function it(string $description, callable $test): void {}
}

if (! function_exists('assertDatabaseHas')) {
    /** @param  array<string, mixed>  $data */
    function assertDatabaseHas(string $table, array $data, ?string $connection = null): void {}
}

if (! function_exists('assertDatabaseMissing')) {
    /** @param  array<string, mixed>  $data */
    function assertDatabaseMissing(string $table, array $data, ?string $connection = null): void {}
}

if (! function_exists('assertDatabaseCount')) {
    function assertDatabaseCount(string $table, int $count, ?string $connection = null): void {}
}
