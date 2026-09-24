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
