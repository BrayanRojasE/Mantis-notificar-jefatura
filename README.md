# 📬 Mantis — Notificar Jefatura

Plugin para [MantisBT](https://www.mantisbt.org/) 2.x: cuando a un
responsable se le asigna un incidente (al crearlo o al reasignarlo), le
manda a su jefatura un **correo propio** —no una copia del que recibe el
responsable— con proyecto, N.º de incidente, responsable, fecha y link.

## ✨ Características

- **Correo propio y formal**, no una copia del que recibe el responsable:
  arma y envía su propio mensaje con los datos clave del incidente.
- **Se dispara en dos momentos**: al crear el incidente ya asignado a un
  responsable, y al reasignarlo — no en comentarios ni cambios de estado
  posteriores.
- **Mapeo responsable → jefatura configurable desde la web**, en Gestión →
  Plugins → Notificar Jefatura, con buscador y checkboxes — sin tocar código
  ni volver a desplegar nada.
- **Relación muchos-a-muchos**: un responsable puede tener varias jefaturas
  asignadas, y una misma jefatura puede cubrir a varios responsables.
- **No reemplaza nada**: se suma como destinatario extra a la notificación
  que Mantis ya envía, usando el sistema de correo estándar de Mantis (no
  manda nada por su cuenta ni requiere SMTP propio).

## 🚀 Uso rápido (ambiente de prueba local)

Incluye un ambiente Docker con MantisBT + MySQL + [Mailpit](https://mailpit.axllent.org/)
para ver los correos generados sin necesidad de un SMTP real.

```bash
cd test-env
cp config_inc.php.example data/config/config_inc.php
# Editar data/config/config_inc.php y poner un $g_crypto_master_salt propio
docker compose up -d
```

Seguir el resto de los pasos en
[`test-env/plugins/PASOS_DE_PRUEBA.md`](test-env/plugins/PASOS_DE_PRUEBA.md).

## 📦 Instalar en otra instancia de Mantis

1. Copiar `test-env/plugins/NotificarJefatura/` dentro de `plugins/` del
   Mantis destino.
2. Como administrador: Gestión → Plugins → instalar/activar "Notificar
   Jefatura".
3. Gestión → Plugins → Notificar Jefatura → cargar el mapeo real del
   equipo.

Requiere MantisBT 2.x y que el Mantis destino tenga el correo saliente
configurado (SMTP/Exchange/Outlook, etc.) — el plugin usa el sistema de
correo estándar de Mantis, no manda nada por su cuenta.

## 🗂️ Estructura

| Archivo/carpeta | Qué es |
|---|---|
| [`test-env/plugins/NotificarJefatura/`](test-env/plugins/NotificarJefatura/) | Código fuente del plugin. |
| [`test-env/docker-compose.yml`](test-env/docker-compose.yml) | Ambiente de prueba local (MantisBT + MySQL + Mailpit para ver los correos sin SMTP real). |
| [`test-env/plugins/PASOS_DE_PRUEBA.md`](test-env/plugins/PASOS_DE_PRUEBA.md) | Guía paso a paso para levantar el ambiente y probar el plugin de punta a punta. |

## 🛠️ Detalles técnicos

El plugin engancha el hook `EVENT_NOTIFY_USER_INCLUDE` de MantisBT, que se
dispara para `$p_notify_type === 'owner'` (reasignación) y `'new'`
(creación con responsable ya asignado) — los dos casos en que Mantis
notifica a un handler. El mapeo responsable → jefatura vive en una tabla
propia del plugin (creada automáticamente al activarlo, sin correr SQL a
mano).
