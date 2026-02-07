<?php

namespace App\Repositories;


use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AuthHelper
{
    protected $user;
    protected $request;
    function __construct(Request $request){
        $this->request = $request;
        $this->user = $request->user();
    }
    public function authorizeInstitutionUser(){
        $user = $this->user;
        $request = $this->request;
        $institution = $user->getInstitution();
        if(!$institution){
            //user has just created account, redirect to fill account details
            $path = $request->path();
            if($path != 'institution/info'){
                return 'institution/info';
            }
        }
        return null;
    }

    public function authorizeAgentUser(){
        $user = $this->user;
        $request = $this->request;
        $agent = $user->getAgent();
        if($agent){
            if($agent->suspended == 1 || $agent->status == 0){
                $allowed_paths = [
                    'agent',
                    'agent/account/details',
                    'user/profile',
                    'agent/account/documents',
                    'agent/account/status/suspended',
                    'agent/account/status/inactive',
                    'agent/account/documents/list',
                    'logout'
                ];
                $path = Route::getFacadeRoot()->current()->uri();
                $current = preg_replace('/\d/', '', $path );
                $current = preg_replace('/{(.*?)}/', '', $current );
                $path = rtrim($current, '/');
                if(!in_array($path,$allowed_paths)){
                    if($agent->suspended)
                        return 'agent/account/status/suspended';
                    return 'agent/account/status/inactive';
                }
            }
        }
        return null;
    }
}
