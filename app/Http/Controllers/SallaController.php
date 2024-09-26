<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Store;
use App\Models\OauthToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SallaController extends Controller
{
    public function auth()
    {
        $data = [
            'client_id' => config('salla.client_id'),
            'client_secret' => config('salla.client_secret'),
            'response_type' => 'code',
            'scope' => 'offline_access',
            'redirect_url' => config('salla.callback_url'),
            'state' => rand(11111111, 99999999),
        ];
        $query = http_build_query($data);
        return redirect(config('salla.auth_url') . '?' . $query);
    }
    public function callback(Request $request)
    {
        $code = $request->input('code');
        $state = $request->input('state');

        if (!$code) {
            return response()->json(['status' => 'error', 'message' => 'Authorization code not provided'], 400);
        }

        if (!$state) {
            return response()->json(['status' => 'error', 'message' => 'State parameter not provided'], 400);
        }

        $data = [
            'client_id' => config('salla.client_id'),
            'client_secret' => config('salla.client_secret'),
            'code' => $code,
            'redirect_uri' => config('salla.callback_url'),
            'grant_type' => 'authorization_code',
            'scope' => 'offline_access',
        ];

        try {
            // طلب الحصول على الـ access token
            $response = Http::asForm()->post(config('salla.token_url'), $data);
            $jsonresponse = json_decode($response->body(), true);

            if (!$response->successful()) {
                Log::error('Token request failed.', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to retrieve access token',
                ], $response->status());
            }

            // استرجاع الـ access token والـ refresh token
            $accessToken = $jsonresponse['access_token'];
            $refreshToken = $jsonresponse['refresh_token'];

            // استدعاء الدالة الخاصة بمعلومات المستخدم
            $storedUser = $this->fetchAndStoreUser($accessToken);
            $userId = $storedUser->id;
            // استدعاء الدالة الخاصة بمعلومات المتجر
            $storedStore = $this->fetchAndStoreStore($accessToken, $userId);
            $storeId = $storedStore->id;
            // استدعاء الدالة الخاصة بمعلومات المنتجات
            $products = $this->fetchAndStoreProducts($accessToken, $storeId);
            // استدعاء الدالة الخاصة بمعلومات الشحن
            $shipments = $this->fetchAndStoreShipments($accessToken, $storeId);
            // استدعاء الدالة الخاصة بمعلومات العملاء
            $customers = $this->fetchAndStoreCustomers($accessToken, $storeId);

            // مصفوفة لتخزين تفاصيل كل منتج
            $allProductsDetails = [];

            // التحقق إذا كانت قائمة المنتجات غير فارغة
            if (!empty($products)) {
                foreach ($products as $product) {
                    // احصل على product_id الخاص بكل منتج
                    $productId = $product['product_salla_id'] ?? null;

                    // التحقق من أن product_id موجود
                    if ($productId) {
                        // رسائل تصحيح
                        Log::info("Fetching details for product ID: " . $productId);

                        // استدعاء الدالة الخاصة بتفاصيل المنتجات باستخدام product_id
                        $productDetails = $this->fetchAndStoreProductsdetails($accessToken, $storeId, $productId);

                        // التحقق من أن البيانات تم إرجاعها
                        if (!empty($productDetails)) {
                            // إضافة تفاصيل المنتج إلى المصفوفة
                            $allProductsDetails[] = $productDetails;
                        } else {
                            Log::error("No product details found for product ID: " . $productId);
                        }
                    } else {
                        Log::error("Product ID is missing for one of the products.");
                    }
                }
            } else {
                Log::error("No products found.");
            }
            //استدعاء الدالة الخاصة بمعلومات الاوردرات
            $orders = $this->fetchAndStoreOrders($accessToken, $storeId);
            // مصفوفة لتخزين تفاصيل كل منتج
            $allordersDetails = [];

            // التحقق إذا كانت قائمة المنتجات غير فارغة
            if (!empty($orders)) {
                foreach ($orders as $order) {
                    $orderId = $order['id'] ?? null;
                    if ($orderId) {
                        // رسائل تصحيح
                        Log::info("Fetching details for order ID: " . $orderId);

                        // استدعاء الدالة الخاصة بتفاصيل المنتجات باستخدام product_id
                        $orderDetails = $this->fetchAndStoreOrdersdetails($accessToken, $storeId, $orderId);

                        // التحقق من أن البيانات تم إرجاعها
                        if (!empty($orderDetails)) {
                            // إضافة تفاصيل المنتج إلى المصفوفة
                            $allordersDetails[] = $orderDetails;
                        } else {
                            Log::error("No order details found for product ID: " . $orderId);
                        }
                    } else {
                        Log::error("order ID is missing for one of the products.");
                    }
                }
            } else {
                Log::error("No products found.");
            }
            //استدعاء الدالة الخاصة بمعلومات الفواتير
            $invoices = $this->fetchAndStoreInvoices($accessToken, $storeId);
            return response()->json([
                'status' => 'success',
                'store_info' => $storedStore,
                'user_info' => $storedUser,
                'customer_info' => $customers,
                // 'products_info' => $products,
                'products_details_info' => $allProductsDetails,
                'orders_details_info' => $allordersDetails,
                // 'orders_info' => $orders,
                'invoices_info' => $invoices,
                'shipments_info' => $shipments,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ]);
        } catch (\Exception $e) {
            Log::error('Exception during callback processing', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function fetchAndStoreStore($accessToken, $userId)
    {
        // طلب معلومات المتجر
        $storeUrl = config('salla.base_api_url') . 'store/info';
        $storeInformation = Http::withToken($accessToken)->acceptJson()->get($storeUrl);
        $storeData = json_decode($storeInformation->body(), true);

        Log::info('Store Information Response:', $storeData);

        $store = $storeData['data'] ?? [];

        // استخراج معلومات المتجر
        $storeInfo = [
            'salla_store_id' => $store['id'] ?? 'N/A',
            'user_id' => $userId,
            'name' => $store['name'] ?? 'N/A',
            'email' => $store['email'] ?? 'N/A',
            'status' => $store['status'] ?? 'N/A',
            'description' => $store['description'] ?? 'N/A',
            'domain' => $store['domain'] ?? 'N/A',
            'type' => $store['type'] ?? 'N/A',
            'plan' => $store['plan'] ?? 'N/A',
        ];

        // تحديث أو إنشاء المتجر في قاعدة البيانات
        $storedStore = Store::updateOrCreate(
            ['salla_store_id' => $storeInfo['salla_store_id']],
            [
                'user_id' => $storeInfo['user_id'],
                'name' => $storeInfo['name'],
                'email' => $storeInfo['email'],
                'status' => $storeInfo['status'],
                'description' => $storeInfo['description'],
                'domain' => $storeInfo['domain'],
                'type' => $storeInfo['type'],
                'plan' => $storeInfo['plan'],
            ]
        );

        return $storedStore;
    }
    public function fetchAndStoreUser($accessToken)
    {
        // طلب معلومات المستخدم
        $userUrl = config('salla.user_api_url') . 'user/info';
        $userInformation = Http::withToken($accessToken)->acceptJson()->get($userUrl);
        $userData = json_decode($userInformation->body(), true);

        Log::info('User Information Response:', $userData);

        $user = $userData['data'] ?? [];

        // تحديث أو إنشاء المستخدم في قاعدة البيانات
        $storedUser = User::updateOrCreate(
            ['salla_user_id' => $user['id']],
            [
                'name' => $user['name'] ?? 'N/A',
                'email' => $user['email'] ?? 'N/A',
                'mobile' => $user['mobile'] ?? 'N/A',
                'merchant_name' => $user['merchant']['name'] ?? 'N/A',
                'domain' => $user['merchant']['domain'] ?? 'N/A',
                'plan' => $user['merchant']['plan'] ?? 'N/A',
            ]
        );

        $storedUserData = [
            'salla_user_id' => $storedUser->salla_user_id,
            'name' => $storedUser->name,
            'email' => $storedUser->email,
            'mobile' => $storedUser->mobile,
            'merchant_name' => $storedUser->merchant_name,
            'domain' => $storedUser->domain,
            'plan' => $storedUser->plan,
        ];

        return $storedUser;
    }
    public function fetchAndStoreProducts($accessToken, $storeId)
    {
        // API URL to fetch products
        $productUrl = config('salla.base_api_url') . 'products';

        // Send GET request to fetch products
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($productUrl);

        // Decode the response body as JSON
        $data = json_decode($response->body(), true);

        // Initialize an array to store the products
        $productList = [];

        // Check if 'data' key exists and is an array
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $product) {
                // Collect product details
                $productDetails = [
                    'product_salla_id' => $product['id'] ?? 'غير متوفر',
                    'name' => $product['name'] ?? 'غير متوفر',
                    'qunatity' => $product['qunaitiy'] ?? 'غير متوفر',
                    'description' => $product['description'] ?? 'غير متوفر',
                    'status' => $product['status'] ?? 'غير متوفر',
                    'sku' => $product['sku'] ?? 'غير متوفر',
                    'price' => isset($product['price']['amount']) && isset($product['price']['currency'])
                        ? $product['price']['amount'] . ' ' . $product['price']['currency']
                        : 'غير متوفر',
                    'regular_price' => isset($product['regular_price']['amount']) && isset($product['regular_price']['currency'])
                        ? $product['regular_price']['amount'] . ' ' . $product['regular_price']['currency']
                        : 'غير متوفر',
                    'taxed_price' => isset($product['taxed_price']['amount']) && isset($product['taxed_price']['currency'])
                        ? $product['taxed_price']['amount'] . ' ' . $product['taxed_price']['currency']
                        : 'غير متوفر',
                    'pre_tax_price' => isset($product['pre_tax_price']['amount']) && isset($product['pre_tax_price']['currency'])
                        ? $product['pre_tax_price']['amount'] . ' ' . $product['pre_tax_price']['currency']
                        : 'غير متوفر',
                    'tax' => isset($product['tax']['amount']) && isset($product['tax']['currency'])
                        ? $product['tax']['amount'] . ' ' . $product['tax']['currency']
                        : 'غير متوفر',

                    'categories' => $product['categories']['name'] ?? 'غير متوفر',
                    'main_image' => $product['main_image'] ?? 'غير متوفر',
                    'is_available' => $product['is_available'] ?? 'غير متوفر',
                    'store_id' => $storeId,
                ];

                // Add the product details to the list
                $productList[] = $productDetails;
            }
        } else {
            // If no products are found, return a default message
            $productList[] = ['message' => 'لا توجد معلومات للمنتجات.'];
        }

        // Return the structured list of products
        return $productList;
    }
    public function fetchAndStoreShipments($accessToken, $storeId)
    {
        // API URL to fetch products
        $shipmentUrl = config('salla.base_api_url') . 'shipments';

        // Send GET request to fetch products
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($shipmentUrl);

        // Decode the response body as JSON
        $data = json_decode($response->body(), true);

        // Initialize an array to store the products
        $shipmentsList = [];

        // Check if 'data' key exists and is an array
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $shipment) {
                // Collect product details
                $shipmentDetails = [
                    'shipment_salla_id' => $shipment['id'] ?? 'غير متوفر',
                    'order_id' => $shipment['order_id'] ?? 'غير متوفر',
                    'courier_name' => $shipment['courier_name'] ?? 'غير متوفر',
                    'addresslineto' => $shipment['ship_to']['address_line'] ?? 'غير متوفر',
                    'city' => $shipment['ship_to']['city'] ?? 'غير متوفر',
                    'phone' => $shipment['ship_to']['phone'] ?? 'غير متوفر',
                    'country' => $shipment['ship_to']['country'] ?? 'غير متوفر',
                    'addresslinefrom' => $shipment['ship_from']['address_line'] ?? 'غير متوفر',
                    'store_id' => $storeId,
                ];

                // Add the product details to the list
                $shipmentsList[] = $shipmentDetails;
            }
        } else {
            // If no products are found, return a default message
            $shipmentsList[] = ['message' => 'لا توجد معلومات للشحن.'];
        }

        // Return the structured list of products
        return $shipmentsList;
    }
    public function fetchAndStoreCustomers($accessToken, $storeId)
    {
        $customersUrl = config('salla.base_api_url') . 'customers';
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($customersUrl);
        $data = json_decode($response->body(), true);
        $customersList = [];
        if (isset($data['data']) && is_array($data['data'])) {

            return $data['data'];
        } else {
            $customersList[] = ['message' => 'لا توجد معلومات للعملاء.'];
        }
    }
    public function fetchAndStoreProductsdetails($accessToken, $storeId, $productId)
    {
        // API URL لجلب معلومات المنتج باستخدام product_id
        $productUrl = config('salla.base_api_url') . 'products/' . $productId;

        // إرسال طلب GET لجلب تفاصيل المنتج
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($productUrl);

        // تحويل الاستجابة إلى JSON
        $data = json_decode($response->body(), true);

        // مصفوفة لتخزين تفاصيل المنتج
        $productList = [];

        // التحقق من وجود 'data' وأنها مصفوفة
        if (isset($data['data']) && is_array($data['data'])) {
            return $data;
            // جلب تفاصيل المنتج
            // $productDetails = [
            //     'product_salla_id' => $data['data']['id'] ?? 'غير متوفر',
            //     'name' => $data['data']['name'] ?? 'غير متوفر',
            //     'quantity' => $data['data']['quantity'] ?? 'غير متوفر',
            //     'description' => $data['data']['description'] ?? 'غير متوفر',
            //     'status' => $data['data']['status'] ?? 'غير متوفر',
            //     'sku' => $data['data']['sku'] ?? 'غير متوفر',
            //     'cost_price' => $data['data']['cost_price'] ?? 'غير متوفر',
            //     'sold_quantity' => $data['data']['sold_quantity'] ?? 'غير متوفر',
            //     'price' => isset($data['data']['price']['amount']) && isset($data['data']['price']['currency'])
            //         ? $data['data']['price']['amount'] . ' ' . $data['data']['price']['currency']
            //         : 'غير متوفر',
            //     'taxed_price' => isset($data['data']['taxed_price']['amount']) && isset($data['data']['taxed_price']['currency'])
            //         ? $data['data']['taxed_price']['amount'] . ' ' . $data['data']['taxed_price']['currency']
            //         : 'غير متوفر',
            //     'regular_price' => isset($data['data']['regular_price']['amount']) && isset($data['data']['regular_price']['currency'])
            //         ? $data['data']['regular_price']['amount'] . ' ' . $data['data']['regular_price']['currency']
            //         : 'غير متوفر',
            //     'pre_tax_price' => isset($data['data']['pre_tax_price']['amount']) && isset($data['data']['pre_tax_price']['currency'])
            //         ? $data['data']['pre_tax_price']['amount'] . ' ' . $data['data']['pre_tax_price']['currency']
            //         : 'غير متوفر',
            //     'tax' => isset($data['data']['tax']['amount']) && isset($data['data']['tax']['currency'])
            //         ? $data['data']['tax']['amount'] . ' ' . $data['data']['tax']['currency']
            //         : 'غير متوفر',
            //     'categories' => $data['data']['categories']['name'] ?? 'غير متوفر',
            //     'main_image' => $data['data']['main_image'] ?? 'غير متوفر',
            //     'is_available' => $data['data']['is_available'] ?? 'غير متوفر',
            //     'store_id' => $storeId,
            // ];

            // إضافة تفاصيل المنتج إلى المصفوفة
            // $productList[] = $productDetails;
        } else {
            // إذا لم يتم العثور على منتج، أضف رسالة افتراضية
            $productList[] = ['message' => 'لا توجد معلومات للمنتج.'];
        }

        // إرجاع تفاصيل المنتج
        return $productList;
    }
    public function fetchAndStoreOrdersdetails($accessToken, $storeId, $orderId)
    {
        // API URL لجلب معلومات الطلب باستخدام order_id
        $orderUrl = config('salla.base_api_url') . 'orders/' . $orderId;

        // إرسال طلب GET لجلب تفاصيل الطلب
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($orderUrl);

        // تحويل الاستجابة إلى JSON
        $data = json_decode($response->body(), true);

        // طباعة بيانات الطلب في الـ log
        Log::info('Order Data:', $data); // طباعة البيانات المستلمة بالكامل

        // مصفوفة لتخزين تفاصيل الطلب
        $orderDetailsList = [];

        // التحقق من وجود 'data' وأنها مصفوفة
        if (isset($data['data']) && is_array($data['data'])) {
            return $data;

            // جلب الخصومات إذا كانت موجودة
            // $discounts = isset($data['data']['amounts']['discounts']) && is_array($data['data']['amounts']['discounts'])
            //     ? $data['data']['amounts']['discounts']
            //     : [];

            // إذا كان يوجد أكثر من خصم واحد
            // if (!empty($discounts)) {
            //     foreach ($discounts as $discount) {
            //         $orderDetails = [
            //             'order_salla_id' => $data['data']['id'] ?? 'غير متوفر',
            //             'titleofdiscount' => $discount['title'] ?? 'غير متوفر',
            //             'typeofdiscount' => $discount['type'] ?? 'غير متوفر',
            //             'codeofdiscount' => $discount['code'] ?? 'غير متوفر',
            //             'discount' => $discount['discount'] ?? 'غير متوفر',
            //             'discounted_shipping' => $discount['discounted_shipping'] ?? 'غير متوفر',
            //             'store_id' => $storeId,
            //         ];

            //         // إضافة تفاصيل الخصم إلى المصفوفة
            //         $orderDetailsList[] = $orderDetails;
            //     }
            // } else {
            //     // إذا لم يكن هناك خصومات متاحة، أضف معرف الطلب إلى الرسالة
            //     $orderDetailsList[] = [
            //         'message' => 'لا توجد خصومات لهذا الطلب.',
            //         'order_salla_id' => $data['data']['id'] ?? 'غير متوفر'
            //     ];
            // }
        } else {
            // إذا لم يتم العثور على الطلب، أضف رسالة افتراضية
            $orderDetailsList[] = ['message' => 'لا توجد معلومات للطلب.'];
        }

        // إرجاع تفاصيل الطلب
        return $orderDetailsList;
    }
    public function fetchAndStoreOrders($accessToken, $storeId)
    {
        // URL الـ API لجلب الطلبات
        $ordersUrl = config('salla.base_api_url') . 'orders';

        // إرسال طلب GET لجلب الطلبات
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($ordersUrl);

        // تحويل استجابة الـ JSON إلى مصفوفة
        $data = json_decode($response->body(), true);

        // التحقق من وجود المفتاح 'data' وأنه مصفوفة
        if (isset($data['data']) && is_array($data['data'])) {
            // إضافة store_id لكل طلب
            foreach ($data['data'] as &$order) {
                $order['store_id'] = $storeId;
            }

            // إرجاع كل بيانات الطلبات
            return $data['data'];
        } else {
            // إذا لم توجد طلبات، إرجاع رسالة افتراضية
            return ['message' => 'لا توجد معلومات للطلبات.'];
        }
    }
    public function fetchAndStoreInvoices($accessToken, $storeId)
    {
        // URL الـ API لجلب الطلبات
        $invoicesUrl = config('salla.base_api_url') . 'orders/invoices';

        // إرسال طلب GET لجلب الطلبات
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($invoicesUrl);

        // تحويل استجابة الـ JSON إلى مصفوفة
        $data = json_decode($response->body(), true);
        // dd($data);

        // التحقق من وجود المفتاح 'data' وأنه مصفوفة
        if (isset($data['data']) && is_array($data['data'])) {
            // إضافة store_id لكل طلب
            foreach ($data['data'] as &$order) {
                $order['store_id'] = $storeId;
            }

            // إرجاع كل بيانات الطلبات
            return $data['data'];
        } else {
            // إذا لم توجد طلبات، إرجاع رسالة افتراضية
            return ['message' => 'لا توجد معلومات للاوردارات.'];
        }
    }
}
