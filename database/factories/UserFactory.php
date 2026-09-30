<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Mọi cột đều có giá trị: mô hình bật `preventAccessingMissingAttributes`,
     * nên tài khoản dựng thiếu cột sẽ làm giao diện báo lỗi ở môi trường dev.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_STAFF,
            'staff_code' => null,
            'department_id' => null,
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_BHYT_MANAGER]);
    }

    public function head(Department $department): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_DEPT_HEAD,
            'department_id' => $department->id,
        ]);
    }

    public function staff(Department $department, ?string $staffCode = null): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_STAFF,
            'department_id' => $department->id,
            'staff_code' => $staffCode,
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_VIEWER]);
    }
}
