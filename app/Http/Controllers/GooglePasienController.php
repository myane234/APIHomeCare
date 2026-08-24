<?php

namespace App\Http\Controllers;

use App\Models\Pasien;
use App\Models\Users;
use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

/**
 * @group User Authentication
 * 
 * Endpoint Login Google Pasien / Nakes
 */
class GooglePasienController extends Controller
{
  public function handleGoogleCallback(Request $request)
  {
    $request->validate([
      'access_token' => 'required|string',
    ]);

    try {
      /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
      $driver = Socialite::driver('google');
      $googleUser = $driver->userFromToken($request->access_token);

      $user = DB::transaction(function () use ($googleUser) {
    
        $checkUser = Users::where('email', $googleUser->getEmail())->first();

   
        $googleAvatar = $googleUser->getAvatar() ?? "https://lh3.googleusercontent.com/a/default-user=s96-c";

        if (!$checkUser) {
          $newUser = Users::create([
            'email'     => $googleUser->getEmail(),
            'password'  => null,
            'is_active' => true,
            'google_id' => $googleUser->getId(),
            'avatar'    => $googleAvatar,
          ]);

          $role = Role::firstOrCreate(['nama_role' => 'pasien']);
          $newUser->roles()->attach($role->nama_role);

  
          Pasien::create([
            'id_user'        => $newUser->id_user,
            'nama_lengkap'   => $googleUser->getName() ?? 'Guest',
            'nik'            => null,
            'golongan_darah' => null,
            'jenis_kelamin'  => null,
            'alamat_utama'   => null,
            'avatar'         => $googleAvatar,
          ]);

          return $newUser;
        } 
        
        if (!$checkUser->google_id) {
          $checkUser->update([
            'google_id' => $googleUser->getId(),
            'avatar'    => $googleAvatar,
          ]);
        } else {
          // Always update avatar from Google on every login
          $checkUser->update(['avatar' => $googleAvatar]);
        }

        // Always update Pasien avatar as well
        $pasien = Pasien::where('id_user', $checkUser->id_user)->first();
        if ($pasien) {
          $pasien->update(['avatar' => $googleAvatar]);
        }

        return $checkUser;
      });

      if (!$user->is_active) {
        return response()->json([
          'success' => false,
          'message' => 'Akun Anda telah dinonaktifkan oleh admin.',
        ], 403);
      }

      $user->load(['pasien', 'tenagaMedis']); 
      $userRoles = $user->roles()->pluck('roles.nama_role')->toArray();

      $pasien = $user->pasien;
      $isProfileComplete = $pasien && $pasien->nik && $pasien->golongan_darah && $pasien->jenis_kelamin && $pasien->alamat_utama && $user->password;

      $token = $user->createToken('auth-token')->plainTextToken;

      return response()->json([
        'success'             => true,
        'message'             => 'Login Google Berhasil',
        'token'               => $token,
        'user'                => $user,
        'roles'               => $userRoles,
        'is_profile_complete' => (bool) $isProfileComplete
      ], 200);

    } catch (Exception $e) {
      Log::error($e->getMessage());

      return response()->json([
        'success' => false,
        'message' => 'Login Google Gagal',
      ], 500);
    }
  }
}