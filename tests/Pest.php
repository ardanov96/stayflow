<?php

declare(strict_types=1);
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Arch test groups
|--------------------------------------------------------------------------
|
| Place tests/Arch tests in their own group so the composer `ci` command can
| run them in isolation with --testsuite=Unit (see composer.json scripts.
|
*/

uses()->in('Arch');
