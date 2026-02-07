<?php

namespace App\Repositories;


use App\Models\Core\CaseStatus;
use App\Models\Core\CategoryTypeItem;
use Illuminate\Support\Str;

class StatusRepository
{
    /**
     * @param $state
     * @return array|int|mixed|string
     * @link use-enum https://www.php.net/manual/en/language.types.enumerations.php
     */

    public static function getTaskStatus($state)
    {
        if (is_string($state))
            $state = strtolower($state);
        $statuses = [
            'active' => 1,
            'on-hold' => 2,
            'canceled' => 3,
            'completed' => 4
        ];
        return self::checkState($state, $statuses);
    }

    public static function getStatus($state)
    {
        $statuses = [
            'inactive' => 0,
            'active' => 1,
            'deleted' => 2,
        ];
        return self::checkState($state, $statuses);
    }
    public static function getCaseType($state)
    {
        $statuses = [
            'litigation' => 1,
            'prosecution' => 2,
        ];
        return self::checkState($state, $statuses);
    }
    public static function getTaskDepartment($state)
    {
        $statuses = [
            'Legal Services' => 1,
            'Prosecutions' => 2,
            'Litigation' => 3,
            'Contracts' => 4,
            'CLS' => 5,
        ];
        return self::checkState($state, $statuses);
    }

    public static function getFileLocationState($state)
    {
        $statuses = [
            'registry' => 1,
            'issued' => 2,
        ];
        return self::checkState($state, $statuses);
    }

    public static function getPermissionType($state)
    {
        $statuses = [
            'user' => 1,
            'superadmin' => 2
        ];
        return self::checkState($state, $statuses);
    }

    public static function getIssueStatus($state)
    {
        $statuses = [
            'request' => 1,
            'issued' => 2,
            'returned' => 3,
            'rejected' => 4
        ];
        return self::checkState($state, $statuses);
    }
    public static function getCaseSettlementStatus($state)
    {
        $statuses = [
            'pending' => 0,
            'partially_paid' => 1,
            'fully_paid' => 2,
            'over_paid' => 3,
        ];
        return self::checkState($state, $statuses);
    }


    public static function getModelStatus($state)
    {
        $statuses = [
            'inactive' => 0,
            'active' => 1,
        ];
        return self::checkState($state, $statuses);
    }

    public static function getTaskPriorityStatus($state)
    {
        $statuses = [
            'High' => 1,
            'Medium' => 2,
            'Low' => 3,
        ];
        return self::checkState($state, $statuses);
    }

    public static function getLegalServicePriorityStatus($state)
    {
        $statuses = [
            'High' => 1,
            'Medium' => 2,
            'Low' => 3,
        ];
        return self::checkState($state, $statuses);
    }

    public static function getTaskReminderPeriod($state)
    {
        //0-minutes,1-hours,2-days
        $statuses = [
            'minutes' => 0,
            'hours' => 1,
            'days' => 2,
            'weeks' => 3,
            'months' => 4,
        ];
        return self::checkState($state, $statuses);
    }


    public static function getUserStatus($state)
    {
        $statuses = [
            'pending' => 0,
            'active' => 1,
            'revoked' => 2
        ];
        return self::checkState($state, $statuses);
    }

    public static function getDocumentCategory($state)
    {
        // TODO implement search by name on category type items table
        $model = CategoryTypeItem::where('name',$state)->select('id')->first();
//        $statuses = [
//            'case' => 34,
//            'contract' => 35,
//            'Lawyer' => 36,// TODO to rename to Lawyers
//            'Personal' => 37,
//        ];
        return $model->id;
    }

    public static function getCaseDocumentType($state)
    {
        $statuses = [
            'orders' => 38,
            'judgement' => 39,
            'fine' => 40,
            'proceedings' => 41,
            'decree' => 42,
            'ruling' => 43,
        ];
        return self::checkState($state, $statuses);
    }

    public static function getCaseStatus($state)
    {
        //TODO cache this requests
        $caseStatuses = CaseStatus::where('status',1)->select('id','name')->get();
        $statuses = [];
        foreach ($caseStatuses as $caseStatus)
        {
            $statuses[Str::lower($caseStatus->name)] = $caseStatus->id;
        }
        return self::checkState($state, $statuses);
    }

    public static function getCaseClosureType($state, $case_type = 'litigation')
    {
        if ($case_type == "prosecution")
            $statuses = [
                'convicted' => 1,
                'acquittal' => 2,
                'dismissed' => 4,
                'withdrawn' => 5
            ];
        else
            $statuses = [
                'closed_favorable' => 1,
                'closed_unfavorable_payable' => 2,
                'closed_unfavorable_non_payable' => 3,
                'dismissed' => 4,
                'withdrawn' => 5,
            ];
        return self::checkState($state, $statuses);
    }

    public static function getUserAuthority($state)
    {
        $statuses = [
            'normal' => 0,
            'hod' => 1,
            'cls' => 2,
        ];
        return self::checkState($state, $statuses);
    }

    public static function checkState($state, $statuses)
    {
        try {
            if (is_numeric($state))
                $statuses = array_flip($statuses);
            if (is_array($state)) {
                $states = [];
                foreach ($state as $st) {
                    $states[] = $statuses[$st];
                }
                return $states;
            }
            return $statuses[$state];
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    public static function getInvoicePaymentStatus($state): array|int|string
    {
        $statuses = [
            'unpaid'=>0,
            'paid'=>1,
            'partially_paid'=>2,
            'overdue'=>3,
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
    public static function getInvoiceStatus($state): array|int|string
    {
        $statuses = [
            'draft'=>0,
            'send'=>1
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
    public static function getInvoiceDiscountType($state): int|array|string|null
    {
        $statuses = [
            'no_discount'=>0,
            'item_level'=>1,
            'invoice_level'=>2
        ];
        if(is_numeric($state)) $statuses = array_flip($statuses);
        if(is_array($state)){
            $states  = [];
            foreach($state as $st) $states[] = $statuses[$st];

            return $states;
        }
        return $statuses[$state] ?? null;
    }

    public static function getInvoiceType($type): int|array|string|null
    {
        $all_types = [
            'invoice'=>0,
            'estimate'=>1
        ];
        if(is_numeric($type)) $all_types = array_flip($all_types);
        if(is_array($type)){
            $types  = [];
            foreach($type as $st) $types[] = $all_types[$st];

            return $types;
        }
        return $all_types[$type] ?? null;
    }

    public static function getLogRequestSource($type): array|int|string|null
    {
        $all_types = [
            'web'=>0,
            'mobile'=>1
        ];
        if(is_numeric($type))
            $all_types = array_flip($all_types);
        if(is_array($type)){
            $types  = [];
            foreach($type as $st) $types[] = $all_types[$st];

            return $types;
        }
        return $all_types[$type] ?? null;
    }

    public static function getInvoiceTaxType($state): int|array|string|null
    {
        $statuses = [
            'exclussive'=>0,
            'inclussive'=>1
        ];
        if(is_numeric($state))
            $statuses = array_flip($statuses);
        if(is_array($state)){
            $states  = [];
            foreach($state as $st) $states[] = $statuses[$st];

            return $states;
        }
        return $statuses[$state] ?? null;
    }

    public static function getPaymentStatus($state): int|array|string|null
    {
        $statuses = [
            'completed'=>1,
            'refunded'=>2
        ];
        if(is_numeric($state))
            $statuses = array_flip($statuses);
        if(is_array($state)){
            $states  = [];
            foreach($state as $st) $states[] = $statuses[$st];

            return $states;
        }
        return $statuses[$state] ?? null;
    }
}
