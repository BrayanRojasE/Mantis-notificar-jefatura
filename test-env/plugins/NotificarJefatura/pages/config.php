<?php
/**
 * Página de administración del plugin NotificarJefatura.
 * Gestión → Plugins → Notificar Jefatura
 *
 * Lista el mapeo responsable → jefatura actual, agrupado por responsable
 * (todas sus jefaturas en una sola fila), y permite guardar el conjunto
 * completo de jefaturas de un responsable de una sola vez eligiendo
 * usuarios de una lista con checkboxes y buscador (sin escribir usernames a
 * mano, para no arriesgar un typo que deje a alguien sin cobertura).
 */

auth_reauthenticate();

// Se exige ADMINISTRATOR de forma fija -no config_get_global('manage_plugin_threshold')-
// porque ese umbral es ajustable en config_inc.php y podría quedar por debajo
// de administrador en esta instalación. Esta página maneja quién se entera
// de las asignaciones de todo el equipo, así que el nivel no debe depender
// de una configuración que alguien más pueda haber cambiado para otro fin.
access_ensure_global_level( ADMINISTRATOR );

$t_table = plugin_table( 'mapa' );

# ---------------------------------------------------------------------------
# Acciones (POST)
# - guardar_mapeo: guarda el conjunto completo de jefaturas de un
#   responsable (agrega las nuevas, saca las que se hayan desmarcado).
#   Sirve tanto para cargar un responsable nuevo como para editar uno
#   existente: siempre reemplaza el conjunto completo.
# - eliminar_responsable: saca todas las jefaturas de un responsable.
# ---------------------------------------------------------------------------
$f_accion = gpc_get_string( 'accion', '' );

if ( $f_accion === 'guardar_mapeo' ) {
	form_security_validate( 'plugin_NotificarJefatura_config' );

	$f_responsable_id = gpc_get_int( 'responsable_id' );
	$f_jefatura_ids    = gpc_get_int_array( 'jefatura_ids', array() );
	$f_jefatura_ids    = array_unique( array_filter( array_map( 'intval', $f_jefatura_ids ), function( $p_id ) use ( $f_responsable_id ) {
		return $p_id > 0 && $p_id !== $f_responsable_id;
	} ) );

	if ( $f_responsable_id > 0 ) {
		$t_query = "SELECT id, jefatura_id FROM $t_table WHERE responsable_id = " . db_param();
		$t_result = db_query( $t_query, array( $f_responsable_id ) );
		$t_actuales = array();
		while ( $t_row = db_fetch_array( $t_result ) ) {
			$t_actuales[(int) $t_row['jefatura_id']] = (int) $t_row['id'];
		}

		# Sacar las que se desmarcaron.
		foreach ( $t_actuales as $t_jefatura_id => $t_fila_id ) {
			if ( !in_array( $t_jefatura_id, $f_jefatura_ids, true ) ) {
				$t_query = "DELETE FROM $t_table WHERE id = " . db_param();
				db_query( $t_query, array( $t_fila_id ) );
			}
		}

		# Agregar las nuevas.
		foreach ( $f_jefatura_ids as $t_jefatura_id ) {
			if ( !isset( $t_actuales[$t_jefatura_id] ) ) {
				$t_query = "INSERT INTO $t_table ( responsable_id, jefatura_id ) VALUES ( "
					. db_param() . ', ' . db_param() . ' )';
				db_query( $t_query, array( $f_responsable_id, $t_jefatura_id ) );
			}
		}
	}

	form_security_purge( 'plugin_NotificarJefatura_config' );
	print_header_redirect( plugin_page( 'config', true ) );

} elseif ( $f_accion === 'eliminar_responsable' ) {
	form_security_validate( 'plugin_NotificarJefatura_config' );

	$f_responsable_id = gpc_get_int( 'responsable_id' );
	$t_query = "DELETE FROM $t_table WHERE responsable_id = " . db_param();
	db_query( $t_query, array( $f_responsable_id ) );

	form_security_purge( 'plugin_NotificarJefatura_config' );
	print_header_redirect( plugin_page( 'config', true ) );
}

# ---------------------------------------------------------------------------
# Vista
# ---------------------------------------------------------------------------
layout_page_header( plugin_lang_get( 'title' ) );

layout_page_begin( 'manage_overview_page.php' );

print_manage_menu( 'manage_plugin_page.php' );

$t_user_table = db_get_table( 'user' );

# Lista de usuarios habilitados, para los checkbox de responsable/jefatura.
# Se arma a mano (en vez de un helper de Mantis) para no depender de firmas
# de función que puedan variar entre versiones — esta consulta es estable
# en cualquier 2.x.
$t_usuarios = array();
$t_query = "SELECT id, username, realname FROM $t_user_table WHERE enabled = " . db_param() . " ORDER BY realname, username";
$t_result = db_query( $t_query, array( 1 ) );
while ( $t_row = db_fetch_array( $t_result ) ) {
	$t_usuarios[(int) $t_row['id']] = trim( $t_row['realname'] ) !== ''
		? $t_row['realname'] . ' (' . $t_row['username'] . ')'
		: $t_row['username'];
}

function nj_nombre_usuario( $p_id, $t_usuarios ) {
	return isset( $t_usuarios[$p_id] ) ? $t_usuarios[$p_id] : '(usuario ' . $p_id . ', ya no existe)';
}

# Mapeo agrupado por responsable: cada responsable_id junta todas sus
# jefaturas, para listarlas juntas en una sola fila de la grilla.
$t_query = "SELECT id, responsable_id, jefatura_id FROM $t_table ORDER BY responsable_id, id";
$t_result = db_query( $t_query );

$t_grupos = array();
while ( $t_row = db_fetch_array( $t_result ) ) {
	$t_responsable_id = (int) $t_row['responsable_id'];
	if ( !isset( $t_grupos[$t_responsable_id] ) ) {
		$t_grupos[$t_responsable_id] = array();
	}
	$t_grupos[$t_responsable_id][] = (int) $t_row['jefatura_id'];
}

# Orden alfabético por nombre de responsable, no por id.
uksort( $t_grupos, function( $p_a, $p_b ) use ( $t_usuarios ) {
	return strcasecmp( nj_nombre_usuario( $p_a, $t_usuarios ), nj_nombre_usuario( $p_b, $t_usuarios ) );
} );

# Si venimos de "Editar", precargar responsable y jefaturas ya asignadas.
$f_editar_id = gpc_get_int( 'editar', 0 );
$t_jefaturas_preseleccionadas = isset( $t_grupos[$f_editar_id] ) ? $t_grupos[$f_editar_id] : array();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">Notificar Jefatura</h4>
	</div>
	<div class="widget-body">
		<div class="widget-main">

			<p style="color:#666;">
				Cuando se le asigna un incidente a un <strong>responsable</strong>, avisa por correo
				a su <strong>jefatura</strong>.
			</p>

			<ul style="list-style:none; padding:0; margin:0 0 16px 0;">
				<li style="padding:6px 0; border-bottom:1px solid #eee;">
					<i class="fa fa-envelope-o" style="width:20px; color:#5b9bd5;"></i>
					Correo propio para la jefatura -no una copia del que recibe el responsable- con
					proyecto, N.º de incidente, responsable, fecha y link.
				</li>
				<li style="padding:6px 0; border-bottom:1px solid #eee;">
					<i class="fa fa-bolt" style="width:20px; color:#5b9bd5;"></i>
					Se dispara al crear el incidente ya asignado, y también al reasignarlo después.
				</li>
				<li style="padding:6px 0; border-bottom:1px solid #eee;">
					<i class="fa fa-sitemap" style="width:20px; color:#5b9bd5;"></i>
					Un responsable puede tener varias jefaturas, y una jefatura puede cubrir a
					varios responsables.
				</li>
				<li style="padding:6px 0;">
					<i class="fa fa-user-o" style="width:20px; color:#5b9bd5;"></i>
					La jefatura debe tener cuenta propia en Mantis: las notificaciones van a
					usuarios, no a correos sueltos.
				</li>
			</ul>

			<div class="space-10"></div>

			<div class="table-responsive" style="border:1px solid #e2e2e2; border-radius:4px; overflow:hidden;">
			<table class="table table-hover" style="margin-bottom:0;">
				<thead>
					<tr style="background:#f2f6f9;">
						<th style="width:26%; padding:10px 12px;">Responsable</th>
						<th style="padding:10px 12px;">Jefaturas asignadas</th>
						<th style="width:190px; padding:10px 12px;">Acciones</th>
					</tr>
				</thead>
				<tbody>
<?php
if ( empty( $t_grupos ) ) {
	?>
					<tr>
						<td colspan="3" style="text-align:center; padding:24px; color:#888;">
							<i class="fa fa-info-circle"></i> Todavía no hay ninguna relación cargada.
						</td>
					</tr>
	<?php
}

foreach ( $t_grupos as $t_responsable_id => $t_jefatura_ids ) {
	$t_nombres_jefaturas = array_map( function( $p_id ) use ( $t_usuarios ) {
		return nj_nombre_usuario( $p_id, $t_usuarios );
	}, $t_jefatura_ids );
	?>
					<tr style="border-top:1px solid #eee;">
						<td class="bold" style="vertical-align:middle; padding:12px;"><?php echo string_display_line( nj_nombre_usuario( $t_responsable_id, $t_usuarios ) ); ?></td>
						<td style="vertical-align:middle; padding:12px;">
							<div style="display:flex; flex-wrap:wrap; gap:6px;">
<?php foreach ( $t_nombres_jefaturas as $t_nombre_jefatura ) { ?>
								<span class="label label-info" style="padding:5px 9px; font-weight:normal; border-radius:3px;"><?php echo string_display_line( $t_nombre_jefatura ); ?></span>
<?php } ?>
							</div>
						</td>
						<td style="vertical-align:middle; padding:12px;">
							<a class="btn btn-xs btn-primary" style="margin-right:6px;" href="<?php echo plugin_page( 'config' ) . '&editar=' . $t_responsable_id . '#nj-form'; ?>">
								<i class="fa fa-pencil"></i> Editar
							</a>
							<form method="post" action="<?php echo plugin_page( 'config' ); ?>" style="display:inline;">
								<?php echo form_security_field( 'plugin_NotificarJefatura_config' ); ?>
								<input type="hidden" name="accion" value="eliminar_responsable">
								<input type="hidden" name="responsable_id" value="<?php echo $t_responsable_id; ?>">
								<button type="submit" class="btn btn-xs btn-danger"
									onclick="return confirm('¿Quitar todas las jefaturas de este responsable?');">
									<i class="fa fa-trash-o"></i> Eliminar
								</button>
							</form>
						</td>
					</tr>
	<?php
}
?>
				</tbody>
			</table>
			</div>

			<div class="space-10"></div>

			<div class="widget-box widget-color-blue2" id="nj-form" style="box-shadow:none;">
				<div class="widget-header widget-header-small">
					<h5 class="widget-title lighter">
						<?php echo $f_editar_id > 0 ? 'Editar jefaturas de ' . string_display_line( nj_nombre_usuario( $f_editar_id, $t_usuarios ) ) : 'Cargar responsable nuevo'; ?>
					</h5>
				</div>
				<div class="widget-body">
					<div class="widget-main">
						<form method="post" action="<?php echo plugin_page( 'config' ); ?>">
							<?php echo form_security_field( 'plugin_NotificarJefatura_config' ); ?>
							<input type="hidden" name="accion" value="guardar_mapeo">

							<div class="row">
								<div class="col-md-4 col-xs-12">
									<label>Responsable</label>
									<select name="responsable_id" class="form-control" required>
										<option value="">-- seleccionar --</option>
<?php foreach ( $t_usuarios as $t_id => $t_etiqueta ) { ?>
										<option value="<?php echo $t_id; ?>" <?php echo $t_id === $f_editar_id ? 'selected' : ''; ?>><?php echo string_display_line( $t_etiqueta ); ?></option>
<?php } ?>
									</select>
								</div>

								<div class="col-md-8 col-xs-12">
									<label>Jefatura(s)</label>
									<div class="input-group" style="margin-bottom:8px; width:100%;">
										<span class="input-group-addon" style="background:#f7f7f7;"><i class="fa fa-search"></i></span>
										<input type="text" id="nj-buscador-jefaturas" class="form-control"
											placeholder="Buscar por nombre o usuario...">
									</div>
									<div id="nj-lista-jefaturas" style="max-height:220px; overflow-y:auto; border:1px solid #ddd; border-radius:4px; padding:10px; background:#fff;">
<?php foreach ( $t_usuarios as $t_id => $t_etiqueta ) { ?>
										<label class="nj-jefatura-opcion" style="display:block; font-weight:normal; padding:3px 0;">
											<input type="checkbox" name="jefatura_ids[]" value="<?php echo $t_id; ?>"
												<?php echo in_array( $t_id, $t_jefaturas_preseleccionadas, true ) ? 'checked' : ''; ?>>
											<span class="nj-jefatura-nombre"><?php echo string_display_line( $t_etiqueta ); ?></span>
										</label>
<?php } ?>
									</div>
								</div>
							</div>

							<div class="space-10"></div>

							<button type="submit" class="btn btn-primary">Guardar</button>
<?php if ( $f_editar_id > 0 ) { ?>
							<a class="btn btn-default" href="<?php echo plugin_page( 'config' ); ?>">Cancelar edición</a>
<?php } ?>
						</form>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>
</div>

<script>
(function() {
	var buscador = document.getElementById('nj-buscador-jefaturas');
	if (!buscador) { return; }
	buscador.addEventListener('input', function() {
		var texto = buscador.value.toLowerCase();
		var opciones = document.querySelectorAll('#nj-lista-jefaturas .nj-jefatura-opcion');
		for (var i = 0; i < opciones.length; i++) {
			var nombre = opciones[i].querySelector('.nj-jefatura-nombre').textContent.toLowerCase();
			opciones[i].style.display = nombre.indexOf(texto) !== -1 ? '' : 'none';
		}
	});
})();
</script>

<?php
layout_page_end();
