<?php

use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\Transaction;

test('member can create income source', function () {
    $this->actingAsFamilyMember('member');

    $this->postJson('/api/v1/income-sources', ['name' => 'Gaji Bulanan'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Gaji Bulanan');
});

test('delete blocked with 409 when referenced by transaction', function () {
    [, $family] = $this->actingAsFamilyMember('admin');
    $source = IncomeSource::factory()->for($family)->create();
    Transaction::factory()->income()->for($family)->create(['source_id' => $source->id]);

    $this->deleteJson('/api/v1/income-sources/'.$source->id)->assertStatus(409);
});

test('tenant leak cannot view other familys income source', function () {
    $this->actingAsFamilyMember('admin');
    $other = IncomeSource::factory()->for(Family::factory())->create();

    $this->getJson('/api/v1/income-sources/'.$other->id)->assertStatus(404);
});
