<?php

namespace App\Repositories;

use App\Models\Core\Settings\DynamicAttribute;
use URL;
use Auth;

class FormRepository
{
    public static function replaceLinks($text)
    {
        $reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
        if (preg_match($reg_exUrl, $text, $url)) {
            return preg_replace($reg_exUrl, '<a target="_blank" href="' . $url[0] . '">' . $url[0] . '</a> ', $text);
        } else {
            return $text;
        }
    }

    public function autoGenerate($elements, $action = null, $classes = [], $model = null)
    {
        $spoofed_method = '';
        $info = '';

        $elements_count = $this->countElements($elements);

        if (isset($model['id']) && @$model['id'] != 0)
            $info = '<div class="alert alert-info">Update Details</div>';

        if ($model) {
            if (!is_array($model)) {
                $model = $model->toArray();
            }

            $action = $action . '/' . $model['id'];
            $spoofed_method = method_field('put');
        }
        $classes[] = 'ajax-post';
        $classes[] = 'model_form_id';

        $textareas = ['description', 'summary', 'reason', 'email_message', 'sms_message', 'html','request_notes',
            'comment', "testimonial", 'about', 'address', 'postal_address', 'message','remarks', "outcome",
            'security_credential', 'reason_rejected', 'note', 'instructions','notes','reason_for_declining','reason_for_issuance','issue_details'];
        $selects = [];
        $selects['invoice_type'] = ["invoice", "estimate"];
        $selects['ownership_type'] = ["firm_internal", "client_owned"];

        $selects_val['tax_type'] = [
            0=>'Non-Inclusive',
            1=>'Inclusive',
        ];
        $selects_val['discount_type'] =
            [
                0=>"no_discount",
                1=>"per_item",
                2=>"per_invoice"
            ];
        $selects['user'] = [];
        $selects['units'] = [];
        $selects['unit'] = [];
        $selects['coy'] = [];
        $selects['category'] = [];
        $selects['issued_by'] = [];
        $selects['parent_category'] = [];
        $selects['practice_area'] = [];
        $selects['category_type'] = [];
        $selects['property_models'] = [];
        $selects['technician'] = [];
        $selects['received_by'] = [];
        $selects['type'] = ["lawyer", "clerk",'client'];
        $selects['rank'] = ["civ"];
        $selects['inc_type'] = ["amount", "percentage"];
        $selects['position'] = ["client", "opponent",'other'];

        $selects_val = [];


        $selects_val['owner'] = [
            '0' => 'Agent',
            '1' => 'Owner'
        ];


        $selects_val['state'] = [
            '' => 'Please Select',
            'unserviceable' => 'Unserviceable',
            'serviceable' => 'Serviceable'
        ];

        $selects_val['is_paid'] = [
            '0' => 'No',
            '1' => 'Yes'
        ];
        $selects_val['is_returnable'] = [ //0-No, 1-Yes
            '1' => 'Yes',
            '0' => 'No'
        ];

        $selects_val['issue_type'] = [
            '' => 'Please Select',
            'NML' => 'Normal Issue',
            'TL' => 'Temporary Loan'
        ];

        $selects_val['month_field'] = [
            '1' => 'January',
            '2' => 'February',
            '3' => 'March',
            '4' => 'April',
            '5' => 'May',
            '6' => 'June',
            '7' => 'July',
            '8' => 'August',
            '9' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December'
        ];
        $selects_val['downloadable'] = [
            '0' => 'No',
            '1' => 'Yes'
        ];
        $selects_val['default'] = [
            '0' => 'No',
            '1' => 'Yes',
        ];

        $selects_val['case_type_id'] = [
            '1' => 'Litigation',
            '2' => 'Prosecution',
        ];
        $selects_val['permission_type'] = [
            '1' => 'Normal',
            '2' => 'Super Admin',
        ];
        $selects_val['file_type'] = [
            '' => 'Please Select',
            'Signed Estimate' => 'Signed Estimate',
            'Other' => 'Other',
        ];
        $selects_val['attribute_type'] = [
            'text' => 'Text',
            'textarea' => 'TextArea',
            'select' => 'Select',
            'multiselect' => 'MultiSelect'
        ];

        if (in_array('field_type', $elements)) {
            $selects_val['field_type'] = [
                'text' => 'Text',
                'long_text' => 'Long Text',
                'select' => 'Select',
                'multi_select' => 'Multi Select'
            ];
        }
        if (in_array('is_required', $elements)) {
            $selects_val['is_required'] = [
                '0' => 'No',
                '1' => 'Yes'
            ];
        }
        if (in_array('tax_status', $elements)) {
            $selects_val['tax_status'] = [
                'no_tax' => 'No Tax',
                'tax_included' => 'Tax Included',
                'plus_tax' => 'Plus Tax'
            ];
        }

        $passwords = ['password', 'password_confirmation'];
        $selects['short_code_type'] = ['till_number', 'paybill'];
        $selects['environment'] = ['production', 'sandbox'];
        $selects['gender'] = ['Male', 'Female'];
        $selects['recurring'] = ['yes', 'no'];
        $selects['recurring_period'] = ['weekly', 'monthly', 'quarterly', 'semi-annually', 'annually'];
        $selects['currency'] = ['USD', 'KES'];
        $selects_val['currency_code'] = [];

        $selects['region'] = ['central', 'coast', 'eastern', 'nairobi', 'north eastern', 'nyanza', 'rift valley', 'western'];
        $class = 'ajax-post';
        $enctype = '';
        $files = ['registration_file', 'image', 'images', 'logo', 'file', "site_icon",'icon', 'profile_pic', 'avatar', 'default_image', 'book_file'];
        foreach ($files as $file) {
            if (in_array($file, $elements)) {
                $enctype = 'multipart/form-data';
                $classes = [];
                $classes[] = 'model_form_id';
                $classes[] = "file-form";
                break;
            }
        }
        if ($elements_count > 6) {
            $classes[] = 'row';
        }
        $classes = implode(' ', $classes);
        $checkboxes['is_sensitive'] = ['yes'];
        $checkboxes['is_feedback'] = ['yes'];
        $checkboxes['is_warranty'] = ['yes'];
        $checkboxes['Escalation'] = ['Send mail alert?'];

        $checkboxes['downloadable'] = ['yes', 'no'];

        $form_string = '';
        $id = 'model_form_id';

        $form_string .= $info . '<form id="" enctype="' . $enctype . '" class="' . $classes . '" method="post" action="' . url($action) . '">
           <input type="hidden" name="id" value="' . @$model['id'] . '">
           <input type="hidden" name="entity_name">';
        if (isset($elements['form_model'])) {
            $form_string .= '<input type="hidden" name="form_model" value="' . $elements['form_model'] . '">';
            unset($elements['form_model']);
        }

        $form_string .= $spoofed_method;
        $halve = round($elements_count / 2, 0);
        if ($elements_count > 6) {
            $form_string .= '<div class="col-md-6">';
        }
        $form_string .= csrf_field();
        $input_masks = [];
        $input_masks['start_time'] = '00:00:00';
        $no = 0;

        foreach ($elements as $element_data => $element) {
            if (strpos($element_data, 'hidden_') === false && strpos($element, 'hidden_') === false) {

            } else {
                if (strpos($element, 'hidden_') === false) {
                    $form_string .= '<input type="hidden" name="' . str_replace('hidden_', '', $element_data) . '" value="' . $element . '">';
                    unset($elements[$element_data]);
                } else {
                    $form_string .= '<input type="hidden" name="' . str_replace('hidden_', '', $element) . '" value="">';
                }
                continue;

            }
            $no++;
            $array = explode('_', $element);
            $form_string .= '<div style="margin-top:10px" class="form-group ' . $element . '">';
            $form_string .= '<div class="fg-line">';
            $label_strings = str_replace('ipoooid', '', $element);
            if ($element == 'terms') {
                // $form_string.='<label class="fg-label control-label">'.ucwords(str_replace('_',' ','Accept temrs and conditions?')).'</label>';
            } else {
                $label_strings = str_replace('_id', ' ', $label_strings);
                $form_string .= '<label class="fg-label control-label label_' . $element . '">' . ucwords(str_replace('_', ' ', $label_strings)) . '</label>';
            }


            if (in_array($element, $textareas)) {
                $form_string .= '<textarea name="' . $element . '" class="form-control">' . @$model[$element] . '</textarea>';
            } elseif ($element == 'terms') {
                $form_string .= '<input name="' . $element . '" value="yes" type="checkbox" checked="true">';
                $form_string .= '<label class="fg-label control-label">' . ucwords(str_replace('_', ' ', 'I Accept terms and conditions')) . '</label>';
            }  elseif ($array[count($array) - 1] == 'id' && isset($selects[$element]) == false && isset($selects_val[$element]) == false && $element !== "national_id") {
                $form_string .= '<div class="select">';
                $data_model = '';
                $add_class = '';
                if (!is_integer($element_data)) {
                    $data_model = ' data-model="' . $element_data . '" ';
                    $add_class = "auto-fetch-select";
                }

                $form_string .= '<select ' . $data_model . ' name="' . $element . '" class="form-control select' . $add_class . '">' . @$model[$element] . '<option value="">Select...</a></select>';
                $form_string .= '</div>';
            } elseif ($array[count($array) - 1] == 'file') {
                $form_string .= '<input type="file" name="' . $element . '" class="form-control">';
            } elseif (in_array($element, $files)) {
                $form_string .= '<input type="file" name="' . $element . '" class="form-control">';
            } elseif (in_array($element, $passwords)) {
                $form_string .= '<input type="password" name="' . $element . '" class="form-control">';
            } elseif (isset($selects[$element])) {
                $form_string .= '<div class="select">';
                $form_string .= '<select name="' . $element . '" class="form-control">';
                foreach ($selects[$element] as $option) {
                    if (@$model[$element] == $option) {
                        $form_string .= '<option selected value="' . $option . '">' . ucwords(str_replace('_', ' ', $option)) . '</option>';
                    } else {
                        $form_string .= '<option value="' . $option . '">' . ucwords(str_replace('_', ' ', $option)) . '</option>';
                    }
                }
                $form_string .= '</select>';
                $form_string .= '</div>';
            } elseif (isset($selects_val[$element])) {
                $form_string .= '<div class="select">';
                $form_string .= '<select name="' . $element . '" class="form-control">';
                foreach ($selects_val[$element] as $key => $value) {
                    if (@$model[$element] == $key) {
                        $form_string .= '<option selected value="' . $key . '">' . ucwords($value) . '</option>';
                    } else {
                        $form_string .= '<option value="' . $key . '">' . ucwords($value) . '</option>';
                    }
                }
                $form_string .= '</select>';
                $form_string .= '</div>';
            } elseif (isset($checkboxes[$element])) {
                $form_string .= '<div class="checkboxes">';
                foreach ($checkboxes[$element] as $checkbox) {
                    if (@$model[$element] == $checkbox) {
                        $form_string .= '<input checked class="" type="checkbox" name="' . $element . '[]" value="' . strtolower(str_replace(' ', '_', $checkbox)) . '"> ' . ucwords($checkbox) . '<br/>';
                    } else {
                        $form_string .= '<input class="" type="checkbox" name="' . $element . '[]" value="' . strtolower(str_replace(' ', '_', $checkbox)) . '"> '  . ucwords($checkbox) . '<br/>';
                    }
                }
                $form_string .= '</div>';
            } elseif (isset($radioboxes[$element])) {
                $form_string .= '<div class="checkboxes">';
                foreach ($radioboxes[$element] as $radiobox) {
                    if (@$model[$element] == $radiobox) {
                        $form_string .= '<input class="" type="radio" name="' . $element . '[]" value="' . strtolower(str_replace(' ', '_', $radiobox)) . '">' . ucwords($radiobox) . '<br/>';
                    } else {
                        $form_string .= '<input class="" type="radio" name="' . $element . '[]" value="' . strtolower(str_replace(' ', '_', $radiobox)) . '">' . ucwords($radiobox) . '<br/>';
                    }
                }
                $form_string .= '</div>';
            } else {
                if (isset($input_masks[$element])) {
                    $form_string .= '<input value="' . @$model[$element] . '" type="text" data-mask="' . $input_masks[$element] . '" name="' . $element . '" class="form-control input-mask">';

                } else {
                    if (strpos($element,'_time') !== false){
                        $element =str_replace('_time','',$element);
                        $form_string .= '<input value="' . @$model[$element] . '" type="datetime-local" name="' . strtolower($element) . '" class="form-control">';
                    }
                    else if (strpos($element,'_date') !== false){
                        $element =str_replace('_date','',$element);
                        $form_string .= '<input value="' . @$model[$element] . '" type="date" name="' . strtolower($element) . '" class="form-control">';
                    }

                    else
                    $form_string .= '<input value="' . @$model[$element] . '" type="text" name="' . strtolower($element) . '" class="form-control">';

                }
            }
            $form_string .= '</div>';
            $form_string .= '</div>';
            if ($elements_count > 6)
                if ($no == $halve || $no == $halve * 2) {
                    $form_string .= '</div>';
                    $form_string .= '<div class="col-md-6">';
                }


        }
        if ($elements_count > 6)
            $form_string .= '</div>';

        $form_string .= '<div class="form-group row col-md-12">

<div class="col-md-12 mt-2">
<button  type="submit" class="btn btn-info btn-raised submit-btn float-end"><i class="fa fa-check-circle fa-1x"></i> <b>Submit</b></button>
</div>
</div>';
        $form_string .= '</form>';
        return $form_string;
    }

    public function countElements($all_elements)
    {
        $count = 0;
        $hidden_fields = [];
        $fields = [];
        if (array_key_exists('form_model', $all_elements))
            unset($all_elements['form_model']);
        foreach ($all_elements as $element_data => $element) {
            if (strpos($element_data, 'hidden_') === false && strpos($element, 'hidden_') === false) {
                $count += 1;
            } else {
//                if (strpos($element, 'hidden_') === false)
//                    $count -= 1;
            }
        }
        return $count;
    }

}
