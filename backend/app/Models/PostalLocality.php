<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A postal code + locality pair from the official directory (see address:import-postcodes).
 */
class PostalLocality extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
