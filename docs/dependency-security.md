# Seguridad de dependencias

## Estado inicial

Auditoria ejecutada el 24 de septiembre de 2026 sobre los lockfiles actuales:

- Composer: cero advisories y cero paquetes abandonados.
- npm: cero vulnerabilidades; 158 dependencias totales analizadas.
- `composer.lock` y `package-lock.json` estan versionados.

Los resultados son temporales. El workflow `Security audit` repite la consulta
cada lunes, en cambios de dependencias y bajo ejecucion manual.

## Politica de severidad

- Composer bloquea cualquier advisory y cualquier paquete abandonado.
- npm bloquea vulnerabilidades `high` y `critical`.
- Hallazgos `moderate` o `low` de npm deben revisarse, documentarse y corregirse
  cuando exista una version compatible; no se ignoran silenciosamente.
- Una excepcion requiere justificar explotabilidad, mitigacion, responsable y
  fecha limite. No se desactiva la auditoria completa para aceptar un paquete.
- Ninguna actualizacion de Dependabot se fusiona automaticamente.

## Revision de paquetes nuevos

Antes de instalar o actualizar una dependencia:

1. Confirmar que la funcionalidad no existe ya en Laravel o el repositorio.
2. Revisar mantenedor, actividad reciente, licencia, advisories y paquetes abandonados.
3. Revisar scripts Composer/npm y plugins que puedan ejecutar codigo al instalar.
4. Confirmar que el paquete solicitado coincide con el descargado y revisar el cambio de lockfile.
5. Ejecutar pruebas, build y ambas auditorias antes de fusionar.
6. Evitar `npm audit fix --force` y actualizaciones mayores automaticas sin revisar cambios incompatibles.

## Scripts revisados

- Composer permite solamente `pestphp/pest-plugin` y `php-http/discovery` como plugins.
- Los hooks Composer restantes pertenecen al esqueleto Laravel y ejecutan Artisan,
  descubrimiento de paquetes, publicacion de assets o tareas explicitas de setup.
- npm expone solo `vite` para `dev` y `build`; no contiene hooks de instalacion.
- En CI, `npm audit --package-lock-only` no ejecuta scripts de paquetes.

## Actualizaciones periodicas

Dependabot revisa semanalmente Composer, npm y GitHub Actions. Cada pull request
debe pasar el workflow de auditoria y recibir revision humana, prestando especial
atencion a cambios de scripts, nuevas dependencias transitivas y URLs de descarga.
