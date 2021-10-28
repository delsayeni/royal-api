<?php

namespace Royal\Models\Mobile;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use Royal\Models\BaseModel;

class OtpCodeModel extends BaseModel
{
  use SoftDeletes;

  protected $table = "otp_code";
  public $primaryKey = "otp_code_id";
  protected $guarded = [];
  protected $fillable = ['otp_code','use_type','code_status'];
}
