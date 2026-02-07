<?php

namespace App\Repositories;
use phpDocumentor\Reflection\Types\Self_;
use Route;

class SearchRepo
{
    protected static $data;
    protected static $request_data;
    protected static $instance;
    protected static $records_count;
    protected static $tmp_key;
    protected static $tmp_value;
    public static function of($model){
        self::$instance = new self();
        $request_data = request()->all();
        self::$request_data = $request_data;
        if(isset($request_data['filter_value'])){
            $value = $request_data['filter_value'];

            $model = $model->where(function($query) use ($request_data,$value){
                $index = 0;
                foreach($request_data['keys'] as $key){
                    if(!strpos($key,'.') && $request_data['base_table'] != null)
                        $key = $request_data['base_table'].'.'.$key;
                    if($index == 0){
                        $query->where([
                            [$key,'like','%'.$value.'%']
                        ]);
                    }else{
                        $query->orWhere([
                            [$key,'like','%'.$value.'%']
                        ]);
                    }
                    $index++;
                }

            });
        }
        $request_data = self::$request_data;
        // escaping orderby if you have a groupBy in your query as it would result to error
        // set the is_grouped property for this section not to execute
        if (!isset($request_data['is_grouped'])){
            if(isset($request_data['order_by']) && isset($request_data['order_method'])){
                $model = $model->orderBy($request_data['order_by'],$request_data['order_method']);
            }else{
                $model = $model->orderBy('created_at','desc');
            }
        }

        //count model records
        self::$records_count = $model->count();
        if(isset($request_data['per_page'])){
            $data =  $model->paginate(round($request_data['per_page'],0));
        }else{
            $data= $model->paginate(10);
        }
        self::$data = $data;
        return self::$instance;
    }

    public static function make($pagination = true){
        $data = self::$data;
        $request_data = self::$request_data;
        unset($request_data['page']);
        $data->appends($request_data);
        if($pagination){
            $pagination = $data->links()->__toString();
            $data = $data->toArray();
            $data['pagination'] = $pagination;
        }
        $data['results_count'] = number_format(self::$records_count);
        return $data;

    }

    public static function addColumn($column,$function){
        $records = self::$data;
        foreach($records as $index=>$record){
            $record->$column = $function($record);
            $records[$index] = $record;
        }
        self::$data = $records;
        return self::$instance;
    }
}
