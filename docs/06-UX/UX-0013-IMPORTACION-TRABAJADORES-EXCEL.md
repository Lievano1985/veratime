# Importación de trabajadores desde Excel

En Trabajadores, **Importar Excel** abre el panel lateral. Permite descargar una plantilla XLSX y cargar archivos XLS o XLSX de hasta 5 MB y 500 trabajadores. La hoja Trabajadores contiene cinco columnas: Clave, Nombre completo, Código de centro, Fecha de ingreso y RFC. El RFC es opcional. Las hojas Centros e Instrucciones facilitan la captura; el código del centro se elige de una lista limitada a los centros activos donde el usuario tiene permiso de alta.

Las fechas aceptan una fecha real de Excel, AAAA-MM-DD o DD/MM/AAAA. Las claves se conservan como texto, incluidos sus ceros iniciales. Los encabezados deben conservarse. No se aceptan fórmulas ni errores de Excel en las filas de datos.

La vista previa presenta todas las filas y los errores encontrados. **Importar** se habilita cuando no hay errores. No se guardan filas parcialmente: la operación completa se ejecuta en una transacción y vuelve a comprobar permisos, empresa, centros, claves existentes y contenido del archivo. Las claves repetidas dentro del archivo o ya existentes en la empresa bloquean la carga; no se actualizan trabajadores existentes.

Se reutiliza `SaveWorkerWithEmploymentRelationshipAction` para crear cada trabajador activo y su relación laboral inicial, con origen `excel` y actor/fecha en los metadatos del trabajador. Las credenciales y asignaciones a áreas se configuran mediante los flujos existentes. No se requieren migraciones.

La vista previa se vincula a la empresa y al hash del archivo. Sus campos son propiedades Livewire bloqueadas. Un cambio de archivo, empresa, permisos o datos existentes exige volver a revisar. La lectura limita dimensiones y tamaño descomprimido antes de cargar XLSX; solo admite lectores XLS y XLSX.

Pruebas automatizadas: ambos formatos, ceros iniciales, fechas reales de Excel, descarga de plantilla, rechazo de fórmulas, duplicados, centros ajenos, vista previa obligatoria, cambio de empresa/archivo, formato incorrecto, límites y autorización del usuario.

Verificación manual: descargar plantilla, completar dos trabajadores en centros autorizados, cargar y revisar el panel, confirmar la importación y revisar sus relaciones laborales. Repetir la carga para comprobar que las claves existentes se señalan como errores. Revisar el panel en móvil y escritorio.
