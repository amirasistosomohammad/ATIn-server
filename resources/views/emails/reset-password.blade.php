<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password – {{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8faf8;">
    <div style="max-width: 560px; margin: 0 auto; padding: 24px;">
        <div style="background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); border: 1px solid rgba(12, 138, 59, 0.12);">
            <h1 style="color: #0C8A3B; font-size: 1.35rem; margin: 0 0 8px 0;">Reset Your Password</h1>
            <p style="color: #4a5c4a; font-size: 0.95rem; margin: 0 0 24px 0;">{{ config('app.name') }}</p>

            <p style="color: #1a2a1a; font-size: 1rem; line-height: 1.5;">Dear {{ $name }},</p>
            <p style="color: #4a5c4a; font-size: 1rem; line-height: 1.5;">You requested a password reset. Click the button below to set a new password. This link expires in {{ $expiresInMinutes }} minutes.</p>

            <p style="margin: 24px 0;">
                <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #0C8A3B; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 1rem;">Reset password</a>
            </p>

            <p style="color: #6b7280; font-size: 0.9rem;">If you did not request this, you can safely ignore this email. Your password will not be changed.</p>

            <p style="color: #6b7280; font-size: 0.85rem; word-break: break-all;">Or copy this link: {{ $resetUrl }}</p>

            <p style="color: #4a5c4a; font-size: 0.85rem; margin-top: 28px; padding-top: 20px; border-top: 1px solid #e0e6e0;">{{ config('app.name') }} — Official communication.</p>
        </div>
    </div>
</body>
</html>
