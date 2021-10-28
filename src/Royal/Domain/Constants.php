<?php

namespace Royal\Domain;

class Constants
{
  const PRODUCTION_HOST = "https://api.liveet.co";

  const ENVIRONMENT_DEVELOPMENT = "development";

  const REG_STATUS_UNUSED = "UNUSED";
  const REG_STATUS_USED = "USED";

  const TOKEN_TYPE_REG = "REGISTRATION";
  const TOKEN_TYPE_RESET = "RESET PASSWORD";

  const USER_STATUS_ENABLED = "ENABLED";
  const USER_STATUS_DISABLED = "DISABLED";


  const TRANSFER_INTERNAL = "INTERNAL";
  const TRANSFER_LOCAL = "LOCAL";
  const TRANSFER_INTERNATIONAL = "INTERNATIONAL";

  const TRANSFER_COMPLETED = "COMPLETED";
  const TRANSFER_PENDING = "PENDING";
}
