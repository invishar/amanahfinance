<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFamilies;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use InteractsWithFamilies, RefreshDatabase;

    public function test_store_forces_role_to_user_regardless_of_input(): void
    {
        [, $family, $member] = $this->actingAsFamilyMember('member');
        $thread = ChatThread::factory()->for($family)->for($member, 'member')->create();

        $response = $this->postJson("/api/v1/chat-threads/{$thread->id}/messages", [
            'content' => 'Halo Amina',
            'role' => 'assistant',
        ])->assertCreated();

        $response->assertJsonPath('data.role', 'user');
        $this->assertNotNull($thread->fresh()->last_message_at);
    }

    public function test_cannot_post_message_into_another_familys_thread(): void
    {
        $this->actingAsFamilyMember('member');
        $otherFamily = Family::factory()->create();
        $otherMember = FamilyMember::factory()->for($otherFamily)->create();
        $otherThread = ChatThread::factory()->for($otherFamily)->for($otherMember, 'member')->create();

        $this->postJson("/api/v1/chat-threads/{$otherThread->id}/messages", ['content' => 'hai'])
            ->assertStatus(404);
    }

    public function test_tenant_leak_on_shallow_show_route(): void
    {
        $this->actingAsFamilyMember('admin');
        $otherFamily = Family::factory()->create();
        $otherMember = FamilyMember::factory()->for($otherFamily)->create();
        $otherThread = ChatThread::factory()->for($otherFamily)->for($otherMember, 'member')->create();
        $otherMessage = ChatMessage::factory()->for($otherThread, 'thread')->create();

        $this->getJson('/api/v1/messages/'.$otherMessage->id)->assertStatus(403);
    }
}
