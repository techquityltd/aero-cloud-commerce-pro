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

        $appPath = storage_path().'/app/';
        $ccpPath = 'cloudcommercepro/queue/products';

        // Get files
        $files = glob($appPath.$ccpPath.'/*.csv', 0);

        foreach($files as $file) {

            $this->call("aero:import:products:csv", ['path' => "{$ccpPath}/".basename($file)]);
            @rename ($file, $appPath.$ccpPath.'/processed/'.basename($file));
        }

    }
}

