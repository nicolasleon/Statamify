<?php

namespace Statamic\Addons\Statamify\Controllers;

use Statamic\Addons\Statamify\Models\Analytics;
use Statamic\Addons\Statamify\Models\Cart;
use Statamic\Extend\Controller;
use Statamic\API\Config;
use Statamic\Addons\Statamify\Models\Emails;
use Statamic\API\Entry;
use Statamic\API\File;
use Statamic\API\Folder;
use Statamic\API\Helper;
use Illuminate\Pagination\LengthAwarePaginator;
use Statamic\Addons\Statamify\Models\Order;
use Statamic\Addons\Statamify\Validators\OrderValidator as Validate;
use Statamic\Presenters\PaginationPresenter;
use Illuminate\Http\Request;
use Statamic\API\User;
use Statamic\Addons\Statamify\Statamify;
use Statamic\API\Stache;
use Statamic\API\Storage;

class OrderController extends Controller
{

  public function create(Cart $cart, Order $order, Request $request)
  {

    $data = $request->all();
    $user = User::getCurrent();

    if ($user) {

      $data['user'] = $user->get('id');
      $data['email'] = $user->email();

    } else {

      $data['user'] = null;

    }

    $valid = Validate::create($data);

    if (is_bool($valid)) {

      unset($data['_token'], $data['addresso']);

      $data = $order->create($data);
      $cart->clear();

      $email_order_new = new Emails('order-new', $data, $data['listing_email']);
      $email_order_new->sendEmail();

      $email_admin_order_new = new Emails('admin-order-new', $data);
      $email_admin_order_new->sendEmail();

      return redirect('/account/order/' . $data['slug'])->withInput($data);

    } else {

      return redirect('/store/checkout')->withInput([
        'errors' => $valid,
        'data' => $data
      ]);

    }

  }

  public function delete(Request $request)
  {

    $this->authorize('super');

    $slugs = Helper::ensureArray($request->input('ids'));

    foreach ($slugs as $slug) {

      $path = 'site/storage/statamify/orders/' . $slug . '.yaml';
      $data = Storage::getYAML(str_replace('site/storage/', '', $path));

      $customer = Entry::whereSlug($data['user'], 'store_customers');
      $customers_orders = collect($customer->get('orders'))->reject(function($item) use ($slug) {
        return $item['slug'] == $slug;
      })->all();

      $customer->set('orders', $customers_orders);
      $customer->save();

      Stache::update();
      File::delete($path);

    }

    return ['success' => true];

  }

  public function get(Request $request)
  {

    $this->authorize('super');

    $columns =  [
      'title',
      'date',
      'listing_customer',
      'listing_email',
      'listing_total',
      'listing_status',
    ];

    $orders = Folder::getFiles('site/storage/statamify/orders');

    $totalEntryCount = count($orders);
    $perPage = Config::get('cp.pagination_size');
    $currentPage = (int) $request->page ?: 1;
    $offset = ($currentPage - 1) * $perPage;

    $collection = collect($orders)->sortByDesc(function($path) {
      $slug = str_replace('site/storage/statamify/orders/', '', $path);
      $parts = explode('.', $slug);
      $date = \DateTime::createFromFormat('Y-m-d_H-i-s', $parts[0]);
      return $date->format('Y-m-d H:i:s');
    })->slice($offset, $perPage);

    $entries = $collection->all();
    $paginator = new LengthAwarePaginator($entries, $totalEntryCount, $perPage, $currentPage);

    $orders = $collection->map(function($path) {
      return Storage::getYAML(str_replace('site/storage/', '', $path));
    });

    return [
      'columns' => $columns,
      'items' => $orders->all(),
      'pagination' => [
        'totalItems' => $totalEntryCount,
        'itemsPerPage' => $perPage,
        'totalPages'    => $paginator->lastPage(),
        'currentPage'   => $paginator->currentPage(),
        'prevPage'      => $paginator->previousPageUrl(),
        'nextPage'      => $paginator->nextPageUrl(),
        'segments'      => array_get($paginator->render(new PaginationPresenter($paginator)), 'segments')
      ]
    ];

  }

  public function index()
  {

    $this->authorize('super');

    return $this->view('orders', [
      'moneyFormat' => Statamify::money(null, 'format'),
      'moneyFormatFn' => Statamify::money(null, 'formatPriceJS'),
      'moneySymbol' => Statamify::money(null, 'symbol'),
      'orderStatuses' => [
        "awaiting_payment" => "Awaiting Payment",
        "pending" => "Pending",
        "completed" => "Completed",
        "shipped" => "Shipped",
        "cancelled" => "Cancelled",
        "refunded" => "Refunded",
        "refunded_partially" => "Refunded Partially"
      ]
    ]);

  }

  public function status(Request $request)
  {

    $this->authorize('super');

    $data = $request->all();

    $order = Storage::getYAML('statamify/orders/' . $data['id']);
    $order['status'] = $data['status'];
    $order['listing_status'] = '<span class="order-status ' . $data['status'] . '">' . Statamify::t('status.' . $data['status']) . '</span>';
    Storage::putYAML('statamify/orders/' . $data['id'], $order);

    $email_status_change = new Emails('order-status', $order, $order['listing_email']);
    $email_status_change->sendEmail();

    return ['success' => true];

  }

  public function tracking(Request $request)
  {

    $this->authorize('super');

    $data = $request->all();

    $order = Storage::getYAML('statamify/orders/' . $data['id']);
    $order['shipping_method']['tracking'] = $data['url'];
    
    Storage::putYAML('statamify/orders/' . $data['id'], $order);

    return ['success' => true];

  }

}