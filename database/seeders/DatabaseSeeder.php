<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Akun administrator awal. Segera ganti kata sandinya setelah login pertama. */
    public function run(): void
    {
        $this->seedUser('admin', 'Administrator INBIS', User::ROLE_ADMIN);
    }

    /**
     * Buat akun bila belum ada. Kata sandi diambil dari config('inbis.seed_passwords')
     * (.env); bila kosong dibuat acak dan ditampilkan sekali di terminal.
     */
    protected function seedUser(string $username, string $name, string $role): User
    {
        if ($user = User::where('username', $username)->first()) {
            return $user;
        }

        $password = config("inbis.seed_passwords.{$username}");
        if (blank($password)) {
            $password = Str::password(12, symbols: false);
            $this->command?->warn("Kata sandi acak untuk '{$username}': {$password}  (catat sekarang, tidak ditampilkan lagi)");
        }

        return User::create([
            'username' => $username,
            'name' => $name,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
