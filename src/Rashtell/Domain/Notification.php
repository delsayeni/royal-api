<?php

namespace Rashtell\Domain;

use Rashtell\Domain\Constants;
use GuzzleHttp\Client;
use Fcm\FcmClient;
use Fcm\Topic\Subscribe;
use Fcm\Topic\Unsubscribe;
use Fcm\Push\Notification ;


trait Notifications
{  
  public function sendMobileNotification($notification_type, $title, $message, $user_tokens = false)
  {
    //initialize the necessary classes
    $server_key = $_ENV["FCM_SERVER_KEY"];
    $server_id = $_ENV["FCM_SENDER_ID"];

    try {
      $fcm_client = new FcmClient($server_key, $server_id);
      $notification = new Notification();
    } catch (\Exception $e) {
      return false;
    }

    if ($notification_type === Constants::NOTIFICATION_ONE_USER) {
      $notification
        ->addRecipient($user_tokens)
        ->setTitle($title)
        ->setSound("default")
        ->setBody($message);

      try {
        $fcm_client->send($notification);
      } catch (\Exception $e) {
        return false;
      }
    } else if ($notification_type === Constants::NOTIFICATION_USER_GROUP) {
      $topic = "/topics/" . $user_tokens;
      $notification
        ->addRecipient($topic)
        ->setTitle($title)
        ->setBody($message);

      try {
        $fcm_client->send($notification);
      } catch (\Exception $e) {
        return false;
      }
    } else if ($notification_type === Constants::NOTIFICATION_ALL_USER) {
      $topic = "/topics/all";
      $notification
        ->addRecipient($topic)
        ->setTitle($title)
        ->setBody($message);

      try {
        $fcm_client->send($notification);
      } catch (\Exception $e) {
        return false;
      }
    } else {
      return false;
    }

    return true;
  }

  public function subcribeUser($topic, $token)
  {
    $server_key = $_ENV["FCM_SERVER_KEY"];
    $server_id = $_ENV["FCM_SENDER_ID"];

    try {
      $fcm_client = new FcmClient($server_key, $server_id);
      $subscribe = new Subscribe($topic);

      $subscribe->addDevice($token);

      $fcm_client->send($subscribe);
    } catch (\Exception $e) {
      return false;
    }

    return true;
  }

  public function unSubcribeUser($topic, $token)
  {
    $server_key = $_ENV["FCM_SERVER_KEY"];
    $server_id = $_ENV["FCM_SENDER_ID"];

    try {
      $fcm_client = new FcmClient($server_key, $server_id);
      $unsubscribe = new Unsubscribe($topic);

      $unsubscribe->addDevice($token);

      $fcm_client->send($unsubscribe);
    } catch (\Exception $e) {
      return false;
    }

    return true;
  }
}
