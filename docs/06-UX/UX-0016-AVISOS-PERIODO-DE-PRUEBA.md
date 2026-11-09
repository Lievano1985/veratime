# Avisos del periodo de prueba

Las empresas con is_trial y trial_ends_at muestran a los usuarios autorizados para administrar su empresa un aviso en la parte superior de las pantallas autenticadas. Indica días restantes redondeados hacia arriba y vencimiento con hora y zona horaria de la empresa. Durante las últimas 72 horas se destaca en ámbar; si la fecha ya pasó y aún se está procesando la suspensión, muestra que el periodo venció. Los trabajadores no ven este aviso administrativo.

El comando companies:send-trial-reminders revisa las empresas activas de prueba cuyo vencimiento esté dentro de las próximas 72 horas. Envía un recordatorio a cada administrador de empresa con cuenta y pertenencia activas utilizando BrevoTransactionalEmailService. No envía a trabajadores, a empresas no marcadas como prueba ni a pruebas vencidas. Se programa cada hora y usa withoutOverlapping.

Los envíos exitosos se registran en companies.settings.trial_reminders con una clave derivada de administrador, correo y vencimiento. Se conserva el resto de settings. Un mismo destinatario y vencimiento no se repite en las siguientes ejecuciones. Los fallos del proveedor no se marcan enviados y se reintentan en otra ejecución. Si cambia el vencimiento o el correo, el nuevo recordatorio tiene una clave diferente. La aplicación no cambia fechas, estados ni condiciones comerciales al enviar avisos.

La suspensión continúa usando companies:suspend-expired-trials cada minuto. Ambos comandos requieren que el cron del hosting ejecute php artisan schedule:run cada minuto. El aviso visual no depende del cron. Los correos requieren services.brevo.enabled, clave y remitente configurados; si está deshabilitado, se informa en la consola y no se marca ningún envío. No se enviaron correos reales durante la implementación.

No hay migraciones nuevas. CompanyTrialNoticesTest valida conteo y zona horaria, visibilidad por rol, envío único, reintento ante error y exclusión de empresas no elegibles/correo deshabilitado mediante HTTP simulado. La compilación de assets fue comprobada.
