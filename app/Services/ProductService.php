<?php

namespace App\Services;

class ProductService {
    public static function getComb( $arrays, $i = 0 ) {
        if ( !isset( $arrays[$i] ) ) {
            return [];
        }

        if ( $i == count( $arrays ) - 1 ) {
            return $arrays[$i];
        }

        // get combinations from subsequent arrays
        $tmp = self::getComb( $arrays, $i + 1 );

        $result = [];

        // concat each array from tmp with each element from $arrays[$i]
        foreach ( $arrays[$i] as $v ) {
            foreach ( $tmp as $t ) {
                $result[] = is_array( $t ) ?
                array_merge( [$v], $t ) :
                [$v, $t];
            }
        }

        return $result;
    }
}
