<?php


namespace App\Repositories;
use phpDocumentor\Reflection\Types\Self_;
use Route;

class SearchApiRepo
{
    protected static $data;
    protected static $request_data;
    protected static $instance;
    protected static $tmp_key;
    protected static $tmp_value;
    public static function of($model,$orderBy =null){
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
        if(isset($request_data['order_by']) && isset($request_data['order_method'])){
            $model = $model->orderBy($request_data['order_by'],$request_data['order_method']);
        }else
        {
            if ($orderBy)
                $model = $model->orderBy($orderBy,'asc');
        }

        if(isset($request_data['per_page'])){
            $data =  $model->paginate(round($request_data['per_page'],0));
        }else{
            $data= $model->paginate(20);
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
