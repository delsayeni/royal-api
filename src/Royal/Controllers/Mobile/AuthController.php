<?php

namespace Royal\Controllers\Mobile;

use Rashtell\Domain\JSON;
use Royal\Domain\Constants;
use Royal\Models\Mobile\RegCodeModel;
use Royal\Models\Mobile\TempUserModel;
use Royal\Models\Mobile\UserModel;
use Royal\Models\Mobile\OtpCodeModel;
use Royal\Domain\MailHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
  public function __construct()
  {
    $this->json = new JSON();
  }

  public function doRegisterConfirm(Request $request, ResponseInterface $response): ResponseInterface
  {

    //declare needed class objects
    $regCode_db = new RegCodeModel();

    $data = $request->getParsedBody();

    $regcode = $data["regcode"];


    $reg_query = $regCode_db->where(
      [
        'reg_code' => $regcode,
        'reg_status' => Constants::REG_STATUS_UNUSED
      ]);

    if (!$reg_query->exists())
    {
      $error = ["errorMessage" => "Registration Code Not Found or it has been used. Please Try Again", "statusCode" => 400];

      return $this->json->withJsonResponse($response, $error);
    }

    $reg_query->update(["reg_status" => Constants::REG_STATUS_USED]);

    $payload = ["statusCode" => 200, "successMessage" => "Registration Code verified"];

    return $this->json->withJsonResponse($response, $payload);
  }

  public function Register(Request $request, ResponseInterface $response): ResponseInterface
  {

    //declare needed class objects
    $temp_db = new TempUserModel();
    $otp_db = new OtpCodeModel();
    $user_db = new UserModel();


    $data = $request->getParsedBody();

    $firstname = $data["firstname"];
    $lastname = $data["lastname"];
    $address = $data["address"];
    $state = $data["state"];
    $country = $data["country"];
    $email = $data["conf"];
    $password = $data["temp"];

    $fullname = $firstname ." ".$lastname;

    $user_query = $user_db->where(
    [
      'email' => $email
    ]);

    if ($user_query->exists())
    {
      $error = ["errorMessage" => "User with this Email Exists. Please Try Again", "statusCode" => 400];

      return $this->json->withJsonResponse($response, $error);
    }

    $reg_query = $temp_db->where(
    [
      'email' => $email
    ]);

    //create token to be sent to user
    $token = rand(0000, 9999);
    $otp_db->create([
        "otp_code" => $token,
        "use_type" => Constants::TOKEN_TYPE_REG
    ]);

    //create user in db
    $crypt_password = hash('sha256', $password);

    if (!$reg_query->exists())
    {
      $account_number = rand(0000000000, 9999999999);
      $routing_number = rand(00000000, 99999999);

      $temp_db->create([
        "full_name" => $fullname,
        "email" => $email,
        "password" => $crypt_password,
        "address" => $address,
        "state" => $state,
        "country" => $country,
        "account_number" => $account_number,
        "routing_number" => $routing_number
      ]);
    }else {
      $reg_query->update([
        "full_name" => $fullname,
        "email" => $email,
        "password" => $crypt_password,
        "address" => $address,
        "state" => $state,
        "country" => $country
      ]);
    }

    //send Email
    $emailParams = [
      "username" => $fullname,
      "otp" => $token
    ];

    $mail = new MailHandler(1,$email, $emailParams);

    ["error" => $error, "success" => $success] = $mail->sendMail();

    $payload = ["statusCode" => 200, "successMessage" => "Registration Token Sent"];

    return $this->json->withJsonResponse($response, $payload);
  }

  public function confirmToken(Request $request, ResponseInterface $response): ResponseInterface
  {

    //declare needed class objects
    $temp_db = new TempUserModel();
    $otp_db = new OtpCodeModel();
    $user_db = new UserModel();


    $data = $request->getParsedBody();

    $token = $data["token"];
    $email = $data["email"];

    $otp_query = $otp_db->where(
    [
      'otp_code' => $token,
      'use_type' => Constants::TOKEN_TYPE_REG,
      'code_status' => Constants::REG_STATUS_UNUSED
    ]);

    if (!$otp_query->exists())
    {
      $error = ["errorMessage" => "OTP Token Provided does not exist or has been used. Please Try Again", "statusCode" => 400];

      return $this->json->withJsonResponse($response, $error);
    }

    $otp_query->update(["code_status" => Constants::REG_STATUS_USED]);

    $temp_details = $temp_db->where('email',$email)->first();
    $full_name = $temp_details->full_name;
    $password = $temp_details->password;
    $address = $temp_details->address;
    $state = $temp_details->state;
    $country = $temp_details->country;
    $account_number = $temp_details->account_number;
    $routing_number = $temp_details->routing_number;

    $user_db->create([
      "full_name" => $full_name,
      "email" => $email,
      "password" => $password,
      "address" => $address,
      "state" => $state,
      "country" => $country,
      "account_number" => $account_number,
      "routing_number" => $routing_number
    ]);

    //remove temp data
    $temp_db->where('email', $email)->forceDelete();

    //get user id
    $user_details = $user_db->where('email',$email)->first();
    $user_id = $user_details->user_id;
    $user_name = $user_details->full_name;

    $data_to_view = [
      "user_id" => $user_id,
      "fullname" => $user_name
    ];

    $payload = ["statusCode" => 200, "data" => $data_to_view];

    return $this->json->withJsonResponse($response, $payload);
  }

  public function Login(Request $request, ResponseInterface $response): ResponseInterface
  {

    //declare needed class objects
    $user_db = new UserModel();

    $data = $request->getParsedBody();

    $email = $data["email"];
    $password = $data["password"];

    $hashed_password = hash('sha256', $password);


    $user_query = $user_db->where([
        "email" => $email,
        "account_status" => Constants::USER_STATUS_ENABLED
      ]);

    if (!$user_query->exists()) {
      $error = ["errorMessage" => "Email Address not Recognised or Account Disabled. Please contact support if you are sure you have an account with this email", "statusCode" => 400];

      return $this->json->withJsonResponse($response, $error);
    }

    $user_details = $user_query->first();

    $db_password = $user_details->password;
    $user_id = $user_details->user_id;
    $user_name = $user_details->full_name;

    if ($hashed_password !== $db_password) {
      $error = ["errorMessage" => "Password Not Correct. Please Try Again", "statusCode" => 400];
      return $this->json->withJsonResponse($response, $error);
    }

    $data_to_view = [
      "user_id" => $user_id,
      "fullname" => $user_name
    ];

    $payload = ["statusCode" => 200, "data" => $data_to_view];

    return $this->json->withJsonResponse($response, $payload);
  }

  public function resetPassword(Request $request, ResponseInterface $response): ResponseInterface
  {

    //declare needed class objects
    $otp_db = new OtpCodeModel();
    $user_db = new UserModel();


    $data = $request->getParsedBody();

    $email = $data["email"];

    $user_query = $user_db->where(
    [
      'email' => $email,
      "account_status" => Constants::USER_STATUS_ENABLED
    ]);

    if (!$user_query->exists())
    {
      $error = ["errorMessage" => "Email Address not Recognised or Account Disabled. Please contact support if you are sure you have an account with this email", "statusCode" => 400];

      return $this->json->withJsonResponse($response, $error);
    }

    //get needed user details
    $user_details = $user_query->first();
    $username = $user_details->full_name;
    
    //create token to be sent to user
    $token = rand(0000, 9999);
    $otp_db->create([
        "otp_code" => $token,
        "use_type" => Constants::TOKEN_TYPE_RESET
    ]);

    //send Email
    $emailParams = [
      "username" => $username,
      "otp" => $token
    ];

    $mail = new MailHandler(2,$email, $emailParams);

    ["error" => $error, "success" => $success] = $mail->sendMail();

    $payload = ["statusCode" => 200, "successMessage" => "Password Reset Token Sent"];

    return $this->json->withJsonResponse($response, $payload);
  }

  public function changePassword(Request $request, ResponseInterface $response): ResponseInterface
  {

    //declare needed class objects
    $otp_db = new OtpCodeModel();
    $user_db = new UserModel();


    $data = $request->getParsedBody();

    $token = $data["token"];
    $email = $data["email"];
    $password = $data["password"];

    $otp_query = $otp_db->where(
    [
      'otp_code' => $token,
      'use_type' => Constants::TOKEN_TYPE_RESET,
      'code_status' => Constants::REG_STATUS_UNUSED
    ]);

    if (!$otp_query->exists())
    {
      $error = ["errorMessage" => "OTP Token Provided does not exist or has been used. Please Try Again", "statusCode" => 400];

      return $this->json->withJsonResponse($response, $error);
    }

    $otp_query->update(["code_status" => Constants::REG_STATUS_USED]);

    
    $user_query = $user_db->where('email',$email);

    //update Password
    $hashed_password = hash('sha256', $password);
    $user_query->update(["password" => $hashed_password]);

    //get user details
    $user_details = $user_query->first();
    $user_id = $user_details->user_id;
    $user_name = $user_details->full_name;

    $data_to_view = [
      "user_id" => $user_id,
      "fullname" => $user_name
    ];

    $payload = ["statusCode" => 200, "data" => $data_to_view];

    return $this->json->withJsonResponse($response, $payload);
  }

}
