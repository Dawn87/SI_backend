<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;
use Carbon\Carbon;

class BloomfiltersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $bloom = config('bloomfilter.bloom');
        $num = config('bloomfilter.num');
        DB::table('bloomfilters')->insert([
            'bloomfilter' => $bloom,
            'entries_max' => $num,
            'created_at'=>Carbon::now(),    
            // 對應 timestamps 的 created_at 列位
            'updated_at'=>Carbon::now(),    
            // 對應 timestamps 的 updated_at 列位
        ]);
    }
}
