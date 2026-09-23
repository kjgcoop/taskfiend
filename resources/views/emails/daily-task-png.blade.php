<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Task List for {{ $date->format('l, F j, Y') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#1f2937; padding:20px 24px;">
                            <div style="color:#9ca3af; font-size:12px; letter-spacing:1px; text-transform:uppercase;">Task Fiend</div>
                            <div style="color:#ffffff; font-size:20px; font-weight:bold; margin-top:4px;">
                                {{ $date->format('l, F j, Y') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px 24px 24px;" align="center">
                            <img src="cid:{{ $imageCid }}" alt="Task list for {{ $date->format('l, F j, Y') }}" style="display:block; max-width:100%; height:auto; border:1px solid #e5e7eb;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9fafb; padding:16px 24px; text-align:center;">
                            <a href="{{ route('day', ['date' => $date->format('Y-m-d')]) }}" style="color:#2563eb; font-size:13px; text-decoration:none;">
                                Open This Day in Task Fiend &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
