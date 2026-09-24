# Seguridad de produccion

## Preflight obligatorio

Ejecutar despues de inyectar secretos y cachear configuracion, antes de dirigir
trafico al release:

```bash
php artisan config:cache
php artisan security:check-production --database
```

El despliegue debe detenerse si el comando falla. Comprueba entorno, debug,
clave de aplicacion, HTTPS, CORS, expiracion Sanctum, nivel de logs, credenciales
de base de datos y ausencia de privilegios administrativos.

## Secretos

- Produccion debe recibir secretos desde el gestor del proveedor, nunca desde
  archivos versionados ni incluidos en la imagen de despliegue.
- Crear una `APP_KEY` exclusiva para produccion. Conservarla de forma estable;
  cambiarla invalida datos cifrados y sesiones.
- Usar credenciales distintas para local, staging y produccion.
- Rotar inmediatamente cualquier valor enviado por chat, incluido en logs,
  copiado a tickets o detectado en Git. Tras rotar, revocar el valor anterior.
- No copiar bases de datos, dumps o `.env` de produccion a equipos locales.

La revision actual no encontro secretos ni archivos sensibles versionados en el
arbol o historial disponible. Las credenciales locales no se consideran
credenciales de produccion y deben reemplazarse al desplegar.

## Base de datos

Usar dos identidades separadas:

- `momentum_migrator`: disponible solo durante despliegues autorizados, con los
  permisos necesarios para migraciones.
- `momentum_runtime`: utilizada por la aplicacion, limitada a
  `SELECT`, `INSERT`, `UPDATE` y `DELETE` sobre la base de Momentum.

Ejemplo conceptual para MySQL; reemplazar base, host y contrasena mediante el
gestor de secretos, sin registrar el valor en el historial del shell:

```sql
CREATE USER 'momentum_runtime'@'<private-host>' IDENTIFIED BY '<generated-secret>';
GRANT SELECT, INSERT, UPDATE, DELETE ON momentum.* TO 'momentum_runtime'@'<private-host>';
```

La cuenta runtime no debe tener permisos globales, `GRANT OPTION`, `SUPER`,
`FILE`, `SHUTDOWN`, `CREATE USER`, `SYSTEM_USER` ni `ROLE_ADMIN`. La cuenta local
auditada actualmente es administrativa y no debe reutilizarse en staging o
produccion.

## Logs y errores

- Usar como minimo `LOG_LEVEL=warning` en produccion.
- `RedactSensitiveData` elimina Authorization, cookies, contrasenas, tokens,
  API keys y secretos del mensaje y contexto antes de escribirlos.
- Mantener `APP_DEBUG=false`; las respuestas 500 de la API deben conservar el
  mensaje generico `Server Error`.
- Configurar retencion y acceso a logs en el proveedor. La redaccion es una
  defensa adicional, no una autorizacion para registrar payloads completos.

## Verificacion posterior al despliegue

- Confirmar que `APP_ENV=production` y `APP_DEBUG=false` mediante el preflight.
- Ejecutar una solicitud controlada que produzca 500 y comprobar respuesta y
  logs sin SQL, rutas, stack trace, headers ni secretos.
- Confirmar que el frontend real es el unico origen CORS permitido.
- Revisar los grants efectivos de la conexion runtime con `SHOW GRANTS FOR CURRENT_USER`.
- Rotar cualquier secreto temporal utilizado durante la preparacion inicial.

## HTTPS, proxies y servidor web

- El balanceador o servidor web debe redirigir HTTP a HTTPS antes de PHP. El
  middleware `ForceHttps` actua como segunda capa: redirige `GET/HEAD` sin
  credenciales y rechaza solicitudes HTTP con credenciales o escrituras.
- Definir `TRUSTED_PROXIES` con IPs o rangos CIDR separados por comas. No usar
  `*`; si el proveedor no publica rangos estables, documentar y proteger el
  acceso directo al origen antes de considerar una excepcion.
- El document root debe apuntar exactamente a `<release>/public`, nunca a la
  raiz del repositorio. Bloquear dotfiles y deshabilitar directory listing.
- Solo `public/build`, assets publicos e `index.php` deben ser servibles. `.env`,
  `.git`, `storage/logs`, dumps, backups, Composer y codigo PHP deben devolver
  `404` desde el servidor, incluso si Laravel no esta disponible.
- Ejecutar desde una maquina externa despues del despliegue:

```bash
curl -I http://api.example.com/api/user
curl -I https://api.example.com/.env
curl -I https://api.example.com/.git/config
curl -I https://api.example.com/storage/logs/laravel.log
```

La primera solicitud debe redirigir a HTTPS sin aceptar credenciales. Las otras
deben responder `404`. Repetir las pruebas de CORS con el dominio frontend real.

## Limites de solicitudes y tiempos

- Mantener `MAX_REQUEST_BODY_KB=1024` o un valor menor compatible con los
  payloads reales. Laravel lo aplica como segunda capa y responde `413`.
- Aplicar el mismo limite en el proxy para rechazar el cuerpo antes de enviarlo
  a PHP. En Nginx: `client_max_body_size 1m`.
- Limitar el tiempo de recepcion del cuerpo y de respuesta del upstream. Un
  punto de partida para Nginx es `client_body_timeout 10s`,
  `fastcgi_read_timeout 30s` o `proxy_read_timeout 30s`, segun la topologia.
- Mantener `max_execution_time=30` en PHP-FPM y alinear los timeouts del
  balanceador para que ninguna capa espere indefinidamente.
- Ajustar estos valores con mediciones de staging; no elevarlos para ocultar
  consultas lentas. Dashboard, board, stats y activity tienen una cuota menor,
  y los reordenamientos y sincronizacion de tags usan una cuota de escritura
  masiva independiente.

## Markdown, XSS y CSP

- La API almacena Markdown crudo como texto. Nunca debe considerarse HTML
  confiable ni insertarse directamente mediante `innerHTML` o equivalentes.
- El frontend debe renderizar notas y descripciones exclusivamente mediante
  `resources/js/security/renderMarkdown.js`, que deshabilita HTML crudo y
  sanitiza el resultado con DOMPurify antes de entregarlo a React.
- La aplicacion Laravel emite una CSP restrictiva. Si React se despliega en un
  host separado, ese host debe emitir una politica equivalente; una cabecera de
  la API no protege documentos cargados desde otro dominio.
- Produccion no habilita `unsafe-inline` ni `unsafe-eval`. Mantener scripts y
  estilos en el bundle de Vite y ajustar dominios de `connect-src` de forma
  explicita si el frontend y la API usan origenes distintos.

## Concurrencia y reintentos

- Iniciar o activar un sprint serializa por proyecto. La base de datos tambien
  impide que dos sprints del mismo proyecto queden activos.
- Completar un sprint bloquea el sprint y sus tareas. Un segundo intento recibe
  `422` porque el sprint ya no esta activo; no crea otra actividad ni vuelve a
  mover tareas.
- Reorder de tareas y checklist es idempotente para el mismo payload. Las filas
  se bloquean en orden estable y los deadlocks se reintentan hasta tres veces.
  Si dos ordenes validas compiten, gana la ultima transaccion confirmada.
- Sincronizar las mismas etiquetas repetidamente es idempotente.
- Repetir una eliminacion devuelve `404` despues de que la primera solicitud
  elimina el recurso. El cliente debe tratar ese resultado como estado final
  cuando este reconciliando una operacion previamente enviada.

## Retencion y eliminacion de datos

- Los tokens revocados o expirados se purgan diariamente; la expiracion
  configurada sigue siendo de siete dias y la poda conserva 24 horas de margen.
- La actividad se conserva `ACTIVITY_RETENTION_DAYS` dias (365 por defecto) y
  `model:prune` la elimina diariamente. No debe usarse como auditoria permanente.
- Proyectos, tareas, sprints, notas y checklist se conservan mientras exista su
  recurso padre. Una eliminacion solicitada por el usuario es permanente y las
  relaciones dependientes se eliminan por constraints de base de datos.
- Las notas se eliminan al borrar la nota, el proyecto o la cuenta. No existe
  papelera ni recuperacion en el MVP; respaldos siguen su propia retencion y no
  deben utilizarse como historial consultable.
- Al eliminar una cuenta deben borrarse tokens, proyectos, tareas, sprints,
  notas, etiquetas, actividad y pivotes asociados. Hasta exponer esa accion en
  la API, la solicitud se ejecuta como operacion administrativa verificada.
- Los logs no deben recibir payloads completos. El procesador de redaccion
  oculta secretos, correo y campos de contenido si llegan al contexto; IP y
  usuario solo se registran para eventos de seguridad justificados.
