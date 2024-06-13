<?php

namespace Nicelizhi\Shopify\Console\Commands\Order;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderCommentRepository;
use Webkul\Admin\DataGrids\Sales\OrderDataGrid;

use Nicelizhi\Shopify\Models\ShopifyOrder;
use Nicelizhi\Shopify\Models\ShopifyStore;
use Webkul\Sales\Models\Order;

class Create extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:order:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create Order';

    private $shopify_store_id = "";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected ShopifyOrder $ShopifyOrder,
        protected ShopifyStore $ShopifyStore,
        protected OrderCommentRepository $orderCommentRepository
    )
    {
        $this->shopify_store_id = "hatmeo";
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {


        $shopifyStore = $this->ShopifyStore->where('shopify_store_id', $this->shopify_store_id)->first();

        if(is_null($shopifyStore)) {
            $this->error("no store");
            return false;
        }

        // $lists = $this->orderRepository->findWhere([
        //     'status' => 'processing'
        // ]);
        //$lists = Order::where(['status'=>'processing'])->orderBy("updated_at", "desc")->limit(10)->get();
        $lists = Order::where(['id'=>'1037'])->orderBy("updated_at", "desc")->limit(10)->get();
       // $lists = Order::where(['id'=>'305'])->orderBy("updated_at", "desc")->limit(10)->get();

        //var_dump($lists);exit;

        foreach($lists as $key=>$list) {
            $this->info("start post order " . $list->id);
            $this->postOrder($list->id, $shopifyStore);
        }


        
    }

    public function postOrder($id, $shopifyStore) {
        // check the shopify have sync

        $shopifyOrder = $this->ShopifyOrder->where([
            'order_id' => $id
        ])->first();
        if(!is_null($shopifyOrder)) {
            return false;
        }

        $client = new Client();

        $shopify = $shopifyStore->toArray();

        /**
         * 
         * @link https://shopify.dev/docs/api/admin-rest/2023-10/resources/order#post-orders
         * 
         */
        // $id = 147;
        $order = $this->orderRepository->findOrFail($id);

        $orderPayment = $order->payment;   
        
        $cnv_id = explode('-',$orderPayment['method_title']);

        var_dump($cnv_id);exit;
        
        //$orderPayment = $orderPayment->toArray();

        //var_dump($orderPayment['method_title']);exit;

        //var_dump($order);exit;

        $postOrder = [];

        $line_items = [];

        $products = $order->items;
        foreach($products as $key=>$product) {
            $sku = $product['additional'];

            $skuInfo = explode('-', $sku['product_sku']);
            if(!isset($skuInfo[1])) {
                $this->error("have error" . $id);
                return false;
            }

            $line_item = [];
            $line_item['variant_id'] = $skuInfo[1];
            $line_item ['quantity'] = $product['qty_ordered'];
            $line_item ['requires_shipping'] = true;

            array_push($line_items, $line_item);
        }

        $shipping_address = $order->shipping_address;
        $postOrder['line_items'] = $line_items;


        $customer = [];
        $customer = [
            "first_name" => $shipping_address->first_name,
            "last_name"  => $shipping_address->last_name,
            "email"     => $shipping_address->email,
        ];
        $postOrder['customer'] = $customer;

        

        $billing_address = [
            "first_name" => $shipping_address->first_name,
            "last_name" => $shipping_address->last_name,
            "address1" => $shipping_address->address1,
            "phone" => $shipping_address->phone,
            "city" => $shipping_address->city,
            "province" => $shipping_address->state,
            "country" => $shipping_address->country,
            "zip" => $shipping_address->postcode
        ];
        $postOrder['billing_address'] = $billing_address;
        

        $shipping_address = [
            "first_name" => $shipping_address->first_name,
            "first_name" => "测试订单",
            "last_name" => $shipping_address->last_name,
            "address1" => $shipping_address->address1,
            "phone" => $shipping_address->phone,
            "city" => $shipping_address->city,
            "province" => $shipping_address->state,
            "country" => $shipping_address->country,
            "zip" => $shipping_address->postcode
        ];

        $postOrder['shipping_address'] = $shipping_address;

        $postOrder['email'] = "";
        
        $transactions = [];

        $transactions = [
            [
                "kind" => "sales",
                "status" => "success",
                "amount" => $order->grand_total,
            ]
        ];

        $postOrder['transactions'] = $transactions;

        $postOrder['financial_status'] = "paid";

        $postOrder['current_subtotal_price'] = $order->sub_total;

        $current_subtotal_price_set = [
            'shop_money' => [
                "amount" => $order->sub_total,
                "currency_code" => $order->order_currency_code,
            ],
            'presentment_money' => [
                "amount" => $order->sub_total,
                "currency_code" => $order->order_currency_code,
            ]
        ];
        $postOrder['current_subtotal_price_set'] = $current_subtotal_price_set;



        // $total_shipping_price_set = [];
        // $shop_money = [];
        // $shop_money['amount'] = $order->shipping_amount;
        // $shop_money['currency_code'] = $order->order_currency_code;
        // $total_shipping_price_set['shop_money'] = $shop_money;
        // $total_shipping_price_set['presentment_money'] = $shop_money;

        $total_shipping_price_set = [
            "shop_money" => [
                "amount" => $order->shipping_amount,
                "currency_code" => $order->order_currency_code,
            ],
            "presentment_money" => [
                "amount" => $order->shipping_amount,
                "currency_code" => $order->order_currency_code,
            ]
        ];

        $postOrder['total_shipping_price_set'] = $total_shipping_price_set;

        // $discount_codes = [];
        // $discount_codes = [
        //     'code' => 'COUPON_CODE',
        //     'amount' => $order->discount_amount,
        //     'type' => 'percentage'
        // ];

        /**
         * 
         * If you're working on a private app and order confirmations are still being sent to the customer when send_receipt is set to false, then you need to disable the Storefront API from the private app's page in the Shopify admin.
         * 
         */

        $postOrder['send_receipt'] = true; 

        // $postOrder['discount_codes'] = $discount_codes;

        $postOrder['current_total_discounts'] = $order->discount_amount;
        $current_total_discounts_set = [
            'shop_money' => [
                'amount' => $order->discount_amount,
                'currency_code' => $order->order_currency_code
            ],
            'presentment_money' => [
                'amount' => $order->discount_amount,
                'currency_code' => $order->order_currency_code
            ]
        ];
        $postOrder['current_total_discounts_set'] = $current_total_discounts_set;
        $postOrder['total_discount'] = $order->discount_amount;
        $total_discount_set = [];
        $total_discount_set = [
            'shop_money' => [
                'amount' => $order->discount_amount,
                'currency_code' => $order->order_currency_code
            ],
            'presentment_money' => [
                'amount' => $order->discount_amount,
                'currency_code' => $order->order_currency_code
            ]
        ];
        $postOrder['total_discount_set'] = $total_discount_set;
        $postOrder['total_discounts'] = $order->discount_amount;


        $shipping_lines = [];

        $shipping_lines = [
            'price' => $order->shipping_amount,
            'code' => 'Standard',
            "title" => "Standard Shipping",
            "source" => "us_post",
            "tax_lines" => [],
            "carrier_identifier" => "third_party_carrier_identifier",
            "requested_fulfillment_service_id" => "third_party_fulfillment_service_id",
            "price_set" => [
                'shop_money' => [
                    'amount' => $order->shipping_amount,
                    'currency_code' => $order->order_currency_code
                ],
                'presentment_money' => [
                    'amount' => $order->shipping_amount,
                    'currency_code' => $order->order_currency_code
                ]
            ]
        ];



        $postOrder['shipping_lines'][] = $shipping_lines;

        $pOrder['test'] = true;

        $pOrder['order'] = $postOrder;
        //var_dump($pOrder);exit;

        $response = $client->post($shopify['shopify_app_host_name'].'/admin/api/2023-10/orders.json', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Shopify-Access-Token' => $shopify['shopify_admin_access_token'],
            ],
            'body' => json_encode($pOrder)
        ]);

        $body = json_decode($response->getBody(), true);
        Log::info("shopify post order body ". json_encode($pOrder));
        Log::info("shopify post order".json_encode($body));

        if(isset($body['order']['id'])) {
            $shopifyNewOrder = $this->ShopifyOrder->where([
                'shopify_order_id' => $body['order']['id']
            ])->first();
            if(is_null($shopifyNewOrder)) $shopifyNewOrder = new \Nicelizhi\Shopify\Models\ShopifyOrder();
            $shopifyNewOrder->order_id = $id;
            $shopifyNewOrder->shopify_order_id = $body['order']['id'];
            $shopifyNewOrder->shopify_store_id = $this->shopify_store_id;

            $item = $body['order'];

            $shopifyNewOrder->admin_graphql_api_id = $item['admin_graphql_api_id'];
            $shopifyNewOrder->app_id = $item['app_id'];
            $shopifyNewOrder->browser_ip = $item['browser_ip'];
            $shopifyNewOrder->buyer_accepts_marketing = $item['buyer_accepts_marketing'];
            $shopifyNewOrder->cancel_reason = $item['cancel_reason'];
            $shopifyNewOrder->cancelled_at = $item['cancelled_at'];
            $shopifyNewOrder->cart_token = $item['cart_token'];
            $shopifyNewOrder->checkout_id = $item['checkout_id'];
            $shopifyNewOrder->checkout_token = $item['checkout_token'];
            $shopifyNewOrder->client_details = $item['client_details'];
            $shopifyNewOrder->closed_at = $item['closed_at'];
            $shopifyNewOrder->company = $item['company'];
            $shopifyNewOrder->confirmation_number = $item['confirmation_number'];
            $shopifyNewOrder->confirmed = $item['confirmed'];
            $shopifyNewOrder->contact_email = $item['contact_email'];
            $shopifyNewOrder->currency = $item['currency'];
            $shopifyNewOrder->current_subtotal_price = $item['current_subtotal_price'];
            $shopifyNewOrder->current_subtotal_price_set = $item['current_subtotal_price_set'];
            $shopifyNewOrder->current_total_additional_fees_set = $item['current_total_additional_fees_set'];
            $shopifyNewOrder->current_total_discounts = $item['current_total_discounts'];
            $shopifyNewOrder->current_total_discounts_set = $item['current_total_discounts_set'];
            $shopifyNewOrder->current_total_duties_set = $item['current_total_duties_set'];
            $shopifyNewOrder->current_total_price = $item['current_total_price'];
            $shopifyNewOrder->current_total_price_set = $item['current_total_price_set'];
            $shopifyNewOrder->current_total_tax = $item['current_total_tax'];
            $shopifyNewOrder->current_total_tax_set = $item['current_total_tax_set'];
            $shopifyNewOrder->customer_locale = $item['customer_locale'];
            $shopifyNewOrder->device_id = $item['device_id'];
            $shopifyNewOrder->discount_codes = $item['discount_codes'];
            $shopifyNewOrder->email = $item['email'];
            $shopifyNewOrder->estimated_taxes = $item['estimated_taxes'];
            $shopifyNewOrder->financial_status = $item['financial_status'];
            $shopifyNewOrder->fulfillment_status = $item['fulfillment_status'];
            $shopifyNewOrder->landing_site = $item['landing_site'];
            $shopifyNewOrder->landing_site_ref = $item['landing_site_ref'];
            $shopifyNewOrder->location_id = $item['location_id'];
            $shopifyNewOrder->merchant_of_record_app_id = $item['merchant_of_record_app_id'];
            $shopifyNewOrder->name = $item['name'];
            $shopifyNewOrder->note = $item['note'];
            $shopifyNewOrder->note_attributes = $item['note_attributes'];
            $shopifyNewOrder->number = $item['number'];
            $shopifyNewOrder->order_number = $item['order_number'];
            $shopifyNewOrder->order_status_url = $item['order_status_url'];
            $shopifyNewOrder->original_total_additional_fees_set = $item['original_total_additional_fees_set'];
            $shopifyNewOrder->original_total_duties_set = $item['original_total_duties_set'];
            $shopifyNewOrder->payment_gateway_names = $item['payment_gateway_names'];
            $shopifyNewOrder->phone = $item['phone'];
            $shopifyNewOrder->po_number = $item['po_number'];
            $shopifyNewOrder->presentment_currency = $item['presentment_currency'];
            $shopifyNewOrder->processed_at = $item['processed_at'];
            $shopifyNewOrder->reference = $item['reference'];
            $shopifyNewOrder->referring_site = $item['referring_site'];
            $shopifyNewOrder->source_identifier = $item['source_identifier'];
            $shopifyNewOrder->source_name = $item['source_name'];
            $shopifyNewOrder->source_url = $item['source_url'];
            $shopifyNewOrder->subtotal_price = $item['subtotal_price'];
            $shopifyNewOrder->subtotal_price_set = $item['subtotal_price_set'];
            $shopifyNewOrder->tags = $item['tags'];
            $shopifyNewOrder->tax_exempt = $item['tax_exempt'];
            $shopifyNewOrder->tax_lines = $item['tax_lines'];
            $shopifyNewOrder->taxes_included = $item['taxes_included'];
            $shopifyNewOrder->test = $item['test'];
            $shopifyNewOrder->token = $item['token'];
            $shopifyNewOrder->total_discounts = $item['total_discounts'];
            $shopifyNewOrder->total_discounts_set = $item['total_discounts_set'];
            $shopifyNewOrder->total_line_items_price = $item['total_line_items_price'];
            $shopifyNewOrder->total_line_items_price_set = $item['total_line_items_price_set'];
            $shopifyNewOrder->total_outstanding = $item['total_outstanding'];
            $shopifyNewOrder->total_price = $item['total_price'];
            $shopifyNewOrder->total_price_set = $item['total_price_set'];
            
            $shopifyNewOrder->total_shipping_price_set = $item['total_shipping_price_set'];
            $shopifyNewOrder->total_tax = $item['total_tax'];
            $shopifyNewOrder->total_tax_set = $item['total_tax_set'];
            $shopifyNewOrder->total_tip_received = $item['total_tip_received'];
            $shopifyNewOrder->total_weight = $item['total_weight'];
            $shopifyNewOrder->user_id = $item['user_id'];
            $shopifyNewOrder->billing_address = $item['billing_address'];
            $shopifyNewOrder->customer = $item['customer'];
            $shopifyNewOrder->discount_applications = $item['discount_applications'];
            $shopifyNewOrder->fulfillments = $item['fulfillments'];
            $shopifyNewOrder->line_items = $item['line_items'];
            $shopifyNewOrder->payment_terms = $item['payment_terms'];
            $shopifyNewOrder->refunds = $item['refunds'];
            $shopifyNewOrder->shipping_address = $item['shipping_address'];
            $shopifyNewOrder->shipping_lines = $item['shipping_lines'];



            $shopifyNewOrder->save();
            // $shopifyNewOrder->save();
        }

        exit;
    }
}
