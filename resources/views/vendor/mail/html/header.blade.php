@props(['url'])
<tr>
<td class="header" align="center" style="padding: 32px 0 24px; text-align: center;">
<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto; text-align: left;">
<tr>
<td style="vertical-align: middle; padding-right: 12px;">
<a href="{{ $url }}" style="display: block; text-decoration: none;">
<img src="{{ rtrim(config('app.url'), '/') }}/apple-touch-icon.png" class="logo" alt="SuaraNetijen Logo" width="44" height="44" style="width: 44px; height: 44px; display: block; border: 0; border-radius: 10px; box-shadow: 0 2px 8px rgba(8, 127, 91, 0.2);">
</a>
</td>
<td style="vertical-align: middle; text-align: left;">
<a href="{{ $url }}" style="display: block; text-decoration: none;">
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 800; letter-spacing: -0.04em; line-height: 1.1; color: #18392d;">
suara<span style="color: #087f5b;">netijen.</span>
</div>
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 11px; font-weight: 500; color: #68746b; margin-top: 3px; letter-spacing: -0.01em;">
Sebelum pilih, cek kata netizen.
</div>
</a>
</td>
</tr>
</table>
</td>
</tr>
