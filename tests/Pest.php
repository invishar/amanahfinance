<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a
| specific PHPUnit test case class. By default, that class is this
| class, which extends the "PHPUnit\Framework\TestCase" class.
*/

uses(TestCase::class, RefreshDatabase::class, InteractsWithFamilies::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Tia Engine (Test Impact Analysis)
|--------------------------------------------------------------------------
|
| Not auto-enabled here: pass --tia explicitly (a PostToolUse hook does
| this after edits to app/**.php) to only re-run tests affected by the
| files that changed since the last baseline. Never use --tia in CI.
*/
