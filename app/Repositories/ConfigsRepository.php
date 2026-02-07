<?php
/**
 * Created by PhpStorm.
 * User: kemboi
 * Date: 22/03/19
 * Time: 3:05 PM
 */

namespace App\Repositories;


use App\Models\Core\Tax;
use App\User;

class ConfigsRepository
{


    public static function getUserStatus($state){
        $statuses = [
            'inactive'=>0,
            'active'=>1,
            'suspended'=>2,
            'deactivated'=>3
        ];
        if(is_numeric($state))
            $statuses = array_flip($statuses);
        if(is_array($state)){
            $states  = [];
            foreach($state as $st){
                $states[] = $statuses[$st];
            }
            return $states;
        }
        return $statuses[$state];
    }

    public static function getTaxStatus($state){
        $statuses = [
            'inactive'=>0,
            'active'=>1
        ];
        if(is_numeric($state))
            $statuses = array_flip($statuses);
        if(is_array($state)){
            $states  = [];
            foreach($state as $st){
                $states[] = $statuses[$st];
            }
            return $states;
        }
        return $statuses[$state];
    }

    /**
     * get current V.A.T percentage value
     */
    public static function getVatPercentage(){
        $vat = Tax::where([
            ['name','vat'],
            ['applied_to','products'],
            ['status',self::getTaxStatus('active')],
        ])->first();
        if($vat)
            return $vat->percentage;
        else
            return null;
    }

}
