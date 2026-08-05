# Ambiente de prueba — plugin NotificarJefatura

## 1. Levantar el ambiente

Con Docker Desktop corriendo ("Engine running"):

```bash
cd ruta/al/repo/test-env
docker compose up -d
```

## 2. Instalar Mantis (primera vez)

Abrir: http://localhost:8989/admin/install.php

Completar así (coincide con las credenciales del docker-compose.yml):

| Campo | Valor |
|---|---|
| Type of Database | MySQL/MySQLi |
| Hostname | mysql |
| Username (Database) | mantisbt |
| Password (Database) | mantisbt |
| Database name | bugtracker |
| Admin Username | root |
| Admin Password | root |

Dar a "Install/Upgrade Database". Al terminar, loguearse en http://localhost:8989/
con `administrator` / `root`.

## 3. Activar el plugin

1. Gestión → Plugins (Manage → Plugins)
2. Buscar "Notificar Jefatura" en la lista de plugins no instalados → Instalar/Activar
3. Debe crear sola la tabla `mantis_plugin_NotificarJefatura_mapa_table` (verificar
   que no tire error al activar — eso confirma que `schema()` corrió bien).

## 4. Configurar el mapeo

1. Crear en Mantis (Gestión → Usuarios) al menos 2 usuarios de prueba: uno que
   hará de "responsable" (ej. `resp_test`) y otro de "jefatura" (ej. `jefe_test`).
   Ambos con casilla de correo real o accesible (ej. Mailtrap / Mailhog, ver
   sección 6) para poder ver si les llega el correo.
2. Ir a Gestión → Plugins → Notificar Jefatura.
3. Agregar una fila: Responsable = `resp_test`, Jefatura = `jefe_test`.
4. Confirmar que aparece en la tabla.

## 5. Casos de prueba funcionales

- **Caso feliz**: crear un incidente y asignarlo a `resp_test` (owner). Verificar
  que tanto `resp_test` como `jefe_test` reciben el correo de asignación, con el
  mismo asunto/contenido.
- **Sin mapeo**: asignar un incidente a un responsable que NO está en la tabla de
  mapeo. Verificar que solo el responsable recibe el correo (nadie más se cuela).
- **Doble jefatura**: agregar una segunda fila (mismo `resp_test`, otra jefatura
  `jefe_test_2`). Reasignar un incidente a `resp_test` y verificar que AMBAS
  jefaturas reciben copia.
- **Jefatura compartida**: un mismo `jefe_test` cubriendo a dos responsables
  distintos — asignar incidentes a cada uno y verificar que `jefe_test` recibe
  ambos.
- **No debe dispararse en otros eventos**: comentar el incidente o cambiar su
  estado (sin reasignar) y verificar que la jefatura NO recibe copia de esa
  notificación (el hook solo debe activarse en 'owner'/asignación).
- **Quitar del mapeo**: eliminar la fila desde la página de admin y volver a
  reasignar el mismo incidente — la jefatura ya no debe recibir copia.
- **Permisos de la página admin**: loguearse con un usuario no-administrador
  (ej. developer) y confirmar que no puede acceder a
  Gestión → Plugins → Notificar Jefatura (debe rechazar por nivel de acceso).
- **CSRF**: confirmar que el formulario de agregar/quitar trae el token
  (`form_security_field`) y que un POST sin token es rechazado.

## 6. Ver los correos sin servidor SMTP real

Mantis por defecto intenta usar `mail()` de PHP, que normalmente no funciona en
un contenedor. Para ver los correos que el plugin genera sin configurar SMTP real,
lo más simple es revisar los logs de PHP dentro del contenedor:

```bash
docker compose logs -f mantisbt
```

o entrar al contenedor y mirar si hay un mail log:

```bash
docker compose exec mantisbt sh
```

Si hace falta un flujo más prolijo, se puede levantar un Mailhog/Mailpit
adicional en el compose y apuntar `$g_smtp_host` a él — avísame si llegan a
ese punto y lo agrego.

## 7. Apagar el ambiente

```bash
docker compose down
```

Para borrar todo (incluida la base de datos) y arrancar de cero:

```bash
docker compose down -v
rm -rf data
```
