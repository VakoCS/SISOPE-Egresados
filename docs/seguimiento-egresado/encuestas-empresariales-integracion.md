# Empleadores y encuestas empresariales

## Compatibilidad e instalación

Base revisada: `origin/main` en `76c5dac`, PR #1 y #2 fusionados. No se modifican autenticación,
layout común, campañas, reportes, consultas de indicadores ni dashboards.

Ejecutar con la conexión PostgreSQL y la base de destino configuradas:

```console
php artisan module:migrate seguimiento-egresado --force
php artisan db:seed --class="App\Modules\SeguimientoEgresado\Database\Seeders\SectoresEconomicosSeeder" --force
```

La migración `2026_11_22_000400_complete_encuestas_empresariales` es aditiva. No usar `migrate:fresh`,
`refresh` ni volver a ejecutar las migraciones anteriores manualmente en una base con datos.
La semilla solo agrega sectores compartidos faltantes, no reemplaza datos ni carga demostraciones.

## Flujo de prueba manual

1. Secretaría: `/seguimiento-egresado/empresas/gestion`, registrar/editar una empresa y vincular un egresado.
   La baja es lógica (`Inactivo`); desvincular conserva el contacto histórico.
2. En la ficha, seleccionar una campaña abierta y el tipo A o B. B exige vínculo activo y que la última
   respuesta del egresado autorice contactar a su empresa; vincular por sí solo no da consentimiento.
3. Se abre la pantalla existente `/seguimiento-egresado/empresas` con el enlace recién generado.
   Copiarlo y entregarlo manualmente. **No hay envío de correo automático.**
4. Abrirlo en ventana privada: `/seguimiento-egresado/encuesta-empresa/{token}`. No requiere login.
5. A: sectores de contratación, perfiles, habilidades valoradas, brechas, demanda a 12 meses y satisfacción general.
   B: identificación del egresado, cinco dimensiones de desempeño (1–5), habilidades/brechas y satisfacción.
6. Tras responder, el enlace devuelve 409 ante otro envío. Token alterado: 404. Vencido/campaña no disponible: 410.
7. La ficha de empresa muestra invitaciones, estados, vencimiento, fecha de respuesta y datos nuevos.
   Dirección consulta sin poder generar enlaces, editar ni modificar vínculos.

La pantalla general mantiene la emisión individual/en lote anterior; no obliga a tener una empresa en el catálogo.
Los enlaces previos mantienen `version_formulario=1` y su cuestionario original. Los nuevos usan versión 2;
la versión y la identidad se leen de PostgreSQL y no se aceptan desde el formulario público.

## Contrato de datos para Jim (I5)

- `seguimiento_egresado.invitacion_empresa`: `contexto` (`MERCADO`/`DESEMPENO`),
  `id_egresado`, `id_empresa` opcional (FK a `comun.empresa`), `id_campania`, `version_formulario`,
  `estado`, `enviado_en`, `expira_en`, `respondido_en` y `token_hash` SHA-256. El token plano no se persiste.
- `respuesta_empresa`: una fila por invitación (`id_invitacion_empresa` único), `fecha_respuesta` y
  `id_nivel_satisfaccion` conservan el contrato de I5. Solo DESEMPENO con egresado admite este último campo.
- Las cinco columnas `desempeno_laboral`, `dominio_conocimientos`, `resolucion_problemas`,
  `trabajo_equipo`, `comunicacion` usan enteros 1–5 (muy bajo a muy alto).
- MERCADO: `demanda_profesionales` (`NINGUNA`, `UNO_CINCO`, `SEIS_DIEZ`, `MAS_DIEZ`) y
  `id_satisfaccion_formacion` (FK al catálogo de satisfacción), **separado de `id_nivel_satisfaccion`**.
  Por tanto la satisfacción general nunca entra en I5.
- `respuesta_empresa_sector`: sectores de contratación (FK al catálogo del módulo).
- `respuesta_empresa_perfil`: perfiles requeridos; códigos documentados en `CuestionarioEmpresa::PERFILES`.
- `respuesta_empresa_habilidad` conserva habilidades demandadas y brechas.
- Los sectores de `comun.sector_economico` y `cat_sector_empresa` comparten códigos, **no IDs**.
- Las columnas nuevas son nulas para respuestas anteriores; no interpretar nulo como cero.

El guardado completo y el estado COMPLETADA se realizan en una transacción con bloqueo de la invitación.
Renovar también bloquea la fila: no puede reemplazar el token mientras se está registrando una respuesta.
La restricción única impide dos respuestas para la misma invitación incluso bajo concurrencia.

## Pruebas

En PowerShell, únicamente sobre una base de pruebas migrada:

```powershell
$env:DB_CONNECTION='pgsql'
$env:DB_DATABASE='portal_test_siso'
$env:DB_URL=''
php vendor/bin/phpunit tests/Feature/SeguimientoEgresado tests/Unit/SeguimientoEgresado --do-not-cache-result
```

La conexión SQLite predeterminada omite las pruebas PostgreSQL: no equivale a verificar este flujo.
