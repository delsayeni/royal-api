<?php

namespace Royal\Controllers\Mobile;

use Rashtell\Domain\JSON;
use Royal\Domain\Constants;
use Royal\Models\Mobile\UserModel;
use Royal\Models\Mobile\WalletModel;
use Royal\Models\Mobile\UserBalanceModel;
use Royal\Models\Mobile\RegCodeModel;
use Royal\Models\Mobile\TransferModel;
use Royal\Domain\MailHandler;
use Royal\Controllers\Mobile\Helper\LiveetFunction;
use Psr\Http\Message\ResponseInterface;
use Carbon\Carbon;
use Psr\Http\Message\ServerRequestInterface as Request;

class RoyalController 
{
  use LiveetFunction;

  public function __construct()
  {
    $this->json = new JSON();
  }

  public function doHomePage(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();
    $balance_db = new UserBalanceModel();
    $transfer_db = new TransferModel();

    $response_data = [];

    $data = $request->getParsedBody();

    $user_id = $data["userid"];

    $balance = 0;
    $received = 0;
    $sent = 0;
    $completed = 0;
    $pending = 0;

    $user_details = $user_db->where('user_id',$user_id)->first();
    $account_number = $user_details->account_number;
    $routing_number = $user_details->routing_number;

    //get balance
    $balance_query = $balance_db->where('user_id',$user_id);

    if($balance_query->exists()){
      $balance_details = $balance_query->first();
      $balance = $balance_details->amount;
    }

    //get received
    $receive_query = $transfer_db->where([
      "account_number" => $account_number,
      "routing_number" => $routing_number,
      "transfer_type" => Constants::TRANSFER_INTERNAL,
    ]);

    if($receive_query->exists()){
      $received = $receive_query->sum('transfer_amount');
    }

    //get sent
    $sent_query = $transfer_db->where([
      "user_id" => $user_id
    ]);

    if($sent_query->exists()){
      $sent = $sent_query->sum('transfer_amount');
    }

    //get completed
    $complete_query = $transfer_db->where([
      "user_id" => $user_id,
      "transfer_status" => Constants::TRANSFER_COMPLETED,
    ]);

    if($complete_query->exists()){
      $completed = $complete_query->sum('transfer_amount');
    }

    //get pending
    $pending_query = $transfer_db->where('user_id',$user_id)->where('transfer_status','!=',Constants::TRANSFER_COMPLETED);

    if($pending_query->exists()){
      $pending = $pending_query->sum('transfer_amount');
    }

    $formatted_Amount = $this->formatMoney($amount);

    //get five latest transfers

    $results = $transfer_db->where('user_id',$user_id)->offset(0)->limit(4)->orderBy('created_at', 'DESC')->get();

    foreach ($results as $result) {
      $transfer_type = $result->transfer_type;
      $transfer_amount = $result->transfer_amount;

      if ($transfer_type == Constants::TRANSFER_INTERNAL)
      {
        $tfaccountNUmber = $result->account_number;
        $tfroutingNUmber = $result->routing_number;

        //get full name with account for Account name
        $tfuser_details = $user_db->where([
          "account_number" => $tfaccountNUmber,
          "routing_number" => $tfroutingNUmber
        ])->first();

        $account_name = $tfuser_details->full_name;
        $bank_name = "Royal Executive";
      } else {
        $bank_name = $result->bank_name;
        $account_name = $result->account_name;
      }

      $transfer_status = $result->transfer_status;

      $date = (new Carbon())->parse($result->created_at); 
      $formatted_date = $date->isoFormat('MMMM Do YYYY');

      $tmp = [
        "id" => $result->transfer_id,
        "bank" => $bank_name,
        "account" => $account_name,
        "amount" => $this->formatMoney($transfer_amount),
        "date" => $formatted_date,
        "status" => $transfer_status,
        "remark" => $result->transfer_desc,
      ];

      array_push($response_data, $tmp);
    }

    $payload_data = [
      "account_number" => $account_number,
      "routing_number" => $routing_number,
      "balance" => $this->formatMoney($balance),
      "received" => $this->formatMoney($received),
      "sent" => $this->formatMoney($sent),
      "completed" => $this->formatMoney($completed),
      "pending" => $this->formatMoney($pending),
      "transfers" => $response_data
    ];

    $payload = ["statusCode" => 200, "data" => $payload_data];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function doPaymentPage(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();
    $transfer_db = new TransferModel();
    $balance_db = new UserBalanceModel();

    $response_data = [];

    $data = $request->getParsedBody();

    $user_id = $data["userid"];

    $balance = 0;
    $sent = 0;
    $completed = 0;
    $pending = 0;

    $user_details = $user_db->where('user_id',$user_id)->first();
    $account_number = $user_details->account_number;
    $routing_number = $user_details->routing_number;

    //get balance
    $balance_query = $balance_db->where('user_id',$user_id);

    if($balance_query->exists()){
      $balance_details = $balance_query->first();
      $balance = $balance_details->amount;
    }

    //get sent
    $sent_query = $transfer_db->where([
      "user_id" => $user_id
    ]);

    if($sent_query->exists()){
      $sent = $sent_query->sum('transfer_amount');
    }

    //get completed
    $complete_query = $transfer_db->where([
      "user_id" => $user_id,
      "transfer_status" => Constants::TRANSFER_COMPLETED,
    ]);

    if($complete_query->exists()){
      $completed = $complete_query->sum('transfer_amount');
    }

    //get pending
    $pending_query = $transfer_db->where('user_id',$user_id)->where('transfer_status','!=',Constants::TRANSFER_COMPLETED);

    if($pending_query->exists()){
      $pending = $pending_query->sum('transfer_amount');
    }

    $formatted_Amount = $this->formatMoney($amount);

    //get all latest transfers

    $results = $transfer_db->where('user_id',$user_id)->orderBy('created_at', 'DESC')->get();

    foreach ($results as $result) {
      $transfer_type = $result->transfer_type;
      $transfer_amount = $result->transfer_amount;

      if ($transfer_type == Constants::TRANSFER_INTERNAL)
      {
        $tfaccountNUmber = $result->account_number;
        $tfroutingNUmber = $result->routing_number;

        //get full name with account for Account name
        $tfuser_details = $user_db->where([
          "account_number" => $tfaccountNUmber,
          "routing_number" => $tfroutingNUmber
        ])->first();

        $account_name = $tfuser_details->full_name;
        $bank_name = "Royal Executive";
      } else {
        $bank_name = $result->bank_name;
        $account_name = $result->account_name;
      }

      $transfer_status = $result->transfer_status;

      $date = (new Carbon())->parse($result->created_at); 
      $formatted_date = $date->isoFormat('MMMM Do YYYY');

      $tmp = [
        "id" => $result->transfer_id,
        "bank" => $bank_name,
        "account" => $account_name,
        "amount" => $this->formatMoney($transfer_amount),
        "date" => $formatted_date,
        "status" => $transfer_status,
        "remark" => $result->transfer_desc
      ];

      array_push($response_data, $tmp);
    }

    $payload_data = [
      "balance" => $this->formatMoney($balance),
      "sent" => $this->formatMoney($sent),
      "completed" => $this->formatMoney($completed),
      "pending" => $this->formatMoney($pending),
      "transfers" => $response_data
    ];

    $payload = ["statusCode" => 200, "data" => $payload_data];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function doLocalPayment(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();
    $balance_db = new UserBalanceModel();
    $transfer_db = new TransferModel();

    $data = $request->getParsedBody();

    $user_id = $data["userid"];
    $account_number = $data["accountnumber"];
    $account_name = $data["accountname"];
    $amount = $data["amount"];
    $bankname = $data["bankname"];
    $routing_number = $data["routingnumber"];
    $account_type = $data["accounttype"];

    $transfer_type = Constants::TRANSFER_LOCAL;
    $transfer_status = Constants::TRANSFER_PENDING;

    $balance = 0;

    $sender_user_details = $user_db->where('user_id',$user_id)->first();
    $sender_name = $sender_user_details->full_name;
    $sender_email = $sender_user_details->email;

    //get balance
    $balance_query = $balance_db->where('user_id',$user_id);

    if(!$balance_query->exists()){
      $error = ["errorMessage" => "insufficient Balance to make this Transfer. Please Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $balance_details = $balance_query->first();
    $balance = $balance_details->amount;

    if($balance < $amount)
    {
      $error = ["errorMessage" => "insufficient Balance. Please Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $newBalance = $balance - $amount;

    $self_user_query = $user_db->where(
    [
      'user_id' => $user_id,
      'account_number' => $account_number,
      'routing_number' => $routing_number,
      'account_status' => Constants::USER_STATUS_ENABLED
    ]);

    if($self_user_query->exists())
    {
      $error = ["errorMessage" => "Sorry, you Cannot Send payments to yourself. Please Try Again with another account details", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    //check if an internal payment was sent instead
    $user_query = $user_db->where(
    [
      'account_number' => $account_number,
      'routing_number' => $routing_number,
      'account_status' => Constants::USER_STATUS_ENABLED
    ]);

    if($user_query->exists())
    {
      //means do process for internal transfer
      $user_details = $user_query->first();
      $receiver_user_id = $user_details->user_id;
      $receiver_email = $user_details->email;
      $receiver_name = $user_details->full_name;

      $receiver_balance_query = $balance_db->where('user_id',$receiver_user_id);

      if($receiver_balance_query->exists()){
        $balance_details = $receiver_balance_query->first();
        $receiver_balance = $balance_details->amount;
        $receiver_balance = $receiver_balance + $amount;

        $receiver_balance_query->update(["amount" => $receiver_balance]);
      }else {
        $balance_db->create([
          "user_id" => $receiver_user_id,
          "amount" => $amount,
        ]);
      }

      $transfer_type = Constants::TRANSFER_INTERNAL;
      $transfer_status = Constants::TRANSFER_COMPLETED;

      //send email
      $emailParams = [
        "username" => $receiver_name,
        "sender" => $sender_name,
        "amount" => $this->formatMoney($amount),
      ];

      $mail = new MailHandler(3,$receiver_email, $emailParams);

      ["error" => $error, "success" => $success] = $mail->sendMail();
    }

    //send Sender Email
    $emailParams = [
      "username" => $sender_name,
      "receiver" => $account_name,
      "accountno" => $account_number,
      "transfertype" => $transfer_type." TRANSFER",
      "status" => $transfer_status,
      "amount" => $this->formatMoney($amount),
    ];

    $mail = new MailHandler(4,$sender_email, $emailParams);

    ["error" => $error, "success" => $success] = $mail->sendMail();

    $transfer_db->create([
      "user_id" => $user_id,
      "transfer_type" => $transfer_type,
      "transfer_amount" => $amount,
      "bank_name" => $bankname,
      "account_name" => $account_name,
      "account_number" => $account_number,
      "routing_number" => $routing_number,
      "sort_code"=> "",
      "country" => "",
      "transfer_desc" => "",
      "transfer_status" => $transfer_status,
    ]);

    $balance_query->update(["amount" => $newBalance]);

    $payload = ["statusCode" => 200, "successMessage" => "Payment Sent Successfully"];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function doInternalPayment(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();
    $balance_db = new UserBalanceModel();
    $transfer_db = new TransferModel();

    $data = $request->getParsedBody();

    $user_id = $data["userid"];
    $account_number = $data["accountnumber"];
    $amount = $data["amount"];
    $routing_number = $data["routingnumber"];

    $transfer_type = Constants::TRANSFER_INTERNAL;
    $transfer_status = Constants::TRANSFER_COMPLETED;

    $balance = 0;

    $sender_user_details = $user_db->where('user_id',$user_id)->first();
    $sender_name = $sender_user_details->full_name;
    $sender_email = $sender_user_details->email;

    //get balance
    $balance_query = $balance_db->where('user_id',$user_id);

    if(!$balance_query->exists()){
      $error = ["errorMessage" => "insufficient Balance to make this Transfer. Please Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $balance_details = $balance_query->first();
    $balance = $balance_details->amount;

    if($balance < $amount)
    {
      $error = ["errorMessage" => "insufficient Balance. Please Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $newBalance = $balance - $amount;

    $self_user_query = $user_db->where(
    [
      'user_id' => $user_id,
      'account_number' => $account_number,
      'routing_number' => $routing_number,
      'account_status' => Constants::USER_STATUS_ENABLED
    ]);

    if($self_user_query->exists())
    {
      $error = ["errorMessage" => "Sorry, you Cannot Send payments to yourself. Please Try Again with another account details", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $user_query = $user_db->where(
    [
      'account_number' => $account_number,
      'routing_number' => $routing_number,
      'account_status' => Constants::USER_STATUS_ENABLED
    ]);

    if(!$user_query->exists())
    {
      $error = ["errorMessage" => "No Account Found with Account Number & Routing. Please check the account details & Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    //do process for internal transfer
    $user_details = $user_query->first();
    $receiver_user_id = $user_details->user_id;
    $receiver_email = $user_details->email;
    $receiver_name = $user_details->full_name;

    $receiver_balance_query = $balance_db->where('user_id',$receiver_user_id);

    if($receiver_balance_query->exists()){
      $balance_details = $receiver_balance_query->first();
      $receiver_balance = $balance_details->amount;
      $receiver_balance = $receiver_balance + $amount;

      $receiver_balance_query->update(["amount" => $receiver_balance]);
    }else {
      $balance_db->create([
        "user_id" => $receiver_user_id,
        "amount" => $amount,
      ]);
    }

    //send email
    $emailParams = [
      "username" => $receiver_name,
      "sender" => $sender_name,
      "amount" => $this->formatMoney($amount),
    ];

    $mail = new MailHandler(3,$receiver_email, $emailParams);

    ["error" => $error, "success" => $success] = $mail->sendMail();
    

    //send Sender Email
    $emailParams = [
      "username" => $sender_name,
      "receiver" => $receiver_name,
      "accountno" => $account_number,
      "transfertype" => $transfer_type." TRANSFER",
      "status" => $transfer_status,
      "amount" => $this->formatMoney($amount),
    ];

    $mail = new MailHandler(4,$sender_email, $emailParams);

    ["error" => $error, "success" => $success] = $mail->sendMail();

    $transfer_db->create([
      "user_id" => $user_id,
      "transfer_type" => $transfer_type,
      "transfer_amount" => $amount,
      "bank_name" => '',
      "account_name" => '',
      "account_number" => $account_number,
      "routing_number" => $routing_number,
      "sort_code"=> "",
      "country" => "",
      "transfer_desc" => "",
      "transfer_status" => $transfer_status,
    ]);

    $balance_query->update(["amount" => $newBalance]);

    $payload = ["statusCode" => 200, "successMessage" => "Payment Sent Successfully"];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function doInternationalPayment(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();
    $balance_db = new UserBalanceModel();
    $transfer_db = new TransferModel();

    $data = $request->getParsedBody();

    $user_id = $data["userid"];
    $account_number = $data["accountnumber"];
    $account_name = $data["accountname"];
    $amount = $data["amount"];
    $bankname = $data["bankname"];
    $routing_number = $data["routingnumber"];
    $sort_code = $data["sortcode"];
    $country = $data["country"];

    $transfer_type = Constants::TRANSFER_INTERNATIONAL;
    $transfer_status = Constants::TRANSFER_PENDING;

    $balance = 0;

    $sender_user_details = $user_db->where('user_id',$user_id)->first();
    $sender_name = $sender_user_details->full_name;
    $sender_email = $sender_user_details->email;

    //get balance
    $balance_query = $balance_db->where('user_id',$user_id);

    if(!$balance_query->exists()){
      $error = ["errorMessage" => "insufficient Balance to make this Transfer. Please Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $balance_details = $balance_query->first();
    $balance = $balance_details->amount;

    if($balance < $amount)
    {
      $error = ["errorMessage" => "insufficient Balance. Please Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $newBalance = $balance - $amount;

    $self_user_query = $user_db->where(
    [
      'user_id' => $user_id,
      'account_number' => $account_number,
      'routing_number' => $routing_number,
      'account_status' => Constants::USER_STATUS_ENABLED
    ]);

    if($self_user_query->exists())
    {
      $error = ["errorMessage" => "Sorry, you Cannot Send payments to yourself. Please Try Again with another account details", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    //send Sender Email
    $emailParams = [
      "username" => $sender_name,
      "receiver" => $account_name,
      "accountno" => $account_number,
      "transfertype" => $transfer_type." TRANSFER",
      "status" => $transfer_status,
      "amount" => $this->formatMoney($amount),
    ];

    $mail = new MailHandler(4,$sender_email, $emailParams);

    ["error" => $error, "success" => $success] = $mail->sendMail();

    $transfer_db->create([
      "user_id" => $user_id,
      "transfer_type" => $transfer_type,
      "transfer_amount" => $amount,
      "bank_name" => $bankname,
      "account_name" => $account_name,
      "account_number" => $account_number,
      "routing_number" => $routing_number,
      "sort_code"=> $sort_code,
      "country" => $country,
      "transfer_desc" => "",
      "transfer_status" => $transfer_status,
    ]);

    $balance_query->update(["amount" => $newBalance]);

    $payload = ["statusCode" => 200, "successMessage" => "Payment Sent Successfully"];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function doProfilePage(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();

    $data = $request->getParsedBody();

    $user_id = $data["userid"];

    $user_query = $user_db->where('user_id',$user_id);

    if(!$user_query->exists()){
      $error = ["errorMessage" => "User not Found. Please Sign In and try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $user_details = $user_query->first();
    $address = $user_details->address;
    $state = $user_details->state;
    $country = $user_details->country;

    $payload_data = [
      "address" => $address,
      "state" => $state,
      "country" => $country
    ];

    $payload = ["statusCode" => 200, "data" => $payload_data];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function doUpdateProfile(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();

    $data = $request->getParsedBody();

    $user_id = $data["userid"];
    $address = $data["address"];
    $name = $data["name"];
    $state = $data["state"];
    $country = $data["country"];

    $user_query = $user_db->where('user_id',$user_id);

    if(!$user_query->exists()){
      $error = ["errorMessage" => "User not Found. Please Sign In and try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $user_query->update([
      "full_name" => $name,
      "address" => $address,
      "state" => $state,
      "country" => $country,
    ]);

    $payload = ["statusCode" => 200, "successMessage" => "Profile Update Successful"];
    return $this->json->withJsonResponse($response, $payload);
  }


  public function doChangePassword(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();

    $data = $request->getParsedBody();

    $user_id = $data["userid"];
    $password = $data["password"];
    $rpassword = $data["rpassword"];

    if($password !== $rpassword) {
      $error = ["errorMessage" => "Mismatched Password. Please Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $user_query = $user_db->where('user_id',$user_id);

    if(!$user_query->exists()){
      $error = ["errorMessage" => "User not Found. Please Sign In and try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    //update Password
    $hashed_password = hash('sha256', $password);

    $user_query->update([
      "password" => $hashed_password,
    ]);

    $payload = ["statusCode" => 200, "successMessage" => "Password Update Successful"];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function doAdminPage(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();
    $transfer_db = new TransferModel();
    $regcode_db = new RegCodeModel();

    $user_response = [];
    $transfer_response = [];
    $regcode_response = [];

    $data = $request->getParsedBody();

    $user_id = $data["userid"];

    if($user_id !== '1'){
      $error = ["errorMessage" => "Not Allowed", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    //get all users

    $userResults = $user_db->get();

    foreach ($userResults as $result) {
      $db_user_id = $result->user_id;

      if ($db_user_id == $user_id) {
        continue;
      }

      $tmp = [
        "id" => $db_user_id,
        "fullname" => $result->full_name,
        "account_number" => $result->account_number,
        "routing_number" => $result->routing_number,
        "email" => $result->email,
        "status" => $result->account_status,
      ];
      array_push($user_response, $tmp);
    }

    //get all transfers
    $transferResults = $transfer_db->where('user_id','!=',$user_id)->where('transfer_type','!=',Constants::TRANSFER_INTERNAL)->orderBy('created_at', 'DESC')->get();

    foreach ($transferResults as $result) {
      $db_user_id = $result->user_id;

      $tfUserDetails = $user_db->where('user_id',$db_user_id)->first();
      $senderName = $tfUserDetails->full_name;

      $tmp = [
        "id" => $result->transfer_id,
        "user" => $senderName,
        "bank" => $result->bank_name,
        "amount" => $this->formatMoney($result->transfer_amount),
        "account_name" => $result->account_name,
        "type" => $result->transfer_type,
        "status" => $result->transfer_status,
      ];

      array_push($transfer_response, $tmp);
    }

    //get all regcodes
    $codeResults = $regcode_db->get();

    foreach ($codeResults as $result) {

      $tmp = [
        "id" => $result->reg_code_id,
        "reg_code" => $result->reg_code,
        "status" => $result->reg_status
      ];

      array_push($regcode_response, $tmp);
    }

    $payload_data = [
      "users" => $user_response,
      "transfers" => $transfer_response,
      "regcodes" => $regcode_response
    ];

    $payload = ["statusCode" => 200, "data" => $payload_data];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function doChangeUserStatus(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();

    $data = $request->getParsedBody();

    $user_id = $data["userid"];
    $status = $data["newstatus"];

    $user_query = $user_db->where('user_id',$user_id);

    if(!$user_query->exists()){
      $error = ["errorMessage" => "User not Found. Please Sign In and try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $user_query->update([
      "account_status" => $status,
    ]);

    $payload = ["statusCode" => 200, "successMessage" => "Account Update Successful"];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function deleteRegCode(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $regcode_db = new RegCodeModel();

    $data = $request->getParsedBody();

    $reg_id = $data["regid"];

    $reg_query = $regcode_db->where('reg_code_id',$reg_id);

    if(!$reg_query->exists()){
      $error = ["errorMessage" => "Record Not Found. Please try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $reg_query->forceDelete();

    $payload = ["statusCode" => 200, "successMessage" => "Registration Code Deleted Successfully"];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function generateRegCode(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $regcode_db = new RegCodeModel();

    $reg_code = rand(000000, 999999);

    $regcode_db->create([
      "reg_code" => $reg_code
    ]);

    $payload = ["statusCode" => 200, "successMessage" => "Registration Code Created Successfully"];
    return $this->json->withJsonResponse($response, $payload);
  }

  public function updatetransferstatus(Request $request, ResponseInterface $response): ResponseInterface
  {
    //declare needed class objects
    $user_db = new UserModel();
    $transfer_db = new TransferModel();

    $data = $request->getParsedBody();

    $transfer_id = $data["transferid"];
    $status = $data["status"];
    $desc = $data["desc"];

    $transfer_query = $transfer_db->where('transfer_id',$transfer_id);

    if(!$transfer_query->exists()){
      $error = ["errorMessage" => "Record Not Found. Please try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $transfer_details = $transfer_query->first();
    $user_id = $transfer_details->user_id;
    $amount = $transfer_details->transfer_amount;
    $account_name = $transfer_details->account_name;
    $account_number = $transfer_details->account_number;

    $user_details = $user_db->where('user_id',$user_id)->first();
    $sender_name = $user_details->full_name;
    $sender_email = $user_details->email;

    //send Sender Email
    $emailParams = [
      "username" => $sender_name,
      "receiver" => $account_name,
      "accountno" => $account_number,
      "status" => $status,
      "remarks" => $desc,
      "amount" => $this->formatMoney($amount)
    ];

    $mail = new MailHandler(5,$sender_email, $emailParams);

    ["error" => $error, "success" => $success] = $mail->sendMail();

    //update the status on the transfer
    $transfer_query->update(["transfer_status" => $status,"transfer_desc" => $desc]);

    $payload = ["statusCode" => 200, "successMessage" => "Transfer Status Updated Successfully"];
    return $this->json->withJsonResponse($response, $payload);
  }
}
