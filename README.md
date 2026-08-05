# Mantis - Notificar Jefatura

Plugin para MantisBT 2.x: cuando a un responsable se le asigna un incidente
(al crearlo o al reasignarlo), le manda a su jefatura un correo propio -no
una copia del que recibe el responsable- con proyecto, N.º de incidente,
responsable, fecha y link. Un responsable puede tener varias jefaturas
asignadas, y una misma jefatura puede cubrir a varios responsables. El
mapeo responsable → jefatura se administra desde una página propia dentro
de Mantis (Gestión → Plugins → Notificar Jefatura), con buscador y
checkboxes, sin tocar código.

## Estructura

- `test-env/plugins/NotificarJefatura/` — código fuente del plugin.
- `test-env/docker-compose.yml` — ambiente de prueba local (MantisBT +
  MySQL + Mailpit para ver los correos sin SMTP real).
- `test-env/plugins/PASOS_DE_PRUEBA.md` — guía paso a paso para levantar el
  ambiente y probar el plugin de punta a punta.

## Uso rápido (ambiente de prueba local)

```bash
cd test-env
cp config_inc.php.example data/config/config_inc.php
# Editar data/config/config_inc.php y poner un $g_crypto_master_salt propio
docker compose up -d
```

Seguir el resto de los pasos en `test-env/plugins/PASOS_DE_PRUEBA.md`.

## Instalar en otra instancia de Mantis

1. Copiar `test-env/plugins/NotificarJefatura/` dentro de `plugins/` del
   Mantis destino.
2. Como administrador: Gestión → Plugins → instalar/activar "Notificar
   Jefatura".
3. Gestión → Plugins → Notificar Jefatura → cargar el mapeo real del
   equipo.

Requiere MantisBT 2.x y que el Mantis destino tenga el correo saliente
configurado (SMTP/Exchange/Outlook, etc.) — el plugin usa el sistema de
correo estándar de Mantis, no manda nada por su cuenta.
