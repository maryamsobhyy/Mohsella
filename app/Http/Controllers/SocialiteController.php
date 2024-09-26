<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirectToGoogle(){

        return Socialite::driver('google')->redirect();
    }
    public function handleGoogleCallback()
{
    try {
        // استخدم `stateless` لتجنب استخدام الجلسات عند التعامل مع API
        $user = Socialite::driver('google')->stateless()->user();

        // هنا يمكنك الوصول إلى الـ access token
        $accessToken = $user->token; // هذا هو الـ access token

        // ابحث عن المستخدم في قاعدة البيانات باستخدام البريد الإلكتروني
        $findUser = User::where('email', $user->email)->first();

        if ($findUser) {
            // تسجيل الدخول للمستخدم إذا كان موجودًا
            Auth::login($findUser);

            // استجابة JSON ناجحة مع بيانات المستخدم
            return response()->json([
                'message' => 'User logged in successfully',
                'user' => $findUser,
                'access_token' => $accessToken // إرجاع الـ access token
            ], 200);
        } else {
            // إنشاء مستخدم جديد إذا لم يتم العثور على المستخدم
            $newUser = new User([
                'name' => $user->name,
                'email' => $user->email,
                'salla_user_id' => $user->id,
            ]);

            // تسجيل الدخول للمستخدم الجديد
            Auth::login($newUser);

            // استجابة JSON للمستخدم الجديد
            return response()->json([
                'message' => 'User registered and logged in successfully',
                'user' => $newUser,
                'access_token' => $accessToken // إرجاع الـ access token
            ], 201);
        }

    } catch (Exception $e) {
        // استجابة JSON في حال حدوث خطأ
        return response()->json([
            'error' => 'Something went wrong',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
}

}
