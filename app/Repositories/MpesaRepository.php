<?php


namespace App\Repositories;


use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MpesaRepository
{

    // STK push method to trigger payment on user's phones
    public function userMpesaPayment($amount = null, $PartyA = null,$billRef,$callback_url = null)
    {

//        Storage::disk('local')->put('payment.txt', $amount.' '. $PartyA. ' ');

//        dd(Carbon::rawParse('now')->format('YmdHms'));
        // the stk magic happens here
        $url = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';


        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->getAccessToken()));


        $curl_post_data = [
            //Fill in the request parameters with valid values
            'BusinessShortCode' => 503100,
            'Password' => $this->lipaNaMpesaPassword(),
//            'Timestamp' => Carbon::rawParse('now')->format('YmdHms'),
            'Timestamp' => Carbon::rawParse('now')->format('YmdHms'),
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $PartyA,
            'PartyB' => 503100,
            'PhoneNumber' => $PartyA,
            'CallBackURL' => $callback_url,
            'AccountReference' => $billRef,
            'TransactionDesc' => 'Test Payment From Broker Portal'
        ];

        $data_string = json_encode($curl_post_data);
//        dd($curl_post_data);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

        $curl_response = curl_exec($curl);

//        dd($curl_response);
        return $curl_response;
    }


    // safaricom access token method
    public function getAccessToken()
    {


        $consumer_key="Oq1q365xIZFMjmd8x2z3O56Gmt9YjUtQ";
        $consumer_secret="W2d1KTqjCuuvWFkO";
        $credentials = base64_encode($consumer_key.":".$consumer_secret);

        // curl url
        $url = "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$credentials));
        curl_setopt($curl, CURLOPT_HEADER,false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $curl_response = curl_exec($curl);
        $access_token=json_decode($curl_response);
//        dd($access_token);
        return $access_token->access_token;
    }

    public function lipaNaMpesaPassword()
    {
        $lipa_time = Carbon::rawParse('now')->format('YmdHms');
//        dd($lipa_time);
        $passkey = "b36e7cd9489edf9baccee7828f0976d8495943a1ffcc10462efb7099a24618bd";
        $BusinessShortCode = 503100;
        $timestamp =$lipa_time;

        $lipa_na_mpesa_password = base64_encode($BusinessShortCode.$passkey.$timestamp);
//        dd($lipa_na_mpesa_password);
        return $lipa_na_mpesa_password;
    }
}
