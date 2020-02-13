<?php

namespace Techquity\CloudCommercePro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use DB;

class ImportProducts extends Command
{
    protected $signature = 'ccp:import:products';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle() : void
    {
        //

        $this->call("aero:import:products:csv", ['path' => "cloudcommercepro/{$product['parent_ref']}"]);

    }
}

