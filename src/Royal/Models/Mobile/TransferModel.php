<?php

namespace Royal\Models\Mobile;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use Royal\Models\BaseModel;

class TransferModel extends BaseModel
{
  use SoftDeletes;

  protected $table = "transfer";
  public $primaryKey = "transfer_id";
  protected $guarded = [];
  protected $fillable = ['user_id','transfer_type','transfer_amount','bank_name','account_name','account_number','routing_number','sort_code','country','transfer_desc','transfer_status'];
}
