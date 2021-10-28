<?php

namespace Rashtell\Domain;

class Constants
{
  const ENVIRONMENT_DEVELOPMENT = "development";

  const USERTYPE_ADMIN = 0;
  const USERTYPE_TPI = 1;
  const USERTYPE_VENDOR = 2;

  const USER_NOT_VERIFIED = 0;
  const USER_VERIFIED = 1;
  const EMAIL_NOT_VERIFIED = 0;
  const EMAIL_VERIFIED = 1;

  const DEFAULT_RESET_PASSWORD = "Project_Name_12345";

  const PRIVILEDGE_ADMIN_ADMIN = "ADMIN";
  const PRIVILEDGE_ADMIN_VENDOR = "VENDOR";
  const PRIVILEDGE_ADMIN_ACTIVITY_LOG = "ACTIVITY_LOG";
  const PRIVILEDGE_ADMIN_TOPUP = 3;
  const PRIVILEDGE_ADMIN_WALLET = 4;
  const PRIVILEDGE_ADMIN_AD = 5;
  const PRIVILEDGE_ADMIN_ROUTE = 6;
  const PRIVILEDGE_ADMIN_PRICE = 7;
  const PRIVILEDGE_ADMIN_TRANSACTION = 8;
  const PRIVILEDGE_ADMIN_REPORT = 9;
  const PRIVILEDGE_ADMIN_BANK = 0;
  const PRIVILEDGE_ADMIN_BANK_ACCOUNT = 0;

  const ERROR_NOT_FOUND = "Not found";
  const ERROR_EMPTY_DATA = "No more data";

  const IMAGE_PATH = "assets/images/";
  const IMAGE_TYPES_ACCEPTED = ["jpg", "jpeg", "png", "svg"];
  const VIDEO_PATH = "assets/videos/";
  const VIDEO_TYPES_ACCEPTED = ["avi", "mp4"];
  const MEDIA_PATH = "assets/medias/";

  const MEDIA_TYPES_ACCEPTED = ["pdf"];
  const MEDIA_TYPES = ["IMAGE", "VIDEO"];
  
  const MEDIA_TYPE_IMAGE = "IMAGE";
  const MEDIA_TYPE_VIDEO = "VIDEO";

  const IMAGE_RESIZE_MAX_WIDTH = 1200;
  const IMAGE_RESIZE_MAX_HEIGHT = 1200;
  const IMAGE_RESIZE_QUALITY = 90;

  const NOTIFICATION_ALL_USER = "ALL_USER";
  const NOTIFICATION_ONE_USER = "ONE_USER";
  const NOTIFICATION_USER_GROUP = "USER_GROUP";

  const USER_ENABLED = "ENABLED";
  const USER_DISABLED = "DISABLED";

  const WALLET_TYPE_DEFAULT = "DEFAULT";

  const AD_ACTIVE_STATUS_INACTIVE = "INACTIVE";
  const AD_ACTIVE_STATUS_ACTIVE = "ACTIVE";
  const AD_ACTIVE_STATUS_BLOCKED = "BLOCKED";

  const PRICE_ACTIVE = 1;
  const PRICE_INACTIVE = 0;

  const VERIFY_TOPUPS_COUNT_DEFAULT = 50;

  const TOPUP_TYPE_PAYSTACK = "Paystack";
  const TOPUP_TYPE_DIRECT = "Direct";
}
