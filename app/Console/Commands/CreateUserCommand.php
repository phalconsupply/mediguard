<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Tạo tài khoản đầu tiên.
 *
 * Hệ thống cố tình **không** seed sẵn tài khoản quản trị với mật khẩu mặc định:
 * một tài khoản mặc định công bố trong tài liệu là lỗ hổng, nhất là khi hệ thống
 * giữ dữ liệu bệnh nhân. Mật khẩu chỉ nhập lúc chạy lệnh.
 */
class CreateUserCommand extends Command
{
    protected $signature = 'mediguard:create-user
        {--name= : Họ tên}
        {--email= : Địa chỉ thư điện tử}
        {--role=admin : admin|bhyt_manager|dept_head|staff|viewer}
        {--staff-code= : Mã bác sĩ/NVYT trong dữ liệu nguồn}
        {--department= : Mã khoa}';

    protected $description = 'Tạo tài khoản sử dụng hệ thống (mật khẩu nhập tại dòng lệnh, không hiển thị).';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Họ tên');
        $email = $this->option('email') ?: $this->ask('Địa chỉ thư điện tử');
        $password = $this->secret('Mật khẩu');
        $confirm = $this->secret('Nhập lại mật khẩu');

        if ($password !== $confirm) {
            $this->error('Hai lần nhập mật khẩu không khớp.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $this->option('role'),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->numbers()],
            'role' => ['required', 'in:admin,bhyt_manager,dept_head,staff,viewer'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $departmentId = null;
        if ($code = $this->option('department')) {
            $departmentId = Department::where('code', $code)->value('id');
            if ($departmentId === null) {
                $this->warn("Chưa có khoa với mã {$code}; tài khoản được tạo mà không gán khoa.");
            }
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $this->option('role'),
            'staff_code' => $this->option('staff-code'),
            'department_id' => $departmentId,
            'is_active' => true,
        ]);

        $this->info("Đã tạo tài khoản #{$user->id} ({$user->email}) với vai trò {$user->role}.");

        return self::SUCCESS;
    }
}
