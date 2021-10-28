<?php

namespace Royal\Models\Mobile;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use Royal\Models\BaseModel;

class TempUserModel extends BaseModel
{
  use SoftDeletes;

  protected $table = "temp_user";
  public $primaryKey = "temp_id";
  protected $guarded = [];
  protected $fillable = ['full_name','email','password','address','state','country','account_number','routing_number'];
}
