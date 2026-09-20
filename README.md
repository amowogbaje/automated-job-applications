# Task Manager

A simple Laravel task management application. It supports creating, editing,
and deleting tasks, drag-and-drop reordering (which automatically updates
each task's priority), and organizing tasks into projects that can be
filtered from a dropdown.

## Features

- **Create / edit / delete tasks** — each task has a name, a priority, and
  standard `created_at` / `updated_at` timestamps.
- **Drag-and-drop reordering** — powered by [SortableJS](https://sortablejs.github.io/Sortable/).
  Dropping a task into a new position sends the full ordered list of task IDs
  to the server, which rewrites the `priority` column (1, 2, 3, ...) in a
  single database transaction. The task at the top of the list is always
  priority `#1`.
- **Projects (bonus)** — tasks can optionally belong to a project. A dropdown
  at the top of the page filters the list to a single project (or shows
  "All tasks"). New projects can be created inline without leaving the page.
  Reordering and priority numbering are scoped per project, so each
  project's list — and the "no project" list — has its own independent
  `#1, #2, #3...` ordering.

## Tech stack

- PHP **8.3**
- Laravel **11**
- MySQL
- Blade views styled with Tailwind CSS (via CDN — no Node/npm build step required)
- SortableJS (via CDN) for the drag-and-drop UI

No frontend build tooling (Vite/npm) is required to run this app — Tailwind
and SortableJS are loaded from a CDN in `resources/views/layouts/app.blade.php`.

## Project structure highlights

```
app/Http/Controllers/TaskController.php     # CRUD + reorder endpoint
app/Http/Controllers/ProjectController.php  # create projects
app/Http/Requests/                          # form validation
app/Models/Task.php, Project.php            # Eloquent models
database/migrations/                        # projects & tasks tables
database/seeders/DatabaseSeeder.php         # sample data
resources/views/tasks/index.blade.php       # the whole UI (list, forms, drag/drop JS)
routes/web.php                              # all app routes
tests/Feature/TaskManagementTest.php        # feature tests (CRUD, reorder, filtering)
```

## Requirements

- PHP >= 8.3 with the usual extensions Laravel needs (`pdo_mysql`, `mbstring`,
  `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- Composer 2.x
- MySQL 5.7+/8.x (or MariaDB)
- A local PHP web server — either PHP's built-in server (`php artisan serve`)
  or Apache/Nginx pointed at the `public/` directory

## Setup instructions

1. **Install PHP dependencies**

   ```bash
   composer install
   ```

2. **Create your environment file**

   ```bash
   cp .env.example .env
   ```

3. **Generate the application key**

   ```bash
   php artisan key:generate
   ```

4. **Create a MySQL database**, e.g.:

   ```sql
   CREATE DATABASE task_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. **Configure the database connection** in `.env`:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=task_manager
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. **Run the migrations** (add `--seed` to also load a few sample projects
   and tasks so the app isn't empty on first load):

   ```bash
   php artisan migrate --seed
   ```

7. **Serve the application**

   ```bash
   php artisan serve
   ```

   Visit **http://localhost:8000** in your browser. The root URL redirects
   to `/tasks`.

That's it — there's no `npm install` / asset build step needed.

## Running the tests

The app ships with a feature test suite covering task creation, editing,
deletion (with priority resequencing), drag-and-drop reordering, and
project filtering. Tests run against an in-memory SQLite database, so they
don't touch your MySQL database or require any extra setup:

```bash
php artisan test
# or
./vendor/bin/phpunit
```

## Deployment notes

- Set `APP_ENV=production` and `APP_DEBUG=false` in your production `.env`.
- Run `composer install --no-dev --optimize-autoloader`.
- Cache configuration and routes for a performance boost:

  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```

- Point your web server's document root at the `public/` directory (not the
  project root). An `.htaccess` file is included for Apache; for Nginx, use
  Laravel's standard `try_files` rewrite rule to `public/index.php`.
- Run `php artisan migrate --force` as part of your deploy step to apply
  migrations non-interactively.
- Make sure `storage/` and `bootstrap/cache/` are writable by the web server
  user.

## How reordering works

Each `<li>` in the task list renders with `data-id="{task id}"`. SortableJS
is attached to the `<ul>` and fires an `onEnd` callback whenever a drag
completes. That callback reads the current DOM order of `data-id`s and
`POST`s them as `{ task_ids: [...] }` (in top-to-bottom order) to
`POST /tasks/reorder`. The `TaskController::reorder()` method loops over
that array inside a database transaction and sets each task's `priority` to
its index in the array + 1 — so the first ID becomes priority `1`, the
second `2`, and so on. Because the list only ever contains the tasks
currently visible (i.e. already filtered to the selected project, if any),
reordering naturally stays scoped to that project.

Creating a new task assigns it `MAX(priority) + 1` within its project scope,
so it's appended to the bottom of the relevant list. Deleting a task
renumbers the remaining tasks in its scope back to a clean `1..n` sequence
with no gaps.
