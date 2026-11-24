# Recorrido del egresado por enlace (sin cuenta)

## Base reutilizada

Los PR #1 y #2 ya están integrados en `origin/main` (`76c5dac`). Se reutilizan las rutas, políticas,
catálogos, emisión de invitaciones, contacto versionado y servicios `EncuestaService`/`HistorialService`.
El acceso institucional del portal no se cambia: el egresado sigue entrando sin cuenta mediante su enlace.
Empresas y sus encuestas A/B no se modifican en esta entrega.

## Acceso y seguridad

- `/seguimiento-egresado/acceso/{token}` busca el SHA-256 en `invitacion_egresado.token_hash`;
  el token plano no se guarda en la sesión ni en PostgreSQL.
- Se regenera la sesión. Una firma HMAC separada ata la identidad al egresado, invitación, campaña,
  propósito, hash vigente y vencimiento original. Se conserva el contrato de cuatro campos de identidad.
- `ProtegeEnlaceEgresado` revalida en PostgreSQL cada petición al área privada: titular, estado,
  vencimiento y firma. Anular/expirar una invitación, acortar su plazo o rotar su token revoca la sesión.
- Un dato de sesión inválido no produce error 500 ni permite cambiar de expediente. Los parámetros de
  identidad enviados en los formularios no se aceptan; la identidad proviene del enlace.
- Los enlaces completados permiten consultar hasta su vencimiento, pero no volver a responder.
- Es posible elegir otra invitación del **mismo egresado** desde su expediente/historial, como en el flujo
  original. El servidor valida titular, propósito, estado, fechas de campaña y vencimiento de esa invitación.
- Contacto e historial siguen disponibles con un enlace válido aunque haya cerrado la campaña; responder no.
- Las páginas y redirecciones del flujo llevan `no-store, private`, `no-referrer` y `noindex, nofollow`.
  Las escrituras privadas conservan CSRF. La ruta pública de acceso mantiene su limitación de peticiones.
- Las sesiones abiertas antes de este refuerzo deben volver a abrir su enlace para obtener la firma.

## Datos y compatibilidad

La migración aditiva `2026_11_22_000500_complete_recorrido_egresado` agrega:

- `medicion_laboral.cargo_actual` (texto), `rango_ingreso` (rango mensual en soles),
  `tiempo_insercion` (intervalo desde el egreso hasta el primer empleo).
- `satisfaccion_egresado.pertinencia_curricular`: escala de 1 a 5.

Son campos **opcionales**: no invalida cuestionarios/clientes anteriores y no convierte respuestas
históricas nulas en cero. Los rangos y escalas se definen en `EncuestaSeguimientoRequest` y se validan
tanto en servidor como mediante restricciones de PostgreSQL. Quien no trabaja no almacena los datos de empleo.

Las preferencias usan las columnas del PR #2, sin duplicarlas: `canal_preferido`,
`horario_contacto_preferido`, `autoriza_mensajes`. Se muestran en contacto y encuesta; se versionan
junto con el resto del contacto. Si un cliente anterior las omite, se conservan las preferencias vigentes,
especialmente una negativa de autorización. El bloqueo del egresado serializa incluso el primer contacto.

La formación posterior reutiliza `formacion_posterior` y sus catálogos de maestría, especialización,
certificación, diplomado, etc. Cada fila conserva `id_medicion_origen` para distinguir lo declarado en cada campaña.
La medición, satisfacción, habilidades, formación, contacto y cierre de invitación se guardan en una transacción.
El bloqueo y la restricción única sobre `medicion_laboral.id_invitacion` impiden duplicar respuestas.

El historial sigue filtrándose por el egresado de la sesión. Los enlaces «Responder» incluyen ahora la
invitación de su campaña y las invitaciones vencidas individualmente ya no se muestran como respondibles.
No se modifican las vistas SQL de indicadores ni los dashboards del equipo.

## Instalación y prueba

Con PostgreSQL y la base de destino configurados:

```console
php artisan module:migrate seguimiento-egresado --force
```

No se requiere cargar demos ni volver a ejecutar seeders. No usar `migrate:fresh` o `refresh` sobre datos existentes.

Pruebas del módulo en PowerShell:

```powershell
$env:DB_CONNECTION='pgsql'
$env:DB_DATABASE='portal_test_siso'
$env:DB_URL=''
php vendor/bin/phpunit tests/Feature/SeguimientoEgresado tests/Unit/SeguimientoEgresado --do-not-cache-result
```

Las pruebas HTTP antiguas que inventaban una identidad usan ahora una invitación real mediante
`ConEnlaceEgresado`; no se desactiva el middleware ni la firma para pasar las pruebas.
La prueba histórica de 2024 responde con el reloj situado dentro de la campaña de 2024.

## Navegador

Servidor local habitual: `http://127.0.0.1:8000`.

1. Secretaría entra por `/login` y emite el enlace desde Padrón (contacto inicial) o Campañas (seguimiento).
2. Abrir el enlace `/seguimiento-egresado/acceso/{token}` en incógnito. No iniciar sesión institucional.
3. Visitar `/seguimiento-egresado/egresado/`, `/egresado/contacto`, `/egresado/encuesta`,
   `/egresado/historial` (todos bajo `/seguimiento-egresado`).
4. Actualizar contacto/preferencias, responder con/sin empleo y agregar una formación posterior.
5. Consultar el detalle del historial y comprobar que repetir el envío no crea otra medición.
6. Empresas mantiene `/seguimiento-egresado/empresas/gestion`, `/seguimiento-egresado/empresas` y
   `/seguimiento-egresado/encuesta-empresa/{token}` para los enlaces A/B públicos.

Los tokens de prueba automatizada se revierten con las transacciones: para probar manualmente hay que
generar un enlace nuevo desde la aplicación; no reutilizar un token ficticio ni publicar enlaces de personas reales.
