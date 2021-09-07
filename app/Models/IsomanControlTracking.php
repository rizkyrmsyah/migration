<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsomanControlTracking extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'isoman_control_tracking';
}
