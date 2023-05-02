<?php

namespace App\Console\Commands\Insales;

use App\Console\Api\InsalesApi;
use App\Models\Commands\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OrdersUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insales:ordersUpdate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command check and update all orders from others systems';

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
        Commands::commandLog($this->signature, function(){
            (new InsalesApi)->updateUncompletedOrders();
        });

    }
}
