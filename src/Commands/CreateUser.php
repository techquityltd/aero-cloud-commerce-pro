<?php

namespace Techquity\CloudCommercePro\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use DB;

class CreateUser extends Command
{
    protected $signature = 'create:api:user';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle() : void
    {
        $apiToken = Str::random(60);

        DB::table('users')->insert([
            'name' => 'Cloud Commerce Pro',
            'email' => $this->ask('Email?'),
            'api_token' => $apiToken,
            'password' => Hash::make($this->secret('Password?')),
        ]);

        $this->info('API Account created for Cloud Commerce Pro: ' . $apiToken);

    }
}

