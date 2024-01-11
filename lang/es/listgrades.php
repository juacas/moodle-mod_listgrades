<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'listgrades', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   mod_listgrades
 * @copyright 2024 onwards Juan Pablo de Castro  {@email juanpablo.decastro@uva.es}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 $string['aepdmethod'] = 'Ofuscación de identificadores AEPD';
 $string['aepdmethod_help'] = 'Usar el método de ofuscación de identificadores que se utilizará para cumplir con la normativa de la Agencia Española de Protección de Datos: <a href="https://www.aepd.es/documento/orientaciones-da7.pdf">https://www.aepd.es/documento/orientaciones-da7.pdf</a>';
 $string['closedate'] = 'Fin de publicación de notas';
 $string['closedate_help'] = 'La fecha y hora en que se cerrará la lista.';

 $string['defaultfooter'] = 'Texto predeterminado del pie de página';
 $string['defaultfooter_help'] = 'Texto predeterminado del pie de página que se mostrará al final del listado.';
 $string['defaultfootertext'] = '<hr>
 <p>Revisión de las calificaciones:</p>
 <p>Fecha:</p>
 <p>Hora:</p>
 <p>Lugar:</p>
 <hr>
 <p><img class="img-fluid align-bottom" style="margin: 10px; float: left;" role="presentation" src="http://moodle3.local/moodle40/pluginfile.php/1/theme_moove/logo/1704277217/UVA_cuadro_rojo.jpg" alt="" width="64" height="64" align="left">Esta publicación se realiza con fines informativos en ejercicio de misiones de interés público previstas en la Ley Orgánica del Sistema Universitario. Su uso por el estudiante para otros fines, y en particular su alteración, manipulación o distribución indebida en redes sociales u otros medios públicos puede generar responsabilidad jurídica.</p>';
 $string['defaultintro'] = 'Texto predeterminado de introducción';
 $string['defaultintrotext'] = '<table border="0" width="100%"><tbody><tr><td><h3><img role="presentation" src="{$a->logourl}" alt="" width="121" height="78"></h3></td>
 <td><h3>LISTADO DE CALIFICACIONES PROVISIONALES</h3><p>Convocatoria: ORDINARIA</p></td>
 </tr></tbody></table>';
 $string['defaultintro_help'] = 'Texto predeterminado de introducción que se mostrará en la lista.';
 $string['footer'] = 'Sección de pie de página';
 $string['footerheader'] = 'Pie de página';
 $string['createlisting'] = 'Crear listado';
 $string['gradeitems'] = 'Elementos de calificación para publicar.';
 $string['gradeitems_help'] = 'Seleccione los elementos de calificación que se publicarán. Mantenga presionada la tecla CTRL para seleccionar varios campos.';

 $string['modulename'] = 'Publicación de notas';
 $string['modulename_help'] = 'El módulo "list grades" permite a un profesor publicar las calificaciones de todos los estudiantes en una página del curso para cumplir con los requisitos de transparencia. Protege la privacidad de los estudiantes al ocultar el campo de usuario seleccionado por el profesor.';
 $string['modulename_link'] = 'mod/listgrades/view';
 $string['modulenameplural'] = 'Listar Calificaciones';
 $string['opendate'] = 'Publicación de notas';
 $string['opendate_help'] = 'La fecha y hora en que se publicará la lista.';
 $string['listgrades:addinstance'] = 'Añadir un listado de notas';
 $string['listgrades:view'] = 'Ver listados de notas';
 $string['pluginadministration'] = 'Administración del módulo List Grades';
 $string['pluginname'] = 'Publicación de notas';

 $string['printintro'] = 'Mostrar descripción del encabezado';
 $string['privacy:metadata'] = 'El complemento de recurso Listgrades no almacena ningún dato personal.';
 $string['search:activity'] = 'listgrades';
 $string['showusername'] = 'Mostrar nombres de usuario';
 $string['showusername_help'] = 'Seleccione si se mostrará el nombre de usuario en la lista de calificaciones.';
 $string['showuserfield'] = 'Mostrar identificadores de usuario';
 $string['showuserfield_help'] = 'Seleccione si se mostrará el ID de usuario en la lista de calificaciones.';
 $string['userfield'] = 'Campo de usuario';
 $string['userfield_help'] = 'Seleccione el campo de usuario que se utilizará para mostrar la lista de usuarios.';
 $string['userfieldmask'] = 'Máscara de campo de usuario';
 $string['userfieldmask_help'] = 'Defina la máscara que se utilizará para ocultar el campo de usuario. Una * sustituirá al carácter, un - omitirá el carácter y un + mostrará el carácter.';
 $string['notopen'] = 'Esta lista aún no está disponible.';
 $string['useridalways'] = 'Siempre';
 $string['useridonlyifnamecollide'] = 'Solo si hay colisión de nombres';
 $string['useridnever'] = 'Nunca';
