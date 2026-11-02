# Datos históricos exclusivos de pruebas

Estos siete fixtures se retiraron de database/seeders. Se conservan porque verifican perfiles, programación, publicaciones, correcciones, importaciones y cálculo de jornadas en las pruebas automatizadas.

Su namespace es Tests\Fixtures\Seeders, disponible mediante autoload-dev. Cada fixture rechaza ejecutarse fuera del entorno de pruebas. No deben utilizarse para preparar instalaciones ni empresas. Los datos de demostración de la aplicación se generan desde Empresas, usando los dos escenarios actuales.
