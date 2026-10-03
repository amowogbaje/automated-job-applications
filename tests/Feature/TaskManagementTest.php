<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The task board is a reactive page backed by JSON endpoints, scoped per user
 * (tasks used to be shared by every account).
 */
class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function task(array $attrs = []): Task
    {
        return Task::factory()->create(['user_id' => $this->user->id] + $attrs);
    }

    public function test_guests_are_sent_to_login(): void
    {
        auth()->logout();
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
        $this->postJson(route('tasks.store'), ['name' => 'x'])->assertUnauthorized();
    }

    public function test_the_page_embeds_only_my_tasks_and_projects(): void
    {
        $mine = $this->task(['name' => 'My secret plan']);
        $other = Task::factory()->create(['name' => 'Someone elses plan']);
        Project::factory()->create(['user_id' => $this->user->id, 'name' => 'Mine']);
        Project::factory()->create(['name' => 'Not mine']);

        $this->get(route('tasks.index'))
            ->assertOk()
            ->assertViewIs('tasks.index')
            ->assertSee('My secret plan')
            ->assertSee('Mine')
            ->assertDontSee('Someone elses plan')
            ->assertDontSee('Not mine');
    }

    public function test_a_task_is_created_and_appended_to_the_end(): void
    {
        $this->task(['priority' => 1]);

        $this->postJson(route('tasks.store'), ['name' => '  New   task '])
            ->assertCreated()
            ->assertJsonPath('task.name', 'New task')
            ->assertJsonPath('task.priority', 2)
            ->assertJsonPath('task.completed', false);

        $this->assertDatabaseHas('tasks', ['user_id' => $this->user->id, 'name' => 'New task', 'priority' => 2]);
    }

    public function test_a_task_cannot_be_put_in_someone_elses_project(): void
    {
        $theirs = Project::factory()->create();

        $this->postJson(route('tasks.store'), ['name' => 'x', 'project_id' => $theirs->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('project_id');
    }

    public function test_a_task_can_be_renamed_completed_and_uncompleted(): void
    {
        $task = $this->task(['name' => 'Original']);

        $this->patchJson(route('tasks.update', $task), ['name' => 'Updated'])->assertOk()->assertJsonPath('task.name', 'Updated');

        $this->patchJson(route('tasks.update', $task), ['completed' => true])
            ->assertOk()->assertJsonPath('task.completed', true);
        $this->assertNotNull($task->fresh()->completed_at);

        $this->patchJson(route('tasks.update', $task), ['completed' => false])->assertOk()->assertJsonPath('task.completed', false);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_moving_a_task_to_another_project_sends_it_to_the_bottom(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $first = $this->task(['priority' => 1]);
        $this->task(['priority' => 2]);

        $this->patchJson(route('tasks.update', $first), ['project_id' => $project->id])
            ->assertOk()->assertJsonPath('task.project_id', $project->id)->assertJsonPath('task.priority', 3);
    }

    public function test_deleting_a_task_resequences_and_can_be_undone(): void
    {
        $first = $this->task(['priority' => 1]);
        $second = $this->task(['priority' => 2]);
        $third = $this->task(['priority' => 3]);

        $this->deleteJson(route('tasks.destroy', $second))->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $second->id]);
        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'priority' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $third->id, 'priority' => 2]);

        $this->postJson(route('tasks.restore', $second->id))->assertOk()->assertJsonPath('task.id', $second->id);
        $this->assertNotSoftDeleted('tasks', ['id' => $second->id]);
    }

    public function test_tasks_can_be_reordered(): void
    {
        $first = $this->task(['priority' => 1]);
        $second = $this->task(['priority' => 2]);
        $third = $this->task(['priority' => 3]);

        $this->postJson(route('tasks.reorder'), ['task_ids' => [$third->id, $first->id, $second->id]])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $third->id, 'priority' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'priority' => 2]);
        $this->assertDatabaseHas('tasks', ['id' => $second->id, 'priority' => 3]);
    }

    public function test_completed_tasks_can_be_cleared(): void
    {
        $open = $this->task(['priority' => 1]);
        $done = $this->task(['priority' => 2, 'completed_at' => now()]);

        $this->deleteJson(route('tasks.clearCompleted'))->assertOk()->assertJsonPath('ids.0', $done->id);

        $this->assertSoftDeleted('tasks', ['id' => $done->id]);
        $this->assertNotSoftDeleted('tasks', ['id' => $open->id]);
    }

    public function test_other_accounts_tasks_are_untouchable(): void
    {
        $theirs = Task::factory()->create(['priority' => 1]);

        $this->patchJson(route('tasks.update', $theirs), ['name' => 'hijacked'])->assertNotFound();
        $this->deleteJson(route('tasks.destroy', $theirs))->assertNotFound();
        $this->postJson(route('tasks.restore', $theirs->id))->assertNotFound();
        $this->postJson(route('tasks.reorder'), ['task_ids' => [$theirs->id]])->assertStatus(422);

        $this->assertDatabaseHas('tasks', ['id' => $theirs->id, 'name' => $theirs->name]);
    }

    public function test_projects_can_be_created_renamed_and_deleted_keeping_their_tasks(): void
    {
        $id = $this->postJson(route('projects.store'), ['name' => 'Launch'])->assertCreated()->json('project.id');

        $this->postJson(route('projects.store'), ['name' => 'Launch'])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->patchJson(route('projects.update', $id), ['name' => 'Relaunch'])->assertOk()->assertJsonPath('project.name', 'Relaunch');

        $task = $this->task(['project_id' => $id]);
        $this->deleteJson(route('projects.destroy', $id))->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => null]);
    }

    public function test_two_accounts_can_use_the_same_project_name(): void
    {
        Project::factory()->create(['name' => 'Inbox']); // someone else's

        $this->postJson(route('projects.store'), ['name' => 'Inbox'])->assertCreated();
    }

    public function test_other_accounts_projects_are_untouchable(): void
    {
        $theirs = Project::factory()->create();

        $this->patchJson(route('projects.update', $theirs), ['name' => 'mine now'])->assertNotFound();
        $this->deleteJson(route('projects.destroy', $theirs))->assertNotFound();
    }
}
