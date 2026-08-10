<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class IncomeSourceTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_member_can_create_income_source(): void
    {
        $this->actingAsFamilyMember('member');

        $this->postJson('/api/v1/income-sources', ['name' => 'Gaji Bulanan'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Gaji Bulanan');
    }

    public function test_delete_blocked_with_409_when_referenced_by_transaction(): void
    {
        [, $family] = $this->actingAsFamilyMember('admin');
        $source = IncomeSource::factory()->for($family)->create();
        Transaction::factory()->income()->for($family)->create(['source_id' => $source->id]);

        $this->deleteJson('/api/v1/income-sources/'.$source->id)->assertStatus(409);
    }

    public function test_tenant_leak_cannot_view_other_familys_income_source(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = IncomeSource::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/income-sources/'.$other->id)->assertStatus(404);
    }
}
