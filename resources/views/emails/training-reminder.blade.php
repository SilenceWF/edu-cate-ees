{{-- Training reminder. Table layout and inline styles only, so it renders the same in desktop Outlook, Outlook on the web and phones. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ __('admin.reminder.subject', ['company' => $companyName, 'campaign' => $campaign->name]) }}</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F4F0; font-family:'Segoe UI', Tahoma, Arial, sans-serif; color:#17171A;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#F5F4F0;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background-color:#FFFFFF; border:1px solid #E6E3DC; border-radius:14px;">
                {{-- Header --}}
                <tr>
                    <td style="background-color:{{ $colors['header'] }}; padding:24px 32px; border-bottom:4px solid {{ $colors['primary'] }}; border-radius:14px 14px 0 0;">
                        <div style="font-size:19px; font-weight:700; color:#FFFFFF; line-height:1.3;">{{ $legalName }}</div>
                        <div style="font-size:12.5px; color:#E2E8F0; margin-top:4px;">{{ __('admin.reminder.header_subtitle', ['campaign' => $campaign->name]) }}</div>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6; color:#17171A;">{{ __('admin.reminder.greeting', ['name' => $user->name]) }}</p>

                        <p style="margin:0 0 18px 0; font-size:14.5px; line-height:1.65; color:#374151;">
                            {!! __('admin.reminder.intro_html', ['company' => e($legalName), 'campaign' => e($campaign->name)]) !!}
                        </p>

                        @if ($campaign->ends_on)
                            <p style="margin:0 0 18px 0; padding:10px 14px; background-color:#FEF3C7; border-left:4px solid {{ $colors['primary'] }}; font-size:14px; color:#78350F;">
                                <strong>{{ __('admin.reminder.deadline_label') }}:</strong> {{ $campaign->ends_on->format('d/m/Y') }}
                            </p>
                        @endif

                        {{-- Steps --}}
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#FAF9F6; border:1px solid #E6E3DC; border-left:4px solid {{ $colors['primary'] }};">
                            <tr>
                                <td style="padding:18px 20px;">
                                    <div style="font-size:14px; font-weight:700; color:#17171A; margin-bottom:10px;">{{ __('admin.reminder.steps_heading', ['minutes' => $minutes]) }}</div>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-size:14px; line-height:1.55; color:#374151;">
                                        @foreach ($steps as $i => $step)
                                            <tr>
                                                <td width="26" valign="top" style="font-weight:700; color:#17171A; padding:0 0 8px 0;">{{ $i + 1 }}.</td>
                                                <td valign="top" style="padding:0 0 8px 0; {{ $step['done'] ? 'color:#6B7280;' : '' }}">
                                                    {{ $step['label'] }}
                                                    @if ($step['done'])
                                                        <span style="display:inline-block; margin-left:6px; padding:1px 8px; font-size:11px; font-weight:700; background-color:#DCFCE7; color:#166534; border-radius:999px;">&#10003; {{ __('admin.reminder.step_done') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- Call to action --}}
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:26px 0;">
                            <tr>
                                <td align="center" bgcolor="{{ $colors['primary'] }}" style="border-radius:10px; background-color:{{ $colors['primary'] }};">
                                    <a href="{{ $portalUrl }}" target="_blank" style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:700; color:{{ $colors['on_primary'] }}; text-decoration:none; border-radius:10px;">{{ __('admin.reminder.cta') }} &rarr;</a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 18px 0; font-size:13.5px; line-height:1.65; color:#4B5563;">
                            <strong style="color:#17171A;">{{ __('admin.reminder.access_heading') }}</strong><br>
                            @if ($sso)&bull; {{ __('admin.reminder.access_sso') }}<br>@endif
                            &bull; {{ __('admin.reminder.access_network') }}
                        </p>

                        <p style="margin:0 0 24px 0; font-size:14px; line-height:1.6; color:#374151;">
                            @if ($contactEmail)
                                {!! __('admin.reminder.closing_note', ['contact' => '<a href="mailto:'.e($contactEmail).'" style="color:#17171A;">'.e($contactEmail).'</a>']) !!}
                            @else
                                {{ __('admin.reminder.closing_note_plain') }}
                            @endif
                        </p>

                        {{-- Sign-off --}}
                        <div style="border-top:1px solid #E6E3DC; padding-top:18px; font-size:14px; line-height:1.6; color:#374151;">
                            {{ __('admin.reminder.signoff') }}<br>
                            @if ($signatoryName)<strong style="color:#17171A;">{{ $signatoryName }}</strong><br>@endif
                            @if ($signatoryTitle)<span style="color:#4B5563;">{{ $signatoryTitle }}</span><br>@endif
                            <span style="color:#6B7280;">{{ $legalName }}</span>
                        </div>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="background-color:#FAF9F6; padding:16px 32px; border-top:1px solid #E6E3DC; border-radius:0 0 14px 14px; font-size:11.5px; line-height:1.6; color:#6B7280;">
                        {{ __('admin.reminder.fallback_url') }}<br>
                        <a href="{{ $portalUrl }}" style="color:#17171A; word-break:break-all;">{{ $portalUrl }}</a>
                        @if ($frameworks)<br><br>{{ $frameworks }}@endif
                        <br>{{ __('admin.reminder.automated', ['company' => rtrim($legalName, '.')]) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
