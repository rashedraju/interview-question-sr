<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    protected $fillable = [
        'title', 'sku', 'description'
    ];

    // filter expense data
    public function scopeFilter( $query, array $filters ) {

        $query->when( $filters['title'] ?? false, fn( $query, $title ) => $query
                ->where( 'title', 'like', '%' . $title . '%' )
        );

        $query->when( $filters['variant'] ?? false, fn( $query, $variant ) => $query
                ->whereHas( 'variants', fn( $query ) => $query
                        ->where( 'variant_id', $variant )
                )
        );

        $query->when( $filters['price_from'] ?? false, fn( $query, $priceFrom ) => $query
                ->whereHas( 'variantPrices', fn( $query ) => $query
                        ->where( 'price', ">=", $priceFrom )
                )
        );

        $query->when( $filters['price_to'] ?? false, fn( $query, $priceTo ) => $query
                ->whereHas( 'variantPrices', fn( $query ) => $query
                        ->where( 'price', "<=", $priceTo )
                )
        );

        $query->when( $filters['date'] ?? false, function ( $query, $date ) {
            $query->whereDate( 'created_at', date( 'Y-m-d', strtotime( $date ) ) );
        } );

    }

    public function variants() {
        return $this->hasMany( ProductVariant::class );
    }

    public function variantPrices() {
        return $this->hasMany( ProductVariantPrice::class, 'product_id' );
    }
}
