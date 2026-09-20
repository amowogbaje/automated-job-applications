<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_index_page_loads(): void
    {
        $response = $this->get(route('tasks.index'));

        $response->assertOk();
        $response->assertViewIs('tasks.index');
    }

    public function test_a_task_can_be_created_and_is_appended_to_the_end_of_the_list(): void
    {
        Task::create(['name' => 'Existing task', 'priority' => 1]);

        $response = $this->post(route('tasks.store'), [
            'name' => 'New task',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'name' => 'New task',
            'priority' => 2,
            'project_id' => null,
        ]);
    }

    public function test_a_task_can_be_updated(): void
    {
        $task = Task::create(['name' => 'Original name', 'priority' => 1]);

        $this->put(route('tasks.update', $task), [
            'name' => 'Updated name',
        ])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Updated name',
        ]);
    }

    public function test_a_task_can_be_deleted_and_remaining_priorities_are_resequenced(): void
    {
        $first = Task::create(['name' => 'First', 'priority' => 1]);
        $second = Task::create(['name' => 'Second', 'priority' => 2]);
        $third = Task::create(['name' => 'Third', 'priority' => 3]);

        $this->delete(route('tasks.destroy', $second))->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $second->id]);
        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'priority' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $third->id, 'priority' => 2]);
    }

    public function test_tasks_can_be_reordered_via_drag_and_drop_endpoint(): void
    {
        $first = Task::create(['name' => 'First', 'priority' => 1]);
        $second = Task::create(['name' => 'Second', 'priority' => 2]);
        $third = Task::create(['name' => 'Third', 'priority' => 3]);

        // Simulate dragging "Third" to the top of the list.
        $response = $this->postJson(route('tasks.reorder'), [
            'task_ids' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $third->id, 'priority' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'priority' => 2]);
        $this->assertDatabaseHas('tasks', ['id' => $second->id, 'priority' => 3]);
    }

    public function test_tasks_can_be_filtered_by_project(): void
    {
        $projectOne = Project::create(['name' => 'Project One']);
        $projectTwo = Project::create(['name' => 'Project Two']);

        Task::create(['name' => 'In project one', 'priority' => 1, 'project_id' => $projectOne->id]);
        Task::create(['name' => 'In project two', 'priority' => 1, 'project_id' => $projectTwo->id]);

        $response = $this->get(route('tasks.index', ['project_id' => $projectOne->id]));

        $response->assertOk();
        $response->assertSee('In project one');
        $response->assertDontSee('In project two');
    }
}
