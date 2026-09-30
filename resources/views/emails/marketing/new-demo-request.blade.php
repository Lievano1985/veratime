<!DOCTYPE html>
<html lang="es">
<body>
    <h1>Nueva solicitud de demostraci&#243;n</h1>

    <dl>
        <dt>Nombre</dt>
        <dd>{{ $demoRequest->contact_name }}</dd>

        <dt>Empresa</dt>
        <dd>{{ $demoRequest->company_name ?: 'No indicada' }}</dd>

        <dt>Correo</dt>
        <dd>{{ $demoRequest->email }}</dd>

        <dt>Tel&#233;fono</dt>
        <dd>{{ $demoRequest->phone }}</dd>

        <dt>Tama&#241;o de equipo</dt>
        <dd>{{ $demoRequest->team_size ?: 'No indicado' }}</dd>

        <dt>Mensaje</dt>
        <dd>{{ $demoRequest->message ?: 'Sin mensaje' }}</dd>
    </dl>
</body>
</html>
