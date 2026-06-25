<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.07);">
                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); padding: 32px 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">
                                🤝 Gift of Hope
                            </h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #1f2937; font-size: 22px; margin: 0 0 8px 0; font-weight: 700;">
                                Password Reset Request
                            </h2>
                            <p style="color: #6b7280; font-size: 15px; margin: 0 0 28px 0; line-height: 1.6;">
                                Hi <strong style="color: #374151;">{{ $fName }}</strong>, we received a request to reset your password. Use the code below to proceed:
                            </p>

                            {{-- Code Box --}}
                            <div style="background-color: #eff6ff; border: 2px dashed #3B82F6; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 28px;">
                                <p style="color: #6b7280; font-size: 13px; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">
                                    Your Reset Code
                                </p>
                                <p style="color: #1E3A8A; font-size: 36px; font-weight: 800; margin: 0; letter-spacing: 8px; font-family: 'Courier New', monospace;">
                                    {{ $resetCode }}
                                </p>
                            </div>

                            <p style="color: #6b7280; font-size: 14px; margin: 0 0 8px 0; line-height: 1.6;">
                                ⏱️ This code will expire in <strong style="color: #374151;">5 minutes</strong>.
                            </p>
                            <p style="color: #6b7280; font-size: 14px; margin: 0; line-height: 1.6;">
                                If you didn't request a password reset, you can safely ignore this email. Your account is secure.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 40px; border-top: 1px solid #e5e7eb; text-align: center;">
                            <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                                © {{ date('Y') }} Gift of Hope. All rights reserved.
                            </p>
                            <p style="color: #9ca3af; font-size: 12px; margin: 6px 0 0 0;">
                                This is an automated email — please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
