<!DOCTYPE html>
<html lang="es">
<body>
    <p>Hola {{ $administrator->name }},</p>

    <p>Se creó tu acceso como administrador de <strong>{{ $company->name }}</strong> en {{ config('app.name') }}.</p>

    <p><a href="{{ $setupUrl }}">Definir mi contraseña y acceder</a></p>

    <p>Este enlace vence en {{ $expiresInMinutes }} minutos.</p>

    <p>Por seguridad, nunca enviamos contraseñas por correo. Si no reconoces esta alta, puedes ignorar este mensaje.</p>
</body>
</html>
