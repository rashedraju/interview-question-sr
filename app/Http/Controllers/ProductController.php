<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index() {
        $attr = request()->validate( [
            'title'      => 'sometimes',
            'variant'    => 'sometimes',
            'price_from' => 'sometimes',
            'price_to'   => 'sometimes',
            'date'       => 'sometimes'
        ] );

        $products = Product::filter( $attr )->paginate( 5 );
        $productVariants = Variant::all()->map( function ( $v ) {
            return [
                'variant'  => $v->title,
                'variants' => $v->productVariants->unique( 'variant' )
            ];
        } );
        return view( 'products.index', compact( ['products', 'productVariants'] ) );
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function create() {
        $variants = Variant::all();
        return view( 'products.create', compact( 'variants' ) );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store( Request $request ) {

        // form validation
        $validator = Validator::make( $request->all(), [
            'title'                  => 'required|string',
            'sku'                    => 'required|unique:products,sku',
            'description'            => 'sometimes',
            'product_variant'        => 'sometimes|array',
            'product_variant_prices' => 'sometimes|array'
        ] );

        if ( $validator->fails() ) {
            return response()
                ->json( $validator->getMessageBag() );
        }

        // create product
        $product = Product::create( $request->only( ['title', 'sku', 'description'] ) );

        // create variants
        $variants = [];
        foreach ( $request->product_variant as $variant ) {
            foreach ( $variant['tags'] as $tag ) {
                $pv = ProductVariant::create( [
                    'variant'    => $tag,
                    'product_id' => $product->id,
                    'variant_id' => $variant['option']
                ] );

                $variants[$variant['option']][] = $pv->id;

            }
        }

        // get combined variations
        $combinedVariants = ProductService::getComb( array_values( $variants ) );

        // create product variants price
        $productVariantPrices = $request->product_variant_prices;

        foreach ( $combinedVariants as $index => $variant ) {
            ProductVariantPrice::create( [
                'product_variant_one'   => isset( $variant[0] ) ? $variant[0] : null,
                'product_variant_two'   => isset( $variant[1] ) ? $variant[1] : null,
                'product_variant_three' => isset( $variant[2] ) ? $variant[2] : null,
                'price'                 => $productVariantPrices[$index]['price'],
                'stock'                 => $productVariantPrices[$index]['stock'],
                'product_id'            => $product->id
            ] );
        }

        $data = [
            'message' => 'New product has been created'
        ];

        return response()->json( $data );
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function show( $product ) {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function edit( Product $product ) {
        $variants = Variant::all();
        return view( 'products.edit', compact( 'variants' ) );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update( Request $request, Product $product ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function destroy( Product $product ) {
        //
    }
}
