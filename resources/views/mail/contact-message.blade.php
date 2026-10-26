<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New ALICE contact message</title>
</head>
<body style="margin:0; padding:0; background-color:#FAF6EE; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FAF6EE; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#FFFFFF; border:3px solid #2D231E; border-radius:16px; box-shadow:6px 6px 0 0 #2D231E;">
                    <tr>
                        <td style="padding:32px 28px;">
                            <p style="margin:0 0 24px; font-size:22px; font-weight:bold; color:#2D231E; letter-spacing:2px;">ALICE</p>

                            <p style="margin:0 0 16px; font-size:20px; font-weight:bold; color:#2D231E;">A visitor sent a message</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#FAF6EE; border:2px solid #E6DCCF; border-radius:12px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 8px; font-size:12px; font-weight:bold; color:#8C7B70; text-transform:uppercase;">Topic</p>
                                        <p style="margin:0 0 16px; font-size:13px; font-weight:bold; color:#2D231E;">{{ $topic }}</p>
                                        <p style="margin:0 0 8px; font-size:12px; font-weight:bold; color:#8C7B70; text-transform:uppercase;">From</p>
                                        <p style="margin:0 0 16px; font-size:13px; font-weight:bold; color:#2D231E;">{{ $name }} &lt;{{ $email }}&gt;</p>
                                        <p style="margin:0 0 8px; font-size:12px; font-weight:bold; color:#8C7B70; text-transform:uppercase;">Message</p>
                                        <p style="margin:0; font-size:14px; line-height:22px; color:#2D231E; white-space:pre-line;">{{ $body }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; padding-top:20px; border-top:2px dashed #E6DCCF; font-size:12px; line-height:18px; color:#8C7B70;">
                                Sent from the ALICE Contact Us page. If you were not expecting this, you can ignore it.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
