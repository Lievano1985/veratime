<!DOCTYPE html>
<html lang="es">
<body>
    <p>Hola {{ $administrator->name }},</p>

    @if ($isNewAdministrator)
        <p>Se creó tu acceso como administrador de <strong>{{ $company->name }}</strong> en {{ config('app.name') }}.</p>

        <p><a href="{{ $setupUrl }}">Definir mi contraseña y acceder</a></p>

        <p>Este enlace vence en {{ $expiresInMinutes }} minutos.</p>

        <p>Por seguridad, nunca enviamos contraseñas por correo. Si no reconoces esta alta, puedes ignorar este mensaje.</p>
    @else
        <p>Tu cuenta existente ahora tiene acceso como administrador de <strong>{{ $company->name }}</strong> en {{ config('app.name') }}.</p>

        <p>Tu contraseña no fue modificada. <a href="{{ route('login') }}">Iniciar sesión</a></p>
    @endif
</body>
</html>
