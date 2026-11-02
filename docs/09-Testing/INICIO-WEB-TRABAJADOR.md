# Inicio web del rol Trabajador

WebHomeRoute resuelve personal.dashboard para el rol trabajador de la empresa activa y dashboard para los demás perfiles. El login de trabajadores descarta destinos antiguos guardados en url.intended y abre /time/my-dashboard. El logotipo de las variantes sidebar y header utiliza la misma resolución. El acceso directo a /time/dashboard redirige al dashboard personal cuando corresponde a un trabajador, sin conceder acceso al tablero operativo.

La ruta es /time/my-day, con una sola diagonal y en minúsculas. No existen /time, /TIME/DAYLYS ni /TIME/SETTINGS como pantallas de acceso personal. El portal conserva la validación de cuenta, empresa, producto y trabajador vinculado activos; los módulos administrativos siguen protegidos por sus políticas.

Para el rol trabajador el menú muestra Mi resumen (dashboard personal) y Mi jornada (calendario y detalle), y oculta el enlace administrativo Inicio y el grupo Organización. Las variantes de navegación de escritorio y móvil conservan esta presentación personal. Los demás perfiles mantienen sus enlaces administrativos según sus permisos.

WorkerHomeTest valida login con destino previo administrativo, redirección del dashboard, carga del portal personal y rechazo de la administración de trabajadores. AuthenticationTest y MyDayScheduleTest verifican que se conserve el login general y el aislamiento de horarios personales.

BuildPersonalDashboardAction obtiene exclusivamente datos de la empresa y trabajador vinculados: turno publicado de hoy, último marcaje válido local, minutos publicados de la semana completa, minutos de cálculos activos hasta hoy, jornadas pendientes de esa semana, seis marcajes recientes y cinco avisos abiertos. Los marcajes anulados se excluyen; los pendientes de revisión se identifican. Los resultados se presentan como provisionales, no como pago confirmado. No realiza escrituras, cálculos nuevos ni cambios de horarios. Las publicaciones correctivas coexistentes se agrupan por fecha utilizando la asignación más reciente.

WorkerDashboardTest verifica estados vacíos y vínculo revocado, exclusión de borradores y cálculos de otros días/semanas, y aislamiento de marcajes y avisos de otro trabajador. El panel no permite modificar registros ni concede acceso a nómina o administración.
