<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to ALICE</title>
</head>
<body style="margin:0; padding:0; background-color:#FAF6EE; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FAF6EE; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#FFFFFF; border:3px solid #2D231E; border-radius:16px; box-shadow:6px 6px 0 0 #2D231E;">
                    <tr>
                        <td style="padding:32px 28px;">
                            <p style="margin:0 0 24px; font-size:22px; font-weight:bold; color:#2D231E; letter-spacing:2px;">ALICE</p>

                            <p style="margin:0 0 16px; font-size:20px; font-weight:bold; color:#2D231E;">You've been added as a teacher</p>

                            <p style="margin:0 0 24px; font-size:14px; line-height:22px; color:#8C7B70;">
                                Here are your sign-in details. Use them to access your ALICE account.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" style="background-color:#FBBF24; border:2px solid #2D231E; border-radius:12px; box-shadow:4px 4px 0 0 #2D231E;">
                                        <a href="{{ url('/login') }}" style="display:inline-block; padding:14px 28px; font-size:14px; font-weight:bold; color:#2D231E; text-decoration:none;">SIGN IN</a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#FAF6EE; border:2px solid #E6DCCF; border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 20px;">
                                        <p style="margin:0 0 4px; font-size:12px; font-weight:bold; color:#8C7B70; text-transform:uppercase;">Email</p>
                                        <p style="margin:0 0 12px; font-size:13px; font-weight:bold; color:#2D231E; word-break:break-all;">{{ $user->email }}</p>
                                        <p style="margin:0 0 4px; font-size:12px; font-weight:bold; color:#8C7B70; text-transform:uppercase;">Password</p>
                                        <p style="margin:0; font-size:13px; font-weight:bold; color:#2D231E; word-break:break-all;">{{ $password }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 24px; font-size:14px; line-height:22px; color:#8C7B70;">
                                Set your own password after signing in under <strong style="color:#2D231E;">Settings</strong>.
                            </p>

                            <p style="margin:0; padding-top:20px; border-top:2px dashed #E6DCCF; font-size:12px; line-height:18px; color:#8C7B70;">
                                If you were not expecting this email, you can ignore it. Please keep this temporary password private.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
