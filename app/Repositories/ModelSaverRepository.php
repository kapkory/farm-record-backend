<?php


namespace App\Repositories;


class ModelSaverRepository
{

    public function saveModel($request_data){
        $request_data = (object)$request_data;
        $class = $request_data->form_model;
        $model = $class::findOrNew(@$request_data->id);
        foreach($request_data as $key=>$value){
            if(!in_array($key,['id','_token','entity_name','form_model','password_confirmation','tab'])){
                if($key == 'password'){
                    $model->$key = bcrypt($value);
                }else{
                    $model->$key = $value;
                }
            }
        }
        $model->save();
        return $model;
    }
}
