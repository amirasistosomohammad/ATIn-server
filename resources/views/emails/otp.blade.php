<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification – {{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8faf8;">
    <div style="max-width: 560px; margin: 0 auto; padding: 24px;">
        <div style="background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); border: 1px solid rgba(12, 138, 59, 0.12);">
            <h1 style="color: #0C8A3B; font-size: 1.35rem; margin: 0 0 8px 0;">Email Verification</h1>
            <p style="color: #4a5c4a; font-size: 0.95rem; margin: 0 0 24px 0;">{{ config('app.name') }}</p>

            <p style="color: #1a2a1a; font-size: 1rem; line-height: 1.5;">Dear {{ $name }},</p>
            <p style="color: #4a5c4a; font-size: 1rem; line-height: 1.5;">A verification code was requested for your account. Enter the code below to complete verification.</p>

            <div style="background: rgba(12, 138, 59, 0.08); border: 1px solid rgba(12, 138, 59, 0.2); border-radius: 8px; padding: 16px 20px; margin: 24px 0; text-align: center;">
                <p style="color: #4a5c4a; font-size: 0.85rem; margin: 0 0 4px 0;">Verification code</p>
                <p style="color: #0C8A3B; font-size: 1.75rem; font-weight: 700; letter-spacing: 0.2em; margin: 0;">{{ $otp }}</p>
            </div>

            <p style="color: #6b7280; font-size: 0.9rem; line-height: 1.5;">This code expires in {{ $expiresInMinutes }} minutes. Do not share it with anyone.</p>
            <p style="color: #6b7280; font-size: 0.9rem;">If you did not request this code, disregard this email.</p>

            <p style="color: #4a5c4a; font-size: 0.85rem; margin-top: 28px; padding-top: 20px; border-top: 1px solid #e0e6e0;">{{ config('app.name') }} — Official communication. Do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
