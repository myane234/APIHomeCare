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
        // 1. Cari user berdasarkan email
        $checkUser = Users::where('email', $googleUser->getEmail())->first();

        if (!$checkUser) {
          $newUser = Users::create([
            'email'     => $googleUser->getEmail(),
            'password'  => null,
            'is_active' => true,
            'google_id' => $googleUser->getId(),
          ]);

          $role = Role::firstOrCreate(['nama_role' => 'pasien']);
          $newUser->roles()->attach($role->nama_role);

          // Buat record profil Pasien awal
          Pasien::create([
            'id_user'        => $newUser->id_user,
            'nama_lengkap'   => $googleUser->getName() ?? 'Guest',
            'nik'            => null,
            'golongan_darah' => null,
            'jenis_kelamin'  => null,
            'alamat_utama'   => null,
          ]);

          return $newUser;
        } 
        
        if (!$checkUser->google_id) {
          $checkUser->update(['google_id' => $googleUser->getId()]);
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