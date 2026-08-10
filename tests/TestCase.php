<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * migrate:fresh only drops tables by default; v_wallet_month and
     * v_cashflow_month are views (2026_01_01_001800_create_reporting_views),
     * so without this the second test run fails with "already exists".
     */
    protected $dropViews = true;
}
