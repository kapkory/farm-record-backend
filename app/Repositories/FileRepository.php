<?php


namespace App\Repositories;
use App\Models\Core\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
class FileRepository
{
    public static function move($file,$estimate_id,$public=false){
        try{
            $originan_name = $file->getClientOriginalName();
            $file_type = $file->getClientMimeType();
            $file_size = $file->getSize();
//            dd($file_size);
            $arr = explode('.',$originan_name);
            $ext = $arr[count($arr)-1];
            $file_name = Str::slug(str_replace($ext,'',$originan_name)).'.'.$ext;
            if($public){
                $pre = 'public';
            }else{
                $pre = 'app';
            }
            $path = '/estimates/'.$estimate_id.'/'.Carbon::now()->format('Y/m/d');
            $new_path = $path;
            $new_name =Str::random(3).'_'.date('H_i_s').'_'.$file_name;
            $disk = env('FILESYSTEM_DRIVER', 'local');
            if($public){
                $disk = 'local';
            }
            Storage::disk($disk)->putFileAs($new_path,$file,$new_name);
            if($public)
                $pre = 'storage';
            return [
                'file_name'=>$originan_name,
                'file_size'=>$file_size,
                'path'=>$path.'/'.$new_name,
                'file_type'=>$file_type,
                'uploaded'=>true,
                'ext'=>$ext,
                'disk'=>$disk
            ];
        }catch(\Exception $e){
            return [
                'uploaded'=>false,
                'error'=>$e->getMessage()
            ];
        }
    }

    public static function saveFile($file,$path,$public=false){
        try{
            $originan_name = $file->getClientOriginalName();
            $file_type = $file->getClientMimeType();
            $file_size = $file->getSize();
            $arr = explode('.',$originan_name);
            $ext = $arr[count($arr)-1];
            $file_name = Str::slug(str_replace($ext,'',$originan_name)).'.'.$ext;
            $pre = '';
            if($public)
                $pre = 'public/';

            $new_name =Str::random(3).'_'.date('H_i_s').'_'.$file_name;
            $disk = env('FILESYSTEM_DRIVER', 'local');
            if($public){
                $disk = 'local';
            }
            Storage::disk($disk)->putFileAs($pre.$path,$file,$new_name);

            $filePath = $path.'/'.$new_name;

            return [
                'path'=>isset($public) ? $filePath : ''.$filePath,
                'name'=>$originan_name
            ];


        }catch(\Exception $e){
            return [
                'uploaded'=>false,
                'error'=>$e->getMessage()
            ];
        }
    }

    public static function download($file){

        if (file_exists(public_path($file->path)))
            return \response()->download(public_path($file->path));


        if (file_exists(storage_path('app/'.$file->path)))
            return \response()->download(storage_path('app/'.$file->path));


        if (file_exists(storage_path('app/'.str_replace('public/','',$file->path))))
            return \response()->download(storage_path('app/'.str_replace('public/','',$file->path)));
        if(file_exists($file->path))
            return \response()->download($file->path);
        $slug = Str::slug($file->name);
        $file_path = storage_path('app/'.$file->path.$slug);
        $file_path = str_replace('-'.$file->type,'.'.$file->type,$file_path);
        if(file_exists($file_path))
            return \response()->download($file_path);
        $slug = str_replace($file->type,'.'.$file->type,$slug);
        $file_path = storage_path('app/'.$file->path.$slug);
        if(file_exists($file_path))
            return \response()->download($file_path);
        $file_path = storage_path('app/'.$file->path.$file->name);
        if(file_exists($file_path))
            return \response()->download($file_path);
        $file_path = storage_path('app/'.$file->path.Str::slug($file->name));
        return \response()->download($file_path);
    }

    /**
     * delete file
     */
    public static function delete($file){
        Storage::disk($file->disk)->delete($file->path);
        return $file->delete();
    }

    public static function sanitizeFile($file){

    }
}
