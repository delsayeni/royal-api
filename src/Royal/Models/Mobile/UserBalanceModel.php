<?php

namespace Royal\Models\Mobile;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use Royal\Models\BaseModel;

class UserBalanceModel extends BaseModel
{
  use SoftDeletes;

  protected $table = "user_balance";
  public $primaryKey = "user_balance_id";
  protected $guarded = [];
  protected $fillable = ['user_id','amount'];
}
