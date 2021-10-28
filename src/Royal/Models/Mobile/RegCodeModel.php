<?php

namespace Royal\Models\Mobile;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use Royal\Models\BaseModel;

class RegCodeModel extends BaseModel
{
  use SoftDeletes;

  protected $table = "registration_code";
  public $primaryKey = "reg_code_id";
  protected $guarded = [];
  protected $fillable = ['reg_code','reg_status'];
}
