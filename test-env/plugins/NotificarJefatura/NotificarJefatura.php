<?php
/**
 * NotificarJefatura — plugin para MantisBT 2.x
 *
 * Cuando Mantis notifica al responsable (handler) de un incidente por correo
 * -típicamente al asignarlo-, este plugin agrega como destinatario adicional
 * a la jefatura de ese responsable. No reemplaza el correo del responsable:
 * SUMA un destinatario extra a la misma notificación que Mantis ya iba a
 * enviar (mismo asunto, mismo contenido).
 *
 * El mapeo "responsable → jefatura" NO se edita en este archivo: vive en una
 * tabla propia del plugin y se administra desde una página web dentro del
 * propio Mantis (Gestión → Plugins → Notificar Jefatura), con menús
 * desplegables de los usuarios existentes. Así, cuando alguien deja de ser
 * jefatura o se suma un responsable nuevo a un equipo, se actualiza ahí
 * mismo -sin tocar código ni volver a desplegar nada.
 *
 * Un mismo responsable puede tener más de una jefatura asignada (se agregan
 * como filas separadas), y una misma jefatura puede cubrir a varios
 * responsables (esa es, de hecho, la forma normal de usarlo: una fila por
 * cada persona de su equipo).
 *
 * INSTALACIÓN
 * 1. Copiar esta carpeta completa (NotificarJefatura/, con su subcarpeta
 *    pages/) dentro de plugins/ en el servidor de Mantis.
 * 2. Entrar como administrador a Gestión → Plugins e instalarlo/activarlo.
 *    Al activarse por primera vez, Mantis crea la tabla propia del plugin
 *    sola (ver schema() más abajo) — no hace falta correr SQL a mano.
 * 3. Ir a Gestión → Plugins → Notificar Jefatura para cargar el mapeo real
 *    del equipo.
 *
 * VERIFICADO CONTRA EL CÓDIGO FUENTE OFICIAL DE MANTISBT
 * El hook (EVENT_NOTIFY_USER_INCLUDE) y su firma exacta se confirmaron
 * leyendo core/email_api.php, core/event_api.php y core/plugin_api.php del
 * repositorio oficial (rama de la que desciende 2.26.2) — no es una
 * suposición. Ese evento es un string literal, no una constante de PHP: no
 * va a aparecer con un grep a core/constant_inc.php, así que no hace falta
 * buscarlo ahí.
 *
 * Se activa para $p_notify_type === 'owner' (reasignación de responsable) y
 * también 'new' (creación del incidente ya asignado a un responsable), los
 * dos tipos que Mantis usa cuando hay un handler involucrado (confirmado en
 * el mismo email_api.php) — no copia a la jefatura en cada comentario o
 * cambio de estado posterior del incidente, solo al asignar o crear.
 *
 * Aun así, recomendado probar primero en una copia de prueba del Mantis, no
 * directo en producción: es la primera vez que este plugin corre contra la
 * base real, y el paso de creación de tabla (schema(), al activar) conviene
 * verlo funcionar una vez antes de confiar en él para todo el equipo.
 */

class NotificarJefaturaPlugin extends MantisPlugin {

	function register() {
		$this->name        = 'Notificar Jefatura';
		$this->description = 'Cuando a un responsable se le asigna un incidente (al crearlo o al reasignarlo), le manda a su jefatura un correo propio -no una copia del que recibe el responsable- con un formato de aviso formal (proyecto, N.º de incidente, responsable, fecha y link). Un responsable puede tener varias jefaturas y viceversa; el mapeo se administra desde una página propia con buscador, sin tocar código.';
		$this->page        = 'config';

		$this->version     = '1.2.0';
		$this->requires    = array(
			'MantisCore' => '2.0.0',
		);

		$this->author  = 'BrayanRojasE';
		$this->contact = '';
		$this->url     = '';
	}

	function hooks() {
		return array(
			'EVENT_NOTIFY_USER_INCLUDE' => 'notify_include',
		);
	}

	/**
	 * Tabla propia del plugin: una fila por cada par (responsable, jefatura).
	 * Mantis prefija el nombre real segun su convencion (algo como
	 * mantis_plugin_NotificarJefatura_mapa_table) via plugin_table().
	 */
	function schema() {
		return array(
			array( 'CreateTableSQL', array(
				plugin_table( 'mapa' ),
				"id I(11) UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				 responsable_id I(11) UNSIGNED NOTNULL,
				 jefatura_id I(11) UNSIGNED NOTNULL"
			) ),
		);
	}

	/**
	 * EVENT_NOTIFY_USER_INCLUDE
	 * Verificado contra core/email_api.php de MantisBT: Mantis llama a este
	 * hook pasando SOLO el id del incidente y el tipo de notificación -NUNCA
	 * la lista de destinatarios ya calculada, a diferencia de lo que
	 * supondría un nombre como "_INCLUDE". Lo que se devuelve no reemplaza
	 * nada: son destinatarios que se SUMAN aparte a los que Mantis ya
	 * resolvió por su cuenta (reportador, responsable, monitores, etc.).
	 * Devolver aquí una lista "final" en vez de solo las adiciones sería
	 * incorrecto y, peor, rompería con error fatal si se intenta tratar el
	 * tercer parámetro como si fuera un array de ids (es un string).
	 *
	 * $p_notify_type llega como 'owner' cuando la notificación es por
	 * asignación posterior a la creación (verificado: en email_api.php,
	 * 'owner' mapea al pref 'email_on_assigned'), y como 'new' cuando el
	 * incidente se acaba de crear (email_bug_added). Se le manda un correo
	 * a la jefatura en ambos casos -si QA crea el incidente ya asignado
	 * directo a un responsable, su jefatura se entera en el mismo momento,
	 * no recién en la próxima reasignación- pero no en otros eventos
	 * (comentarios, cambios de estado, etc.).
	 *
	 * A diferencia de una versión anterior, esta función NO devuelve ids
	 * para que Mantis les copie el correo completo del responsable: arma y
	 * manda ella misma un correo resumido propio (id, resumen, proyecto,
	 * responsable y link), vía email_store(). El array de retorno de este
	 * hook siempre queda vacío a propósito.
	 *
	 * @param string $p_event        nombre del evento (sin uso aquí)
	 * @param int    $p_bug_id       id del incidente
	 * @param string $p_notify_type  tipo de notificación ('owner' = asignación, 'new' = creación)
	 * @return array                 vacío siempre (el envío lo hace este método, no Mantis)
	 */
	function notify_include( $p_event, $p_bug_id, $p_notify_type ) {
		if ( $p_notify_type !== 'owner' && $p_notify_type !== 'new' ) {
			return array();
		}

		$t_bug = bug_get( $p_bug_id, true );

		// Sin responsable asignado, no hay a quién referenciar.
		if ( (int) $t_bug->handler_id === 0 ) {
			return array();
		}

		$t_table = plugin_table( 'mapa' );
		$t_query = "SELECT jefatura_id FROM $t_table WHERE responsable_id = " . db_param();
		$t_result = db_query( $t_query, array( (int) $t_bug->handler_id ) );

		$t_jefatura_ids = array();
		while ( $t_row = db_fetch_array( $t_result ) ) {
			$t_jefe_id = (int) $t_row['jefatura_id'];
			if ( $t_jefe_id > 0 ) {
				$t_jefatura_ids[] = $t_jefe_id;
			}
		}

		if ( empty( $t_jefatura_ids ) ) {
			return array();
		}

		$t_responsable_nombre = user_get_name( $t_bug->handler_id );
		$t_proyecto_nombre    = project_get_name( $t_bug->project_id );
		$t_id_formateado      = bug_format_id( $p_bug_id );
		$t_link               = string_get_bug_view_url_with_fqdn( $p_bug_id );
		$t_fecha              = date( 'd-m-Y', $t_bug->date_submitted );

		$t_subject = '[' . $t_proyecto_nombre . ' ' . $t_id_formateado . '] Asignado a ' . $t_responsable_nombre;

		foreach ( $t_jefatura_ids as $t_jefe_id ) {
			$t_jefe_email = user_get_email( $t_jefe_id );
			if ( is_blank( $t_jefe_email ) ) {
				continue;
			}

			$t_jefe_nombre = user_get_name( $t_jefe_id );

			$t_cuerpo = "Estimado/a $t_jefe_nombre:\n\n"
				. "Junto con saludar, informo que se ha levantado un incidente correspondiente al proyecto "
				. "$t_proyecto_nombre, con el objetivo de gestionar y dar seguimiento a la situación detectada.\n\n"
				. "El incidente fue asignado al responsable $t_responsable_nombre, quien estará a cargo del "
				. "análisis, gestión y resolución del caso de acuerdo con el procedimiento establecido.\n\n"
				. "A continuación, se detallan los antecedentes principales:\n\n"
				. "Proyecto: $t_proyecto_nombre - $t_link\n"
				. "N.º de Incidente: $t_id_formateado\n"
				. "Responsable: $t_responsable_nombre\n"
				. "Fecha de levantamiento: $t_fecha\n\n"
				. "Quedo atento(a) a cualquier consulta.\n\n"
				. "Saludos cordiales.\n";

			email_store( $t_jefe_email, $t_subject, $t_cuerpo );
		}

		return array();
	}
}
