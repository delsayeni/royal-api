<?php

namespace Royal\Models\Mobile;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use Royal\Models\BaseModel;

class WalletModel extends BaseModel
{
  use SoftDeletes;

  protected $table = "wallet";
  public $primaryKey = "wallet_id";
  protected $guarded = [];
  protected $fillable = ['user_id','bank_name','account_number','routing_number'];
}
