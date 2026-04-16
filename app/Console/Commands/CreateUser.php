<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    protected $signature = 'user:create {email} {name} {password}';

    protected $description = 'Create a new user';

    public function handle()
    {
        $email = $this->argument('email');
        $humanName = $this->argument('name');
        $password = $this->argument('password');

        $validator = Validator::make([
            'email' => $email,
            'name' => $humanName,
            'password' => $password,
        ], [
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('User creation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - ' . $error);
            }
            return 1;
        }

        $user = User::create([
            'email' => $email,
            'name' => $humanName,
            'password' => Hash::make($password),
            'email_enabled_at' => null,
        ]);

        $user->createDefaultProject();

        $this->info("User created successfully!");
        $this->info("Email: {$user->email}");
        $this->info("Name: {$user->name}");
        $this->info("ID: {$user->id}");
        $this->info("Default project created: {$user->name}'s Inbox");

        return 0;
    }
}
