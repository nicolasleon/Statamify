<?php

namespace Statamic\Addons\Statamify\Controllers;

use Statamic\Addons\Statamify\Models\Cart;
use Statamic\Addons\Statamify\Validators\CartValidator as Validate;
use Statamic\Extend\Controller;
use Illuminate\Http\Request;
use Statamic\Addons\Statamify\Statamify;

class CartController extends Controller
{

  public function add(Cart $cart, Request $request)
  {

    $data = $request->all();

    Validate::add($data);

    return $cart->add($data);

  }

  public function defaultAddress(Cart $cart, Request $request)
  {

    $data = $request->all();

    Validate::defaultAddress($data);

    $cart->setDefaultAddress($data['address']);

  }

  public function get(Cart $cart)
  {

    return $cart->get();

  }

  public function update(Cart $cart, Request $request)
  {

    $data = $request->all();

    Validate::update($data);

    return $cart->update($data);

  }

  public function setShipping(Cart $cart, Request $request)
  {

    Validate::setShipping($request->all());

    $countries = Statamify::location();
    $regions = Statamify::location('regions');

    $data = [ 
      'countries' => reset($countries), 
      'regions' => reset($regions) 
    ];

    if ($request->shipping_country) {
      session(['statamify.shipping_country' => $request->shipping_country]);
    } else {
      session()->forget('statamify.shipping_country');
    }

    $cart->setShipping();
    $data['cart'] = $cart->get();

    return $data;

  }

  public function setShippingMethod(Request $request) {

    Validate::setShippingMethod($request->all());

    $shipping = explode('|', $request->shipping);

    session(['statamify.shipping_method' => isset($shipping[1]) ? $shipping[1] : 0]);

    $this->get();

  }

}