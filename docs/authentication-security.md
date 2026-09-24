# Seguridad de autenticacion

## Decision del MVP

Momentum usa tokens Bearer de Laravel Sanctum para sus clientes API. En esta
etapa no se habilita simultaneamente el modo SPA basado en cookies. CORS tiene
`supports_credentials=false`, por lo que no se aceptan credenciales de sesion
cross-origin de forma accidental.

Antes de cambiar a autenticacion SPA con cookies se deberan implementar y
probar en conjunto `statefulApi`, CSRF, cookies seguras y CORS con credenciales.

## Ciclo de vida

- Los tokens expiran despues de 10,080 minutos (7 dias) por defecto.
- `SANCTUM_EXPIRATION` permite reducir ese periodo por entorno.
- `sanctum:prune-expired --hours=24` se ejecuta diariamente.
- `POST /api/logout` revoca solo el token utilizado en la solicitud.
- `POST /api/logout-all` exige la contrasena actual y revoca todos los tokens.
- Un futuro cambio o restablecimiento de contrasena debe ejecutar
  `$user->tokens()->delete()` dentro del flujo exitoso.
- Ante un incidente de cuenta se debe revocar todos los tokens antes de emitir
  uno nuevo y revisar los accesos registrados.

`device_name` es opcional al registrar o iniciar sesion, tiene un maximo de 100
caracteres y no admite caracteres de control. Debe ser un nombre reconocible,
como `Chrome en laptop`, y nunca debe contener email, contrasena o token.

## Cliente React

- Mantener el token solamente en memoria durante el MVP.
- No guardar el token en `localStorage`, `sessionStorage`, IndexedDB ni cookies
  accesibles desde JavaScript.
- Enviar el token exclusivamente mediante `Authorization: Bearer <token>` sobre
  HTTPS.
- Eliminarlo de memoria al recibir `401`, al cerrar sesion o al vencer la sesion.
- Un refresh de pagina requerira iniciar sesion otra vez hasta que se disene un
  mecanismo de renovacion o se migre deliberadamente al modo SPA con cookies.
- No incluir el token en URLs, analytics, reportes de errores ni logs.
