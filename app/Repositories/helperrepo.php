<?php

use App\Repositories\StatusRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (!function_exists('autoForm')) {
    function autoForm($elements, $action, $classes = [], $model = null)
    {
        $model_form = null;
        if (!is_array($elements)) {
            $model_form = $elements;
            $elements = new $elements();
            $elements = $elements->getfillable();
            $elements['form_model'] = $model_form;
        }
        $formRepository = new \App\Repositories\FormRepository();
        return $formRepository->autoGenerate($elements, $action, $classes, $model);
    }
}

/**
 * Truncate a string after a given number of words -- limit number of words
 */
if (!function_exists('limit_string_words')) {
    function limit_string_words($text, $words_limit)
    {
        if (str_word_count($text, 0) > $words_limit) {
            $words = str_word_count($text, 2);
            $pos = array_keys($words);
            $text = substr($text, 0, $pos[$words_limit]) . ' ...';
        }
        return $text;
    }
}
/**
 * Get current logged in user
 */
if (!function_exists('getUser')) {
    function getUser($user_id = null)
    {
        if ($user_id)
            return \App\Models\User::whereId($user_id)->first();
        return auth()->user();
    }
}

if (!function_exists('getUserStatus')) {
    function getUserStatus($state)
    {
        return \App\Repositories\StatusRepository::getUserStatus($state);
    }
}

if (!function_exists('getModelStatus')) {
    function getModelStatus($status)
    {
        return \App\Repositories\StatusRepository::getModelStatus($status);
    }
}
if (!function_exists('userCan')) {
    function userCan($slug)
    {
        return request()->user()->isAllowedTo($slug);
    }
}
if (!function_exists('getAllCategoryTypeItems')) {
    function getAllCategoryTypeItems()
    {
        return Cache::rememberForever('category_type_items', function () {
            $items = DB::table('category_type_items')->select('id', 'name')->get()->toArray();
            $keyValueArray = [];
            foreach ($items as $item) {
                $keyValueArray[$item->id] = $item->name;
            }
            return $keyValueArray;
        });
    }
}
if (!function_exists('getAllCaseStatuses')) {
    function getAllCaseStatuses()
    {
        return Cache::rememberForever('all_case_statuses', function () {
            $items = DB::table('case_statuses')->where('status', 1)->select('id', 'id as status_id', 'name', 'slug', 'icon_slug', 'btn_color_code')->get()->toArray();
            $keyValueArray = [];
            foreach ($items as $item) {
                $keyValueArray[$item->id]['status_id'] = $item->id;
                $keyValueArray[$item->id]['name'] = $item->name;
                $keyValueArray[$item->id]['slug'] = $item->slug;
                $keyValueArray[$item->id]['icon_slug'] = $item->icon_slug;
                $keyValueArray[$item->id]['btn_color_code'] = $item->btn_color_code;
            }
            return $keyValueArray;
        });
    }
}
if (!function_exists('getPermissionType')) {
    function getPermissionType($state)
    {
        return StatusRepository::getPermissionType($state);
    }
}

if (!function_exists('getCaseStatusBySlug')) {
    function getCaseStatusBySlug($slug)
    {
        $statusesArr = getAllCaseStatuses();
        $res = searchMultDimArray($statusesArr, 'slug', $slug);
        if ($res >= 0) {
            $statusesArr[$res]['id'] = $res;
            return $statusesArr[$res];
        }
        return [];
    }
}
if (!function_exists('searchMultDimArray')) {
    function searchMultDimArray($array_data, $index, $value)
    {

        foreach (@$array_data as $key => $data) {
            if (@$data[$index] === $value)
                return $key;
        }
        return -1;
    }
}
if (!function_exists('userHas')) {
    function userHas($user_id, $slug, $section = 'writing')
    {
        return \App\Models\User::where('id', '=', $user_id)->first()->isAllowedTo($slug, $section);
    }
}


if (!function_exists('getTaskReminderPeriod')) {
    function getTaskReminderPeriod($state)
    {
        return \App\Repositories\StatusRepository::getTaskReminderPeriod($state);
    }
}
if (!function_exists('getTaskStatus')) {
    function getTaskStatus($state)
    {
        return \App\Repositories\StatusRepository::getTaskStatus($state);
    }
}if (!function_exists('getUserAuthority')) {
    function getUserAuthority($state)
    {
        return \App\Repositories\StatusRepository::getUserAuthority($state);
    }
}

if (!function_exists('getTaskStatusButton')) {
    function getTaskStatusButton($state, $due_date = null, $icon = true): string
    {
        if (!is_string($state))
            $stat_index = getTaskStatus($state);

        $stat_index = strtolower($stat_index ?? $state);
        $btn['active'] = [0 => 'info', 1 => 'toggle-on', 2 => ' Active'];
        $btn['on-hold'] = [0 => 'warning', 1 => 'pause', 2 => ' On-hold'];
        $btn['canceled'] = [0 => 'danger', 1 => 'times', 2 => ' Canceled'];
        $btn['completed'] = [0 => 'success', 1 => 'thumbs-up', 2 => ' Completed'];

        $overdueSuffixStr = "";
        if ($due_date && now()->gt(Carbon::parse($due_date)) && $stat_index == "active")
            $overdueSuffixStr = "&nbsp;<b><i data-popup='tooltip' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Overdue Task!' class='fa fa-clock text-danger'></i></b>";
        if (!$icon)
            return "<button class=\"btn btn-" . $btn[$stat_index][0] . " badge rounded badge-pill font-weight-bold btn-sm small\">  <b>" . $btn[$stat_index][2] . "</b></button>" . $overdueSuffixStr;
        return "<button class=\"btn btn-" . $btn[$stat_index][0] . " badge rounded badge-pill font-weight-bold btn-sm small\"> <i class='fas fa-" . $btn[$stat_index][1] . "'></i>  <b>" . $btn[$stat_index][2] . "</b></button>" . $overdueSuffixStr;

    }
}
if (!function_exists('formatDeadline')) {
    function formatDeadline($date)
    {

        $date = \Carbon\Carbon::createFromTimestamp(strtotime($date));
        if ($date->isPast()) {
            $div_pre = '<strong class="text-danger">';
            $pre = "(late)";
            $days = $date->diffInDays();
            $hours = $date->copy()->addDays($days)->diffInHours();
            $minutes = $date->copy()->addDays($days)->addHours($hours)->diffInMinutes();
            $days_string = $days . 'D ';
            $hours_string = $hours . "H ";
        } else {
            $pre = '';
            $days = $date->diffInDays();
            $hours = $date->copy()->subDays($days)->diffInHours();
            if ($days > 0 || $hours > 5) {
                $div_pre = '<strong class="text-success">';
            } else {
                $div_pre = '<strong class="text-warning">';
            }
            $minutes = $date->copy()->subDays($days)->subHour($hours)->diffInMinutes();
            $days_string = $days . 'D ';
            $hours_string = $hours . "H ";
        }
        if ($days == 0)
            $days_string = "";
        if ($hours == 0)
            $hours_string = "";
        return $div_pre . $days_string . $hours_string . $minutes . "Mins " . $pre . '</strong>';
    }
}

if (!function_exists('formatDeadline1')) {
    function formatDeadline1($date)
    {
        $date = \Carbon\Carbon::createFromTimestamp(strtotime($date));
        if ($date->isPast())
            $div_pre = '<strong class="text-danger">';
        else
            $div_pre = '<strong class="text-success">';

        return $div_pre . $date->isoFormat('ddd, Do MMM Y') . '</strong>';
    }
}

if (!function_exists('getDaysDifference')) {
    function getDaysDifference($date_from, $date_to, $formated = false)
    {
        if (!$date_from)
            return 0;
        if ($date_to)
            $date_to = Carbon::parse($date_to);
        else
            $date_to = Carbon::today();

        $date_from = Carbon::parse($date_from);
        if ($date_to < $date_from)
            return 0;
        $date_diff = $date_to->diffInDaysFiltered(function (Carbon $date) {
            return !$date->isWeekend();
        }, $date_from);

        if (!$formated)
            return $date_diff;
        else
            if ($date_diff > 0)
                $div_pre = '<strong class="text-danger">';
            else
                $div_pre = '<strong class="text-success">';

        return $div_pre . number_format($date_diff) . '</strong>';
    }
}
if (!function_exists('getTimeDifferenceInDaysAndHours')) {
    function getTimeDifferenceInDaysAndHours($date_from, $date_to, $formated = false)
    {
        if (!$date_from)
            return 0;
        if ($date_to)
            $date_to = Carbon::parse($date_to);
        else
            $date_to = Carbon::today();
        $date_from = Carbon::parse($date_from);
        $div_pre = '<strong class="text-info">';
        $days = $date_from->diffInDays($date_to);
        $hours = $date_from->copy()->addDays($days)->diffInHours($date_to);
        $minutes = $date_from->copy()->addDays($days)->addHours($hours)->diffInMinutes($date_to);
        $days_string = ($days == 1) ? $days . 'Day ' : $days . 'Days ';
        $hours_string = ($hours == 1) ? $hours . 'Hr ' : $hours . 'Hrs ';

        if ($days == 0)
            $days_string = "";
        if ($hours == 0)
            $hours_string = "";
        return $div_pre . $days_string . $hours_string . $minutes . "Mins " . '</strong>';
    }
}


if (!function_exists('bytesToHuman')) {
    function bytesToHuman($bytes)
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
if (!function_exists('encryptProperty')) {
    function encryptProperty($data)
    {
        $encryptionKey = env('ENC_KEY');
        $encryption_key = base64_decode($encryptionKey);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
}

if (!function_exists('decryptProperty')) {
    function decryptProperty($data)
    {
        $encryptionKey = env('ENC_KEY');
        $encryption_key = base64_decode($encryptionKey);
        list($encrypted_data, $iv) = array_pad(explode('::', base64_decode($data), 2), 2, null);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);

    }
}
if (!function_exists('getStatus')) {
    function getStatus($state)
    {
        return StatusRepository::getStatus($state);
    }
}
if (!function_exists('obfuscateEmail')) {

    function obfuscateEmail($email)
    {
        $em = explode("@", $email);
        $name = implode('@', array_slice($em, 0, count($em) - 1));
        $len = floor(strlen($name) / 2);

        return substr($name, 0, $len) . str_repeat('*', $len) . "@" . end($em);
    }
}

if (!function_exists('getAge')) {
    function getAge($createdAt, $closedAt = null)
    {
        $closedAt = $closedAt ?? Carbon::now();
        if (isset($createdAt) && isset($closedAt))
            return getTimeDifferenceInDaysAndHours($createdAt, $closedAt);

        return "";
    }
}

if (!function_exists('getPaymentStatus')) {
    function getPaymentStatus($state)
    {
        return StatusRepository::getPaymentStatus($state);
    }
}

if (!function_exists('getPaymentStatusButton')) {
    function getPaymentStatusButton($state, $icon = true): string
    {
        if (!is_string($state))
            $stat_index = getPaymentStatus($state);

        $stat_index = strtolower($stat_index ?? $state);
        $btn['completed'] = [0 => 'success', 1 => 'check-circle', 2 => ' Completed'];
        $btn['refunded'] = [0 => 'danger', 1 => 'undo', 2 => ' Refunded'];

        if (!$icon)
            return "<span class=\"badge bg-" . $btn[$stat_index][0] . " font-weight-bold\">" . $btn[$stat_index][2] . "</span>";
        return "<span class=\"badge bg-" . $btn[$stat_index][0] . "\"><i class='fas fa-" . $btn[$stat_index][1] . "'></i>" . $btn[$stat_index][2] . "</span>";
    }
}
