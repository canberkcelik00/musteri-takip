<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored setting value; read and written only through App\Support\Settings.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    //
}
