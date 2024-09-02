<?php

namespace App\Http\Controllers;

use App\Models\OauthToken;
use Illuminate\Http\Request;
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
        $data = [
            'client_id' => config('salla.client_id'),
            'client_secret' => config('salla.client_secret'),
            'code' => $request->code,
            'scope' => 'offline_access',
            'redirect_url' => config('salla.callback_url'),
            'state' => rand(11111111, 99999999),
            'grant_type' => 'authorization_code',
        ];

        // // تحديث أو إنشاء سجل التوكن
        OauthToken::updateOrCreate(
            ['client_id' => config('salla.client_id')],
            [
                'client_secret' => config('salla.client_secret'),
                'code' => $request->code,
                'scope' => 'offline_access',
                'redirect_url' => config('salla.callback_url'),
                'state' => $request->state,
                'grant_type' => 'authorization_code',
            ]
        );

        // طلب التوكن
        $response = Http::asForm()->post(config('salla.token_url'), $data);
        $jsonresponse = json_decode($response->body());

        if ($response->successful()) {
            $accessToken = $jsonresponse->access_token;

            // استرجاع بيانات المتجر
            $storeUrl = config('salla.base_api_url') . '/store/info';
            $storeInformation = Http::withToken($accessToken)->acceptJson()->get($storeUrl);
            $storeData = json_decode($storeInformation->body(), true);

            // استرجاع بيانات المستخدم
            $userUrl = config('salla.base_api_url') . '/users';
            $userInformation = Http::withToken($accessToken)->acceptJson()->get($userUrl);
            $userData = json_decode($userInformation->body(), true);

            // استرجاع بيانات المنتجات
            $productsUrl = config('salla.base_api_url') . '/products';
            $productsResponse = Http::withToken($accessToken)->acceptJson()->get($productsUrl);
            $productsData = json_decode($productsResponse->body(), true);

            // استرجاع بيانات الطلبات
            $ordersUrl = config('salla.base_api_url') . '/orders';
            $ordersResponse = Http::withToken($accessToken)->acceptJson()->get($ordersUrl);
            $ordersData = json_decode($ordersResponse->body(), true);

            // إرجاع البيانات في استجابة JSON
            return response()->json([
                'status' => 'success',
                'store_data' => $storeData,
                'user_data' => $userData,
                'products_data' => $productsData,
                'orders_data' => $ordersData,
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch store or user information',
            ]);
        }
    }
}
