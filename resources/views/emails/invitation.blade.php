<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings xmlns:o="urn:schemas-microsoft-com:office:office">
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <style>
        td, th, div, p, a, h1, h2, h3, h4, h5, h6 { font-family: "Segoe UI", sans-serif; }
    </style>
    <![endif]-->
    <title>{{ $subject }}</title>
</head>
<body style="margin: 0; padding: 0; width: 100%; -webkit-font-smoothing: antialiased; word-break: break-word; background-color: #f4f5f7;">
    <!-- Preview text -->
    <div style="display: none; max-height: 0; overflow: hidden;">
        {{ $inviterName }} has invited you to join {{ $tenantName }} &mdash; set up your account to get started.
        &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f7;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <!-- Main container -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px;">

                    <!-- Brand Header -->
                    <tr>
                        <td align="center" style="padding-bottom: 32px;">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $tenantName }}" width="48" height="48" style="display: block; border-radius: 10px; border: 0;" />
                            @else
                                <p style="margin: 0; font-size: 20px; font-weight: 700; color: #111827; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                    {{ $tenantName }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    <!-- Card -->
                    <tr>
                        <td style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                            <!-- Accent bar -->
                            <div style="height: 4px; border-radius: 12px 12px 0 0; background-color: {{ $primaryColor }};"></div>

                            <!-- Card inner -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 44px 40px 40px;">

                                        <!-- Greeting -->
                                        <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 700; color: #111827; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.3;">
                                            You're invited to join {{ $tenantName }}
                                        </h1>

                                        <p style="margin: 0 0 28px; font-size: 15px; color: #6b7280; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6;">
                                            Hello {{ $userName }},
                                        </p>

                                        <!-- Invitation detail card -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 28px;">
                                            <tr>
                                                <td style="background-color: #f9fafb; border-radius: 8px; border: 1px solid #f0f0f3; padding: 20px 24px;">
                                                    <p style="margin: 0 0 4px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                                        Invited by
                                                    </p>
                                                    <p style="margin: 0 0 16px; font-size: 15px; font-weight: 600; color: #111827; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                                        {{ $inviterName }}
                                                    </p>
                                                    <p style="margin: 0 0 4px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                                        Organization
                                                    </p>
                                                    <p style="margin: 0; font-size: 15px; font-weight: 600; color: #111827; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                                        {{ $tenantName }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 0 0 28px; font-size: 15px; color: #374151; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6;">
                                            Click the button below to set up your password and activate your account. Once set up, you'll have access to your employee portal.
                                        </p>

                                        <!-- CTA Button -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 28px;">
                                            <tr>
                                                <td align="center">
                                                    <!--[if mso]>
                                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ $acceptUrl }}" style="height: 48px; width: 240px; v-text-anchor: middle;" arcsize="17%" fillcolor="{{ $primaryColor }}">
                                                        <w:anchorlock/>
                                                        <center style="color: #ffffff; font-family: 'Segoe UI', sans-serif; font-size: 15px; font-weight: 600;">Accept Invitation</center>
                                                    </v:roundrect>
                                                    <![endif]-->
                                                    <!--[if !mso]><!-->
                                                    <a href="{{ $acceptUrl }}" target="_blank" style="display: inline-block; padding: 14px 36px; background-color: {{ $primaryColor }}; color: #ffffff; font-size: 15px; font-weight: 600; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 8px; line-height: 1; text-align: center;">
                                                        Accept Invitation
                                                    </a>
                                                    <!--<![endif]-->
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Expiry notice -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="border-top: 1px solid #f0f0f3; padding-top: 20px;">
                                                    <p style="margin: 0; font-size: 13px; color: #9ca3af; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5;">
                                                        This invitation expires in <strong style="color: #6b7280;">7 days</strong>. If you didn't expect this email, you can safely ignore it.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Fallback URL -->
                    <tr>
                        <td align="center" style="padding: 24px 0 0;">
                            <p style="margin: 0 0 6px; font-size: 12px; color: #9ca3af; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                Button not working? Copy and paste this link into your browser:
                            </p>
                            <p style="margin: 0; font-size: 12px; color: {{ $primaryColor }}; word-break: break-all; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                {{ $acceptUrl }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 32px 0 0;">
                            <p style="margin: 0; font-size: 12px; color: #b0b5be; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5;">
                                Sent by {{ config('app.name') }} on behalf of {{ $tenantName }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
