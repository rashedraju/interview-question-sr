<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
            'product_variant_prices' => 'sometimes|array',
            'product_image'          => 'sometimes|array'
        ] );

        if ( $validator->fails() ) {
            return response()
                ->json( $validator->getMessageBag() );
        }

        // create product
        $product = Product::create( $request->only( ['title', 'sku', 'description'] ) );

        foreach ( $request->product_image as $image ) {
            ProductImage::create( [
                'product_id' => $product->id,
                'file_path'  => $image
            ] );
        }

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
        // prepare product variants
        $product_variants = Variant::all()->map( function ( $variant ) use ( $product ) {
            $productVariants = $variant->productVariants()->where( 'product_id', $product->id )->get()->map( fn( $v ) => $v->variant );
            return [
                'option' => $variant->id,
                'tags'   => $productVariants
            ];
        } );

        $product_variants = $product_variants->filter( fn( $v ) => $v['tags']->count() );

        $variants = Variant::all();

        $product_variant_price = $product->variantPrices->map( function ( $variantPrice ) {
            return [
                'price' => $variantPrice->price,
                'stock' => $variantPrice->stock,
                'title' => $variantPrice->variantOne?->variant . "/" . $variantPrice->variantTwo?->variant . "/" . $variantPrice->variantThree?->variant
            ];
        } );

        return view( 'products.edit', compact( 'variants', 'product', 'product_variants', 'product_variant_price' ) );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update( Request $request, Product $product ) {
        // form validation
        $validator = Validator::make( $request->all(), [
            'title'                  => 'required|string',
            'sku'                    => [
                'required',
                Rule::unique( 'products' )->ignore( $product->id, 'id' )
            ],

            'description'            => 'sometimes',
            'product_variant'        => 'sometimes|array',
            'product_variant_prices' => 'sometimes|array',
            'product_image'          => 'sometimes|array'
        ] );

        if ( $validator->fails() ) {
            return response()
                ->json( $validator->getMessageBag() );
        }

        // create product
        $product->update( $request->only( ['title', 'sku', 'description'] ) );

        // save product images
        $productImages = array_map( fn( $image ) => new ProductImage( ['product_id' => $product->id, 'file_path' => $image] ), $request->product_image );

        $product->images()->saveMany( $productImages );

        // update product variants
        $product->variants()->delete();
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

        // update product variant prices
        $combinedVariants = ProductService::getComb( array_values( $variants ) );

        // delete previous variant prices
        $product->variantPrices()->delete();

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
            'message' => 'Product has been updated'
        ];

        return response()->json( $data );
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
