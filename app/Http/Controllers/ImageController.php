<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ImageController extends Controller {
    public function uploadProductImage( Request $request ) {
        $fname = $imageName = time() . '.' . $request->file->getClientOriginalExtension();
        $request->file->move( public_path( 'product_image' ), $imageName );

        return response()->json( ['url' => $fname] );
    }
}
