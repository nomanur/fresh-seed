<?php

namespace Nomanurrahman\FreshSeed\Tests;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FreshSeedCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        Schema::dropIfExists('users');
    }

    /** @test */
    public function it_can_truncate_a_table()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        DB::table('users')->insert(['name' => 'John Doe']);
        
        $this->assertEquals(1, DB::table('users')->count());

        $this->artisan('fresh:seed', ['--table' => 'users'])
             ->expectsOutput('Truncating table: users')
             ->expectsOutput('Table [users] truncated successfully.')
             ->assertExitCode(0);

        $this->assertEquals(0, DB::table('users')->count());
    }

    /** @test */
    public function it_fails_if_table_does_not_exist()
    {
        $this->artisan('fresh:seed', ['--table' => 'non_existent_table'])
             ->expectsOutput('Table [non_existent_table] does not exist.')
             ->assertExitCode(1);
    }

    /** @test */
    public function it_can_auto_detect_and_run_seeder()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        // The seeder class is autoloaded or manually required
        if (!class_exists('Database\Seeders\UsersTableSeeder')) {
            require_once __DIR__ . '/UsersTableSeeder.php';
        }

        $this->artisan('fresh:seed', ['--table' => 'users'])
             ->expectsOutput('Truncating table: users')
             ->expectsOutput('Table [users] truncated successfully.')
             ->expectsOutput('Seeding table using: Database\Seeders\UsersTableSeeder')
             ->assertExitCode(0);

        $this->assertEquals(1, DB::table('users')->count());
        $this->assertEquals('Seeded User', DB::table('users')->first()->name);
    }

    /** @test */
    public function it_respects_the_force_flag()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        $this->artisan('fresh:seed', ['--table' => 'users', '--force' => true])
             ->assertExitCode(0);
    }
}
