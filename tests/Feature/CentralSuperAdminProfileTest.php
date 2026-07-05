<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CentralSuperAdminProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number', 32)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('password');
            $table->uuid('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_authenticated_central_user_can_view_profile_page(): void
    {
        $user = $this->ownerUser();

        $response = $this->actingAs($user, 'central')->get('http://aircloud.biz.id/super-admin/profile');

        $response->assertOk();
        $response->assertSee('Data Profil');
        $response->assertSee('Ubah Password');
    }

    public function test_authenticated_central_user_can_update_profile_with_avatar(): void
    {
        Storage::fake('public');

        $user = $this->ownerUser();

        $response = $this->actingAs($user, 'central')->patch('http://aircloud.biz.id/super-admin/profile', [
            'name' => 'Central Owner Updated',
            'email' => 'owner.updated@aircloud.test',
            'phone_number' => '0812 3456 7890',
            'avatar' => UploadedFile::fake()->image('owner.png', 200, 200),
            'remove_avatar' => '0',
        ]);

        $response->assertRedirect(route('central.super-admin.profile.edit', absolute: false));
        $response->assertSessionHas('status', 'Profil superadmin berhasil diperbarui.');

        $user->refresh();

        $this->assertSame('Central Owner Updated', $user->name);
        $this->assertSame('owner.updated@aircloud.test', $user->email);
        $this->assertSame('0812 3456 7890', $user->phone_number);
        $this->assertNotNull($user->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists((string) $user->avatar_path));
    }

    public function test_authenticated_central_user_can_update_password(): void
    {
        $user = $this->ownerUser();

        $response = $this->actingAs($user, 'central')->patch('http://aircloud.biz.id/super-admin/profile/password', [
            'current_password' => 'OldPass123',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);

        $response->assertRedirect(route('central.super-admin.profile.edit', absolute: false));
        $response->assertSessionHas('password_status', 'Password berhasil diperbarui.');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPass123', (string) $user->password));
    }

    protected function ownerUser(): User
    {
        $role = Role::query()->create([
            'id' => 'role-owner',
            'name' => 'Owner',
            'slug' => 'owner',
        ]);

        $user = User::query()->create([
            'name' => 'Central Owner',
            'email' => 'owner@aircloud.test',
            'password' => Hash::make('OldPass123'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return $user->setRelation('role', $role);
    }
}
