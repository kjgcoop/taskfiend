<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Tasks for {{ $date->format('l, F j, Y') }}</title>
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
                        <td style="padding:8px 24px 24px 24px;">
                            <p style="color:#374151; font-size:14px;">
                                Hi {{ $user->name }}, here's what's on your list today:
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                @foreach ($tasks as $task)
                                    <tr>
                                        <td style="padding:12px 0; border-top:1px solid #e5e7eb;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td width="90" valign="top" style="color:#6b7280; font-size:13px; padding-right:12px; white-space:nowrap;">
                                                        {{ $task->time ? \Carbon\Carbon::parse($task->time)->format('g:i A') : '' }}
                                                    </td>
                                                    <td valign="top">
                                                        <div style="color:#111827; font-size:15px; font-weight:600;">
                                                            {{ $task->name }}
                                                        </div>
                                                        @if ($task->project)
                                                            <div style="color:#6b7280; font-size:12px; margin-top:2px;">
                                                                {{ $task->project->name }}
                                                            </div>
                                                        @endif
                                                        @if ($task->tags->isNotEmpty())
                                                            <div style="margin-top:6px;">
                                                                @foreach ($task->tags as $tag)
                                                                    <span style="display:inline-block; background-color:{{ $tag->color }}22; color:#374151; font-size:11px; padding:2px 8px; border-radius:10px; margin-right:4px;">
                                                                        {{ $tag->tag_name }}
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9fafb; padding:16px 24px; text-align:center;">
                            <a href="{{ route('day', ['date' => $date->format('Y-m-d')]) }}" style="color:#2563eb; font-size:13px; text-decoration:none;">
                                Open Today in Task Fiend &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
