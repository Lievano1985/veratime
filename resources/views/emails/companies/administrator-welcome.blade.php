<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:0; background:#f3f7fc; color:#102a43; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background:#f3f7fc; padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 8px 24px rgba(16,42,67,.08);">
                    <tr>
                        <td style="padding:28px 36px 22px; background:linear-gradient(135deg,#063c78,#0874dc); text-align:center;">
                            <img src="{{ $brandImageUrl }}" alt="{{ $company->name }}" width="112" style="display:inline-block; max-width:112px; max-height:52px; width:auto; height:auto; object-fit:contain; background:#ffffff; border-radius:8px; padding:7px;" />
                            <p style="margin:16px 0 0; color:#ffffff; font-size:13px; font-weight:600; letter-spacing:.08em; text-transform:uppercase;">Vera Time</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 36px 12px;">
                            <h1 style="margin:0 0 14px; color:#0b2440; font-size:25px; line-height:1.25;">{{ $isNewAdministrator ? '¡Bienvenido a Vera Time!' : 'Tienes una nueva empresa disponible' }}</h1>
                            <p style="margin:0; color:#4e647b; font-size:16px; line-height:1.6;">Hola {{ $administrator->name }},</p>

                            @if ($isNewAdministrator)
                                <p style="margin:16px 0 0; color:#4e647b; font-size:16px; line-height:1.6;">Se creó tu acceso como administrador de <strong style="color:#102a43;">{{ $company->name }}</strong>. Completa tu activación para comenzar a configurar y administrar la empresa.</p>
                            @else
                                <p style="margin:16px 0 0; color:#4e647b; font-size:16px; line-height:1.6;">Tu cuenta existente ahora tiene acceso como administrador de <strong style="color:#102a43;">{{ $company->name }}</strong>. Tu contraseña no fue modificada.</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 36px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; border:1px solid #dbe7f4; border-radius:12px; background:#f8fbff;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 12px; color:#0b2440; font-size:14px; font-weight:700;">Datos de acceso</p>
                                        <p style="margin:0 0 8px; color:#4e647b; font-size:14px;"><strong style="color:#102a43;">Empresa:</strong> {{ $company->name }}</p>
                                        <p style="margin:0 0 8px; color:#4e647b; font-size:14px;"><strong style="color:#102a43;">Usuario:</strong> {{ $administrator->email }}</p>
                                        <p style="margin:0; color:#4e647b; font-size:14px;"><strong style="color:#102a43;">Perfil:</strong> Administrador de empresa</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:10px 36px 28px;">
                            @if ($isNewAdministrator)
                                <a href="{{ $setupUrl }}" style="display:inline-block; padding:14px 24px; border-radius:8px; background:#0874dc; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none;">Definir contraseña y acceder</a>
                                <p style="margin:16px 0 0; color:#6f8297; font-size:13px; line-height:1.5;">Este enlace vence en {{ $expiresInMinutes }} minutos.</p>
                            @else
                                <a href="{{ $loginUrl }}" style="display:inline-block; padding:14px 24px; border-radius:8px; background:#0874dc; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none;">Ingresar a Vera Time</a>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 36px 30px;">
                            <p style="margin:0; padding:16px; border-radius:10px; background:#fff8e8; color:#7b5912; font-size:13px; line-height:1.55;">Por seguridad, Vera Time nunca envía contraseñas por correo. Si no reconoces este acceso, puedes ignorar el mensaje o contactar a soporte.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 36px 28px; border-top:1px solid #e6edf5; text-align:center;">
                            <p style="margin:0 0 8px; color:#6f8297; font-size:12px; line-height:1.5;">Necesitas ayuda? Escríbenos a <a href="mailto:{{ $supportEmail }}" style="color:#0874dc; text-decoration:none;">{{ $supportEmail }}</a></p>
                            <p style="margin:0; color:#98a8b8; font-size:12px; line-height:1.5;">Vera Time · Medición y administración de jornada laboral</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
