<?php

namespace Nomanurrahman\FreshSeed\Tests;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FreshSeedFeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('comments');
    }

    /** @test */
    public function it_can_refresh_multiple_tables()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
        });

        DB::table('users')->insert(['name' => 'John Doe']);
        DB::table('posts')->insert(['title' => 'My First Post']);

        $this->assertEquals(1, DB::table('users')->count());
        $this->assertEquals(1, DB::table('posts')->count());

        $this->artisan('fresh:seed', ['--table' => 'users,posts'])
             ->expectsOutput('Truncating table: users')
             ->expectsOutput('Table [users] truncated successfully.')
             ->expectsOutput('Truncating table: posts')
             ->expectsOutput('Table [posts] truncated successfully.')
             ->assertExitCode(0);

        // If UsersTableSeeder was loaded by other tests, users table will be seeded. Otherwise, 0 rows.
        if (class_exists('Database\Seeders\UsersTableSeeder')) {
            $this->assertEquals(1, DB::table('users')->count());
            $this->assertEquals('Seeded User', DB::table('users')->first()->name);
        } else {
            $this->assertEquals(0, DB::table('users')->count());
        }
        $this->assertEquals(0, DB::table('posts')->count());
    }

    /** @test */
    public function it_can_refresh_using_custom_table_groups()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
        });

        if (!class_exists('Database\Seeders\UsersTableSeeder')) {
            require_once __DIR__ . '/UsersTableSeeder.php';
        }

        // Define a custom group in config dynamically
        config(['fresh-seed.groups.blog' => [
            'users' => 'Database\\Seeders\\UsersTableSeeder',
            'posts',
        ]]);

        DB::table('users')->insert(['name' => 'John Doe']);
        DB::table('posts')->insert(['title' => 'My First Post']);

        $this->artisan('fresh:seed', ['--group' => 'blog'])
             ->expectsOutput('Truncating table: users')
             ->expectsOutput('Table [users] truncated successfully.')
             ->expectsOutput('Seeding table using: Database\Seeders\UsersTableSeeder')
             ->expectsOutput('Truncating table: posts')
             ->expectsOutput('Table [posts] truncated successfully.')
             ->assertExitCode(0);

        // Users should have 1 seeded record, posts should be empty
        $this->assertEquals(1, DB::table('users')->count());
        $this->assertEquals('Seeded User', DB::table('users')->first()->name);
        $this->assertEquals(0, DB::table('posts')->count());
    }

    /** @test */
    public function it_fails_if_group_does_not_exist()
    {
        $this->artisan('fresh:seed', ['--group' => 'non_existent'])
             ->expectsOutput('Table group [non_existent] is not defined in config.')
             ->assertExitCode(1);
    }

    /** @test */
    public function it_supports_dry_run_mode()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        DB::table('users')->insert(['name' => 'John Doe']);

        $this->artisan('fresh:seed', ['--table' => 'users', '--dry-run' => true])
             ->expectsOutput('[Dry Run] Would truncate table: users')
             ->assertExitCode(0);

        // Row should still exist because it was a dry run
        $this->assertEquals(1, DB::table('users')->count());
    }

    /** @test */
    public function it_supports_safe_deletion_mode()
    {
        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
        });

        DB::table('posts')->insert(['title' => 'My First Post']);

        $this->artisan('fresh:seed', ['--table' => 'posts', '--safe' => true])
             ->expectsOutput('Deleting all records from table: posts')
             ->expectsOutput('Table [posts] records deleted successfully.')
             ->assertExitCode(0);

        $this->assertEquals(0, DB::table('posts')->count());
    }

    /** @test */
    public function it_validates_that_seeder_can_only_be_used_with_single_table()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
        });

        $this->artisan('fresh:seed', ['--table' => 'users,posts', '--seeder' => 'CustomSeeder'])
             ->expectsOutput('The --seeder option can only be used when refreshing a single table.')
             ->assertExitCode(1);
    }

    /** @test */
    public function it_supports_interactive_selection_mode()
    {
        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
        });

        Schema::create('comments', function ($table) {
            $table->increments('id');
            $table->string('body');
        });

        DB::table('posts')->insert(['title' => 'My First Post']);
        DB::table('comments')->insert(['body' => 'My First Comment']);

        // Check interactive selection and choose only "posts"
        $this->artisan('fresh:seed')
             ->expectsChoice(
                 'Which table(s) would you like to refresh? (comma separated numbers/names)',
                 ['posts'],
                 ['posts', 'comments']
             )
             ->expectsOutput('Truncating table: posts')
             ->expectsOutput('Table [posts] truncated successfully.')
             ->assertExitCode(0);

        $this->assertEquals(0, DB::table('posts')->count());
        $this->assertEquals(1, DB::table('comments')->count()); // comments was not refreshed!
    }

    /** @test */
    public function it_supports_custom_database_connection()
    {
        Schema::create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
        });

        // Set up secondary connection in config
        config(['database.connections.testbench_secondary' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);

        // Create table in secondary connection
        Schema::connection('testbench_secondary')->create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
        });

        DB::table('posts')->insert(['title' => 'Default Connection Post']);
        DB::connection('testbench_secondary')->table('posts')->insert(['title' => 'Secondary Connection Post']);

        $this->artisan('fresh:seed', ['--table' => 'posts', '--database' => 'testbench_secondary'])
             ->expectsOutput('Truncating table: posts')
             ->expectsOutput('Table [posts] truncated successfully.')
             ->assertExitCode(0);

        // Secondary connection table should be empty, default should still have its post!
        $this->assertEquals(0, DB::connection('testbench_secondary')->table('posts')->count());
        $this->assertEquals(1, DB::table('posts')->count());

        // Clean up secondary connection
        Schema::connection('testbench_secondary')->dropIfExists('posts');
    }
}
