<?php

namespace App\Console\Commands\Insales;


use App\Models\Commands\Commands;
use App\Models\Others\Insales\Insales;
use Illuminate\Console\Command;

class ProductsMatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insales:productsMatches';

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
        Commands::commandLog($this->signature, function(){
            Insales::productsMatches();
        });

    }
}
