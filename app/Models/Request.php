<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'request_type',
        'name',
        'nik',
        'ktp_photo',
        'birth_date',
        'phone_primary',
        'phone_secondary',
        'email',
        'city_id',
        'district_id',
        'subdistrict_id',
        'rt',
        'rw',
        'address',
        'landmark',
        'date_check',
        'date_confirmation',
        'test_location_id',
        'other_test_location',
        'test_type',
        'test_result_photo',
        'is_reported',
        'is_reported_tracing',
        'consultation_ticket_id',
        'doctor_name',
        'prescription_photo',
        'package_id',
        'created_date',
        'created_at',
        'test_location_name',
        'test_type_name',
        'other_test_type',
    ];
}
