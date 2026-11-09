<!DOCTYPE html>
<html lang="es"><body style="margin:0;background:#f4f7fc;font-family:Arial,sans-serif;color:#092348;padding:24px">
    <div style="max-width:580px;margin:auto;background:white;border:1px solid #dce6f4;border-radius:16px;padding:28px">
        <p style="color:#0067e4;font-weight:bold">VERA TIME · PERIODO DE PRUEBA</p>
        <h1 style="font-size:23px">Tu prueba está por vencer</h1>
        <p>Hola, {{ $administrator->name }}.</p>
        <p>A tu empresa <strong>{{ $company->name }}</strong> le quedan <strong>{{ $trial['days'] }} {{ $trial['days'] === 1 ? 'día' : 'días' }}</strong> de prueba.</p>
        <p style="background:#fff8e6;border-radius:10px;padding:16px">Vencimiento: <strong>{{ $expiry }}</strong></p>
        <p>Contacta a Vera Time para activar tu empresa y continuar usando el servicio. Al vencer la prueba se suspende el acceso; tus datos se conservan.</p>
        <p><a href="{{ route('login') }}" style="display:inline-block;border-radius:10px;background:#0067e4;color:white;text-decoration:none;padding:13px 20px">Entrar a Vera Time</a></p>
    </div>
</body></html>
