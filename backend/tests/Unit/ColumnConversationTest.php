<?php

namespace Tests\Unit;

use App\Enums\ColumnStatus;
use App\Models\ColumnConversation;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColumnConversationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::create([
            'session_id' => 'test-session-123',
            'initial_prompt' => 'Test prompt',
        ]);
    }

    #[Test]
    public function column_creates_with_required_fields(): void
    {
        $column = ColumnConversation::create([
            'workspace_id' => $this->workspace->id,
            'model_code' => 'xai',
            'position' => 1,
        ]);

        $this->assertDatabaseHas('column_conversations', [
            'id' => $column->id,
            'workspace_id' => $this->workspace->id,
            'model_code' => 'xai',
            'position' => 1,
        ]);
    }

    #[Test]
    public function column_position_is_unique_within_workspace(): void
    {
        ColumnConversation::create([
            'workspace_id' => $this->workspace->id,
            'model_code' => 'xai',
            'position' => 1,
        ]);

        $this->expectException(QueryException::class);

        ColumnConversation::create([
            'workspace_id' => $this->workspace->id,
            'model_code' => 'google',
            'position' => 1,
        ]);
    }

    #[Test]
    public function column_position_range_is_1_to_4(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            ColumnConversation::create([
                'workspace_id' => $this->workspace->id,
                'model_code' => "model_$i",
                'position' => $i,
            ]);
        }

        $this->assertEquals(4, $this->workspace->columns()->count());
    }

    #[Test]
    public function column_default_status_is_idle(): void
    {
        $column = ColumnConversation::create([
            'workspace_id' => $this->workspace->id,
            'model_code' => 'xai',
            'position' => 1,
        ]);

        $this->assertEquals(ColumnStatus::IDLE, $column->status);
    }

    #[Test]
    public function column_belongs_to_workspace(): void
    {
        $column = ColumnConversation::create([
            'workspace_id' => $this->workspace->id,
            'model_code' => 'xai',
            'position' => 1,
        ]);

        $this->assertEquals($this->workspace->id, $column->workspace->id);
    }
}
