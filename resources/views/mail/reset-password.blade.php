<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your ALICE password</title>
</head>
<body style="margin:0; padding:0; background-color:#FAF6EE; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FAF6EE; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#FFFFFF; border:3px solid #2D231E; border-radius:16px; box-shadow:6px 6px 0 0 #2D231E;">
                    <tr>
                        <td style="padding:32px 28px;">
                            <p style="margin:0 0 24px; font-size:22px; font-weight:bold; color:#2D231E; letter-spacing:2px;">ALICE</p>

                            <p style="margin:0 0 16px; font-size:20px; font-weight:bold; color:#2D231E;">Reset your password</p>

                            <p style="margin:0 0 24px; font-size:14px; line-height:22px; color:#8C7B70;">
                                We received a request to reset the password for this account. Tap the button below to choose a new one.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" style="background-color:#FBBF24; border:2px solid #2D231E; border-radius:12px; box-shadow:4px 4px 0 0 #2D231E;">
                                        <a href="{{ $url }}" style="display:inline-block; padding:14px 28px; font-size:14px; font-weight:bold; color:#2D231E; text-decoration:none;">RESET PASSWORD</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; font-size:12px; line-height:18px; color:#8C7B70;">
                                This link expires in {{ $expire }} minutes. If the button above does not work, copy and paste this address into your browser:
                            </p>

                            <p style="margin:0 0 24px; font-size:12px; line-height:18px; color:#2D231E; word-break:break-all;">{{ $url }}</p>

                            <p style="margin:0; padding-top:20px; border-top:2px dashed #E6DCCF; font-size:12px; line-height:18px; color:#8C7B70;">
                                If you did not ask to reset your password, you can ignore this email. Your password will stay the same.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
