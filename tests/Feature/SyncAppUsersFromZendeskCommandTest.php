<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZdUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncAppUsersFromZendeskCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_fallback_email_when_zendesk_user_has_no_email(): void
    {
        ZdUser::create([
            'zd_id' => 1234,
            'name' => 'No Mail',
            'email' => null,
            'role' => 'end-user',
        ]);

        $this->artisan('zendesk:sync-users-app', ['--password' => 'TempPass123!'])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'zd_1234@local.tecdesk',
            'role' => 'cliente',
        ]);
    }

    public function test_it_does_not_print_password_by_default(): void
    {
        ZdUser::create([
            'zd_id' => 1235,
            'name' => 'Masked',
            'email' => 'masked@example.com',
            'role' => 'agent',
        ]);

        $result = $this->artisan('zendesk:sync-users-app', ['--password' => 'TopSecret123!']);

        $result->expectsOutputToContain('Temporary password was applied but not displayed')
            ->assertSuccessful();

        $this->assertSame(1, User::count());
    }
}
