<?php

namespace Royal\Models\Mobile;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use Royal\Models\BaseModel;

class UserModel extends BaseModel
{
  use SoftDeletes;

  protected $table = "user";
  public $primaryKey = "user_id";
  protected $guarded = [];
  protected $fillable = ['full_name','email','password','address','state','country','account_number','routing_number','account_status'];
}
