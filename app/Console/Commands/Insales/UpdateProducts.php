<?php

namespace App\Console\Commands\Insales;

use App\Console\Api\OzonApi;
use App\Models\Commands\Commands;
use App\Models\Others\Insales\Insales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insales:updateProducts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Insales::updateProducts();
    }
}
