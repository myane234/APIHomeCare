<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Users;
use App\Models\Admin;
use App\Models\PasswordResetOtp;
use App\Mail\ResetPasswordOtpMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    /**
     * Step 1 — Request OTP (Pasien / Nakes)
     */
    public function requestOtpUser(Request $request)
    {
        return $this->handleRequestOtp($request, 'user');
    }

    /**
     * Step 2 — Verifikasi OTP (Pasien / Nakes)
     */
    public function verifyOtpUser(Request $request)
    {
        return $this->handleVerifyOtp($request, 'user');
    }

    /**
     * Step 3 — Reset Password (Pasien / Nakes)
     */
    public function resetPasswordUser(Request $request)
    {
        return $this->handleResetPassword($request, 'user');
    }

    /**
     * Step 1 — Request OTP (Admin)
     */
    public function requestOtpAdmin(Request $request)
    {
        return $this->handleRequestOtp($request, 'admin');
    }

    /**
     * Step 2 — Verifikasi OTP (Admin)
     */
    public function verifyOtpAdmin(Request $request)
    {
        return $this->handleVerifyOtp($request, 'admin');
    }

    /**
     * Step 3 — Reset Password (Admin)
     */
    public function resetPasswordAdmin(Request $request)
    {
        return $this->handleResetPassword($request, 'admin');
    }

    /**
     * Internal logic for Step 1 — Request OTP
     */
    private function handleRequestOtp(Request $request, string $userType)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $email = $request->input('email');

        // Check whether account exists for the given type
        if ($userType === 'admin') {
            $account = Admin::where('email', $email)->first();
        } else {
            $account = Users::where('email', $email)->first();
        }

        if ($account) {
            // Generate 6 digit numeric OTP
            $otp = sprintf('%06d', random_int(0, 999999));

            // Store or update OTP record (valid for 10 minutes)
            PasswordResetOtp::updateOrCreate(
                [
                    'email' => $email,
                    'user_type' => $userType,
                ],
                [
                    'otp' => $otp,
                    'otp_expires_at' => Carbon::now()->addMinutes(10),
                    'reset_token' => null,
                    'token_expires_at' => null,
                ]
            );

            // Send OTP email
            try {
                Mail::to($email)->send(new ResetPasswordOtpMail($otp, $userType));
            } catch (\Throwable $e) {
                Log::error("Gagal mengirim email OTP reset password ke {$email}: " . $e->getMessage());
            }
        }

        // Response is always success (200) for security reasons
        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, kode OTP telah dikirim.',
        ], 200);
    }

    /**
     * Internal logic for Step 2 — Verifikasi OTP
     */
    private function handleVerifyOtp(Request $request, string $userType)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak valid.',
            ], 400);
        }

        $email = $request->input('email');
        $otp = (string) $request->input('otp');

        $record = PasswordResetOtp::where('email', $email)
            ->where('user_type', $userType)
            ->first();

        if (!$record || empty($record->otp) || (string) $record->otp !== $otp) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak valid.',
            ], 400);
        }

        if ($record->otp_expires_at && Carbon::parse($record->otp_expires_at)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.',
            ], 400);
        }

        // OTP is valid -> generate reset_token (valid for 15 minutes)
        $resetToken = Str::random(64);

        $record->update([
            'otp' => null,
            'otp_expires_at' => null,
            'reset_token' => $resetToken,
            'token_expires_at' => Carbon::now()->addMinutes(15),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP valid. Silakan masukkan password baru Anda.',
            'reset_token' => $resetToken,
        ], 200);
    }

    /**
     * Internal logic for Step 3 — Reset Password
     */
    private function handleResetPassword(Request $request, string $userType)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            // If the failure is purely related to reset_token, or validation errors
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $email = $request->input('email');
        $resetToken = $request->input('reset_token');
        $newPassword = $request->input('password');

        $record = PasswordResetOtp::where('email', $email)
            ->where('user_type', $userType)
            ->where('reset_token', $resetToken)
            ->first();

        if (!$record || empty($record->token_expires_at) || Carbon::parse($record->token_expires_at)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Token reset tidak valid atau sudah kadaluarsa.',
            ], 400);
        }

        // Find user/admin account and update password
        if ($userType === 'admin') {
            $account = Admin::where('email', $email)->first();
        } else {
            $account = Users::where('email', $email)->first();
        }

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Token reset tidak valid atau sudah kadaluarsa.',
            ], 400);
        }

        $account->password = Hash::make($newPassword);
        $account->save();

        // Invalidate the reset token
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset. Silakan login dengan password baru Anda.',
        ], 200);
    }
}
