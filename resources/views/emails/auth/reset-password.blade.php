<!DOCTYPE html>
<html lang="es">
<body>
    <p>Hola {{ $user->name }},</p>

    <p>Recibimos una solicitud para restablecer la contrase&#241;a de tu cuenta en {{ config('app.name') }}.</p>

    <p><a href="{{ $resetUrl }}">Restablecer contrase&#241;a</a></p>

    <p>Este enlace vence en {{ $expiresInMinutes }} minutos.</p>

    <p>Si no solicitaste este cambio, puedes ignorar este correo.</p>
</body>
</html>
