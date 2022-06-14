<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    public function variant(){
        return $this->belongsTo(Variant::class, 'variant_id');
    }

    public function product(){
        return $this->belongsTo(Product::class, 'product_id');
    }
}
