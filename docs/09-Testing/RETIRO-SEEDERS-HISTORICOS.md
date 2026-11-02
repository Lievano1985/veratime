# Retiro de seeders históricos y aislamiento de productos

Se retiraron los siete VeraTime*Seeder de database/seeders. Ninguno forma parte del flujo de ejecución de la aplicación, pero los siete se utilizan en pruebas automatizadas. Sus datos se conservaron como fixtures en tests/Fixtures/Seeders, con namespace Tests\Fixtures\Seeders y bloqueo cuando app()->runningUnitTests() es falso. Las doce clases de pruebas que los referencian utilizan el nuevo namespace. No se suprimió cobertura para retirar los seeders.

Las instrucciones antiguas de documentos históricos que invocan db:seed --class=VeraTime... ya no son procedimientos de instalación vigentes. Para datos demo se utiliza Empresas → Generar demo. Los dos escenarios actuales no dependen de los fixtures retirados.

ProductSeeder ahora solo registra los tres productos existentes. Se eliminó el recorrido por customer_accounts que activaba Vera Time y borraba fechas y metadatos de customer_account_products. Ejecutarlo de nuevo no concede accesos ni altera contratos suspendidos, vencidos o vigentes. No cambia los datos ya guardados retroactivamente. Las altas y las modificaciones de productos se controlan mediante sus Actions e interfaz específica.

EnsureCustomerAccountHasProductAction también se cambió a firstOrCreate: puede establecer el acceso inicial al crear una cuenta, pero no reactiva ni restablece un acceso ya existente al editar datos de empresa. Los cambios explícitos de contratación siguen en UpdateCustomerAccountProductAction.

Las pruebas ProductAccessTest comprueban registro sin contratación automática y preservación exacta de estado, fechas y metadatos de un acceso suspendido, también al editar la empresa. La instalación limpia y los escenarios históricos se validan en una base aislada.
