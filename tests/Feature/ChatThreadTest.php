<?php

namespace Tests\Feature;

use App\Models\ChatThread;
use App\Models\Family;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class ChatThreadTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_store_sets_member_id_from_current_member_not_body(): void
    {
        [, $family, $member] = $this->actingAsFamilyMember('member');

        $response = $this->postJson('/api/v1/chat-threads', [
            'title' => 'Tanya Amina',
            'member_id' => 'not-a-real-id',
        ])->assertCreated();

        $response->assertJsonPath('data.member_id', $member->id);
        $response->assertJsonPath('data.kind', 'general');
    }

    public function test_tenant_leak_cannot_view_other_familys_thread(): void
    {
        $this->actingAsFamilyMember('admin');
        $other = ChatThread::factory()->for(Family::factory())->create();

        $this->getJson('/api/v1/chat-threads/'.$other->id)->assertStatus(404);
    }
}
