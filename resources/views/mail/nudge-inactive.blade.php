<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alice misses you</title>
</head>
<body style="margin:0; padding:0; background-color:#FAF6EE; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FAF6EE; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#FFFFFF; border:3px solid #2D231E; border-radius:16px; box-shadow:6px 6px 0 0 #2D231E;">
                    <tr>
                        <td style="padding:32px 28px;">
                            <p style="margin:0 0 24px; font-size:22px; font-weight:bold; color:#2D231E; letter-spacing:2px;">ALICE</p>

                            <p style="margin:0 0 16px; font-size:20px; font-weight:bold; color:#2D231E;">Hi {{ $student->first_name ?? $student->nickname }}, Alice misses you.</p>

                            <p style="margin:0 0 16px; font-size:14px; line-height:22px; color:#2D231E;">
                                Your books are still here, keeping your place. Stories do not leave
                                in the middle, and neither will yours.
                            </p>

                            <p style="margin:0 0 24px; font-size:14px; line-height:22px; color:#8C7B70;">
                                A few minutes of reading is enough to keep the streak alive. Come back.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
                                <tr>
                                    <td style="background-color:#2D231E; border-radius:12px;">
                                        <a href="{{ url('/') }}" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#FAF6EE; text-decoration:none;">Back to reading</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; padding-top:20px; border-top:2px dashed #E6DCCF; font-size:12px; line-height:18px; color:#8C7B70;">
                                This message came from your reading app, Alice.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
