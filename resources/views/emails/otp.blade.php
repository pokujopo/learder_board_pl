<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawacode Verification Code</title>
</head>

<body style="margin:0; padding:0; background:#f4f6f8; font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f8; padding:40px 15px;">
    <tr>
        <td align="center">

            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:520px; background:#ffffff; border-radius:12px; overflow:hidden;">

                <tr>
                    <td align="center" style="padding:35px 30px 20px;">
                        <img
                            src="{{ asset('images/pawacode-logo.png') }}"
                            alt="Pawacode"
                            width="150"
                            style="display:block; max-width:150px; height:auto;"
                        >
                    </td>
                </tr>

                <tr>
                    <td style="padding:10px 35px 35px; color:#1f2937;">

                        <h1 style="margin:0 0 20px; text-align:center; font-size:24px;">
                            Verification Code
                        </h1>

                        <p style="font-size:15px; line-height:1.6; margin:0 0 20px;">
                            Use the verification code below to continue with your Pawacode account.
                        </p>

                        <div style="text-align:center; margin:30px 0;">
                            <span style="
                                display:inline-block;
                                padding:15px 28px;
                                background:#f3f4f6;
                                border-radius:8px;
                                font-size:30px;
                                font-weight:bold;
                                letter-spacing:8px;
                                color:#111827;
                            ">
                                {{ $token }}
                            </span>
                        </div>

                        <p style="font-size:14px; line-height:1.6; color:#6b7280; margin:0;">
                            This verification code will expire according to the security settings of your account.
                        </p>

                    </td>
                </tr>

                <tr>
                    <td align="center"
                        style="padding:20px 30px; background:#f9fafb; color:#9ca3af; font-size:12px;">
                        © {{ date('Y') }} Pawacode. All rights reserved.
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>