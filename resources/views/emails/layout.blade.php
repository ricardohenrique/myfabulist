<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#f7f4fc;color:#27313c;font-family:'Segoe UI',Arial,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">@yield('preheader')</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f4fc;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                    <tr>
                        <td style="padding:0 4px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" width="42" height="42" style="width:42px;height:42px;border-radius:10px;background:#8b6fd6;color:#ffffff;font-size:24px;font-weight:700;line-height:42px;">✓</td>
                                    <td style="padding-left:12px;color:#27313c;font-size:19px;font-weight:700;">Purplelist</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="overflow:hidden;border:1px solid #e2ddeb;border-radius:16px;background:#ffffff;box-shadow:0 14px 34px rgba(70,54,105,.10);">
                            <div style="height:6px;background:#8b6fd6;"></div>
                            <div style="padding:42px 44px 38px;">
                                <p style="margin:0 0 12px;color:#8b6fd6;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;">@yield('eyebrow')</p>
                                <h1 style="margin:0 0 18px;color:#27313c;font-size:30px;font-weight:700;letter-spacing:-.8px;line-height:1.2;">@yield('heading')</h1>
                                @yield('content')
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:30px 0 26px;">
                                    <tr>
                                        <td align="center" style="border-radius:9px;background:#8b6fd6;">
                                            <a href="@yield('actionUrl')" style="display:inline-block;padding:14px 22px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">@yield('actionLabel')</a>
                                        </td>
                                    </tr>
                                </table>
                                @yield('afterAction')
                                <p style="margin:28px 0 0;padding-top:24px;border-top:1px solid #ece9f0;color:#8a949d;font-size:12px;line-height:1.6;">
                                    If the button does not work, copy and paste this link into your browser:<br>
                                    <a href="@yield('actionUrl')" style="color:#7659c4;word-break:break-all;">@yield('actionUrl')</a>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 12px 0;color:#929ba3;font-size:12px;line-height:1.6;">
                            Simple lists. Clear days. A little more breathing room.<br>
                            © {{ now()->year }} Purplelist
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
