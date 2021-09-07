<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Verification extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'request_number',
        'verif_data_status',
        'verif_data_note',
        'verif_labtest_status',
        'verif_labtest_note',
        'verif_prescription_status',
        'verif_prescription_note',
        'verif_packing_status',
        'verif_packing_note',
        'shipping_status',
        'shipping_note',
        'shipping_url',
        'shipping_courier_name',
        'shipping_code',
        'shipping_receiver',
        'shipping_receive_date',
        'final_status',
        'final_status_note',
    ];
}
