{{--
    Samotný podpis. Tohle se kopíruje do poštovního klienta, proto platí
    pravidla e-mailového HTML, ne webu:

    - jen tabulky a inline styly, žádné třídy (Gmail i Outlook <style> v podpisu zahodí),
    - obrázky jako PNG s pevným width/height (SVG Gmail ani Outlook nezobrazí,
      Outlook pro Windows ignoruje CSS rozměry), zaoblení rohů je zapečené v PNG,
    - žádná barva pozadí a jen dva odstíny textu, které fungují na bílém
      i tmavém podkladu: cihlová #db4b24 a teplá šedá #857f75 (kontrast ~4:1
      v obou režimech). Klient tak nemusí nic invertovat a když to udělá,
      nic se nerozbije.
--}}
<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;font-family:Montserrat,Arial,Helvetica,sans-serif;">
    <tr>
        <td valign="middle" style="padding:0 16px 0 0;">
            <a href="{{ $websiteUrl }}" style="text-decoration:none;"><img src="{{ $photoUrl }}" width="100" height="122" alt="Pavel a Tom" style="display:block;width:100px;height:122px;border:0;outline:none;"></a>
        </td>
        <td valign="middle" style="padding:0 0 0 16px;border-left:2px solid #db4b24;">
            <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;">
                <tr>
                    <td style="padding:2px 0 0 0;">
                        <a href="{{ $websiteUrl }}" style="text-decoration:none;"><img src="{{ $logoUrl }}" width="110" height="26" alt="Taveo" style="display:block;width:110px;height:26px;border:0;outline:none;"></a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 0 8px 0;font-size:13px;line-height:18px;mso-line-height-rule:exactly;color:#857f75;">{{ text('email_signature.tagline', 'Weby, e-shopy a reklama z Hradce Králové', 'E-mailový podpis') }}</td>
                </tr>
                <tr>
                    <td style="padding:0;">
                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;">
                            @foreach ($founders as $founder)
                                <tr>
                                    <td style="padding:0 10px 0 0;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#857f75;">{{ $founder->name }}</td>
                                    <td style="padding:0;font-size:13px;line-height:20px;mso-line-height-rule:exactly;"><a href="{{ $founder->phoneHref() }}" style="color:#db4b24;text-decoration:none;font-weight:bold;">{{ $founder->phoneLabel() }}</a></td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:2px 0 0 0;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#857f75;"><a href="{{ $contact->emailHref() }}" style="color:#db4b24;text-decoration:none;font-weight:bold;">{{ $contact->email }}</a>&nbsp;&nbsp;·&nbsp;&nbsp;<a href="{{ $websiteUrl }}" style="color:#db4b24;text-decoration:none;font-weight:bold;">{{ $websiteLabel }}</a></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
