<?php
/**
 * Created by PhpStorm.
 * User: kemboi
 * Date: 9/13/18
 * Time: 6:13 PM
 */

namespace App\Repositories;


use App\Models\Core\Log;
use App\Models\Core\LogType;
class LogsRepository
{

public static function storeLog($slug,$description,$source_type=0,$invoice_id=null, $user_id = null)
{
    $log_type = LogType::where('slug',$slug)->first();
    if (!$log_type){
        return response()->json( true );
    }
    $log = new Log();
    $log ->log_type_id = $log_type->id;
    $log ->user_id = $user_id ?? request()->user()->id;
    $log ->description = $description;
    if ($invoice_id != null)
        $log ->invoice_id = $invoice_id;
    $log ->save();
    return response()->json( true );
}

}
