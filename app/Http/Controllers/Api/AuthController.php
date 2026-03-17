<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 15;

    /**
     * Register: create user (or update if exists unverified), send OTP email.
     */
    public function register(Request $request): JsonResponse
    {
        $request->merge(['password_confirmation' => $request->input('confirmPassword')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'section_unit' => ['required', 'string', 'max:255'],
            'designation_position' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $existing = User::where('email', $validated['email'])->first();

        if ($existing && $existing->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Please sign in.'],
            ]);
        }

        if ($existing && ! $existing->email_verified_at) {
            $existing->update([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'section_unit' => $validated['section_unit'],
                'designation_position' => $validated['designation_position'],
            ]);
            $user = $existing;
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'section_unit' => $validated['section_unit'],
                'designation_position' => $validated['designation_position'],
            ]);
        }

        $this->sendOtpToUser($user);

        return response()->json([
            'message' => 'Check your email for the 6-digit code to verify your account.',
            'email' => $user->email,
            'user' => $user->only(['id', 'name', 'email', 'role', 'section_unit', 'designation_position']),
        ], 201);
    }

    /**
     * Verify email with OTP.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['No account found for this email.']]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified. You may log in.',
            ]);
        }

        if (! $user->otp || $user->otp !== $validated['otp']) {
            throw ValidationException::withMessages(['otp' => ['Invalid or expired OTP. Please try again.']]);
        }

        if ($user->otp_expires_at && $user->otp_expires_at->isPast()) {
            $user->update(['otp' => null, 'otp_expires_at' => null]);
            throw ValidationException::withMessages(['otp' => ['OTP has expired. Please request a new one.']]);
        }

        $user->update([
            'email_verified_at' => now(),
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Email verified successfully. You can now sign in.',
        ]);
    }

    /**
     * Resend OTP to email.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['No account found for this email.']]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified. You may log in.',
            ]);
        }

        $this->sendOtpToUser($user);

        return response()->json([
            'message' => 'A new code has been sent to your email. It expires in ' . self::OTP_EXPIRY_MINUTES . ' minutes.',
        ]);
    }

    /**
     * Login: validate email/password, issue API token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return response()->json([
                'message' => 'This account has been deactivated. Please contact an administrator.',
                'code' => 'ACCOUNT_INACTIVE',
                'user' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'section_unit', 'designation_position']),
                'deactivation_remarks' => $user->deactivation_remarks,
            ], 403);
        }

        $user->tokens()->where('name', 'auth')->delete();
        $token = $user->createToken('auth', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'section_unit', 'designation_position']),
        ]);
    }

    /**
     * Change password for authenticated user.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'confirmed',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
            ],
        ], [
            'new_password.regex' => 'Password must contain at least one letter and one number.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $validated['new_password'],
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Forgot password: send reset link only if account exists and email is verified.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            Log::info('Forgot password: no user found.', ['email' => $validated['email']]);
        } elseif (! $user->email_verified_at) {
            Log::info('Forgot password: email not verified, not sending.', ['email' => $user->email]);
        }

        if ($user && $user->email_verified_at) {
            $token = Str::random(64);
            $hashedToken = Hash::make($token);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => $hashedToken,
                    'created_at' => now(),
                ]
            );

            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
            $resetUrl = $frontendUrl . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email);

            try {
                Log::info('Sending password reset email to verified user.', ['email' => $user->email]);
                Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $user->name, 60));
                Log::info('Password reset email sent successfully.', ['email' => $user->email]);
            } catch (\Throwable $e) {
                Log::error('Password reset email failed.', [
                    'email' => $user->email,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
                return response()->json([
                    'message' => 'We could not send the reset email. ' . (config('app.debug') ? $e->getMessage() : 'Please try again later or contact support.'),
                    'success' => false,
                ], 503);
            }
        }

        return response()->json([
            'message' => 'If an account exists with this email, a password reset link has been sent. Please check your inbox.',
            'success' => true,
        ]);
    }

    /**
     * Reset password: validate token and set new password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
            ],
        ], [
            'password.regex' => 'Password must contain at least one letter and one number.',
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();

        if (! $row || ! Hash::check($validated['token'], $row->token)) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired. Please request a new one.'],
            ]);
        }

        $createdAt = \Carbon\Carbon::parse($row->created_at);
        if ($createdAt->copy()->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset link has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();
        if (! $user) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            throw ValidationException::withMessages([
                'email' => ['No account found for this email.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Your password has been reset successfully. You can now sign in with your new password.',
            'success' => true,
        ]);
    }

    /**
     * Get authenticated user (requires Bearer token).
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'section_unit', 'designation_position']),
        ]);
    }

    /**
     * Update authenticated user's profile (name, section_unit, designation_position). Email cannot be changed.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'section_unit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'designation_position' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'section_unit' => $validated['section_unit'] ?? $user->section_unit,
            'designation_position' => $validated['designation_position'] ?? $user->designation_position,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'section_unit', 'designation_position']),
        ]);
    }

    /**
     * Logout: revoke current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function sendOtpToUser(User $user): void
    {
        $otp = (string) random_int(100000, 999999);
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        Mail::to($user->email)->send(new OtpMail($otp, $user->name, self::OTP_EXPIRY_MINUTES));
    }
}
