<?php


namespace App\Repositories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
//use Storage;
use Illuminate\Support\Facades\Storage;
use View;
use Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
class RoleRepository
{
    protected $path;
    protected $user;
    protected $menus;
    protected $allow=false;
    protected $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->user = Auth::user();
        $this->path = Route::getFacadeRoot()->current()->uri();
        $sub_path = strtolower($this->path);
        $sub_path = str_replace('unpaid','bids',$sub_path);
        $sub_path = str_replace('disputes','resolution',$sub_path);
        $sub_path = str_replace('stud','Home',$sub_path);
        $sub_pages = explode('/',$sub_path);
        View::share('sub_pages',$sub_pages);

    }

    public function check($allow=false){
        $path_sections = explode('/',$this->path);
        if(in_array('api',$path_sections)){
            return true;
        }
        $this->allow = $allow;
        $file = Storage::disk('local')->get('system/roles.json');
        $menus = json_decode($file);

        $this->menus = $menus;

        if(!$menus ){
            die('Error decoding json config');
        }
        if(Auth::user()){
            $user = Auth::user();
            $role = $this->user->role;
            if(!$role){
                $this->user->role = "admin";
                $this->user->update();
                $role = "admin";
            }

            $allowed = $menus->$role;

            if($role == 'superadmin'){
                    $permissions = [];
                    if($this->user->permissionGroup)
                        $permissions = json_decode($this->user->permissionGroup->permissions);
                    $allowed = [];

                    foreach($menus->superadmin as $mnu){
                        if(in_array($mnu->slug,$permissions))
                            $allowed[]=$mnu;
                    }
                    $this->authorize($allowed,$menus->guest);

            }elseif($role == 'admin'){
                    $permissions = [];
                    if($this->user->permissionGroup)
                        $permissions = json_decode($this->user->permissionGroup->permissions);
                    $allowed = [];

                    foreach($menus->admin as $mnu){
                        if(in_array($mnu->slug,$permissions))
                            $allowed[]=$mnu;
                    }
                    $this->authorize($allowed,$menus->guest);

            }else{
//                foreach($this->menus->in as $mnu){
//                    $allowed[]=$mnu;
//                }

                $this->authorize($allowed,$menus->guest,[]);
//                View::share('real_menus',$allowed);
            }
        }else{
            $out_menus = $menus->guest;
            foreach($this->menus->out as $mnu){
                $out_menus[]=$mnu;
            }
            View::share('real_menus',$out_menus);
        }

    }
    protected function authorize($backend,$front_end){
        $current = preg_replace('/\d/', '', $this->path );
        $current = preg_replace('/{(.*?)}/', '', $current );
        $current = rtrim($current, '/');
        View::share('current_url',$current);
//        if($this->user->role=='business'){
//            $business = $this->filterBackend($business);
//        }

        $backend_urls = $this->separateLinks($backend);
        $front_end_urls = $this->separateLinks($front_end);
//        $business_urls = $this->separateLinks($business);
        $current = str_replace("//","/",$current);
//        dd($this->path,$business_urls);
        if(in_array($current,$backend_urls)){
            View::share(['real_menus'=>$backend]);
        }elseif(in_array($current,$front_end_urls) || $this->allow == true){
            foreach($this->menus->in as $mnu){
                $front_end[] = $mnu;
            }
            View::share('real_menus',$front_end);
        }else{
            $this->unauthorized();
        }

    }

    public function filterBackend($backend){
        $allowed = [];
        if($this->user->role == 'business'){
            $group_permissions = $this->user->userGroup->permissions;

        }elseif($this->user->role == 'super'){
            $group_permissions = json_decode($this->user->group->permissions);
        }
        if(!$group_permissions){
            $group_permissions = [];
        }
        foreach($backend as $single){
            if(in_array($single->slug,$group_permissions)){
                $allowed[]=$single;
                if($single->slug=='user_management'){
                    $user_groups = UserGroup::all(['id','name']);
                    foreach($user_groups as $group){
                        $menu = new \stdClass();
                        $menu->url = "users/view/".$group->id;
                        $menu->label = $group->name;
                        $single->children[] = $menu;
                    }
                }
            }

        }
        return $allowed;
    }
    protected function separateLinks($raw_menu){
        $links = [];
        foreach($raw_menu as $single){
            $main_url = "";
            if($single->type=='many'){
                foreach($single->children as $child){
                    $child_url = preg_replace('/\d/', '', $child->url );
                    $child_url = rtrim($child_url, '/');
                    if(!in_array($child_url,$links))
                        $links[]=$child_url;
                }
                if(isset($single->urls)){
                    foreach($single->urls as $url){
                        $url = rtrim($url, '/');
                        $url = preg_replace('/\d/', '', $url );
                        if(!in_array($url,$links))
                            $links[]=$url;
                    }
                }

                if(isset($single->subs) && isset($single->main)){
                    $child_url = preg_replace('/\d/', '', $single->main );
                    $child_url = rtrim($child_url, '/');
                    $main_url = $child_url;
                    foreach($single->subs as $url){
                        $url = rtrim($url, '/');
                        $url = preg_replace('/\d/', '', $url );
                        $url = $main_url.'/'.$url;
                        if(!in_array($url,$links))
                            $links[]=$url;
                    }
                }
            }else{
                if(isset($single->menus->url)){
                    $child_url = preg_replace('/\d/', '', $single->menus->url );
                    $child_url = rtrim($child_url, '/');
                    $main_url = $child_url;
                    if(!in_array($child_url,$links))
                        $links[]=$child_url;
                }
                if(isset($single->subs)){
                    foreach($single->subs as $url){
                        $url = rtrim($url, '/');
                        $url = preg_replace('/\d/', '', $url );
                        $url = $main_url.'/'.$url;
                        if(!in_array($url,$links))
                            $links[]=$url;
                    }
                }
            }
            if(isset($single->urls))
                foreach($single->urls as $url){
                    $url = rtrim($url, '/');
                    $url = preg_replace('/\d/', '', $url );
                    if(!in_array($url,$links))
                        $links[]=$url;
                }
        }
        return $links;
    }

    protected function sanitizeBusinessUrls($urls){

    }

    public function unauthorized(){
        $common_paths = ['logout','login','register'];
        $path = $this->path;
//        dd($path,$common_paths);
        if(!in_array($path,$common_paths)){
            App::abort(403);
            die('You are not authorized to perform this action');
        }
    }
}
