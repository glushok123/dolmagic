<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Eloquent\Sales\Sale;
use DB;

class CalculationMrgSalesByIntervalShort extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:mrgIntervalShort';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $countSales = DB::table('sales')
            ->where('created_at', '>=', Carbon::now()->subDays(10)->toDateTimeString())
            ->count();

        $progressBar = $this->output->createProgressBar($countSales);
        $progressBar->start();

        Sale::where('created_at', '>=', Carbon::now()->subDays(100)->toDateTimeString())
            ->chunk(100, function($sales) use ($progressBar) {
                foreach ($sales as $sale) {
                    if (DB::connection('tech')->table('calculation_mrg_sales')->where('sale_id', $sale->id)->exists() == false) {
                        DB::connection('tech')->table('calculation_mrg_sales')->insert([
                            'sale_id' => $sale->id,
                            'mrg_sale' => $sale->marginValue,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                    else {
                        DB::connection('tech')->table('calculation_mrg_sales')->where('sale_id', $sale->id)->update([
                            'mrg_sale' => $sale->marginValue,
                            'updated_at' => Carbon::now(),
                        ]);
                    }

                    $progressBar->advance();
                }
        });

        $progressBar->finish();

        return Command::SUCCESS;
    }
}