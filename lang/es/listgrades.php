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
 $string['aepdmethod_help'] = 'Seleccione el método de ofuscación de identificadores que se utilizará para cumplir con la normativa de la Agencia Española de Protección de Datos:
 <li>Dado un DNI con formato 12345678X, se publicarán los dígitos que en el formato que ocupen las posiciones cuarta, quinta, sexta y séptima. En el ejemplo: \*\*\*4567\*\*.
 <li>Dado un NIE con formato L1234567X, se publicarán los dígitos que en el formato ocupen las posiciones, evitando el primer carácter alfabéticos, 
 cuarta, quinta, sexta y séptima. En el ejemplo: \*\*\*\*4567\*.
 <li> Dado un pasaporte con formato ABC123456, al tener sólo seis cifras, se 
  publicarán los dígitos que en el formato ocupen las posiciones, evitando los 
  tres caracteres alfabéticos, tercera, cuarta, quinta y sexta. En el ejemplo: \*\*\*\*\*3456.
 <li> Dado otro tipo de identificación, siempre que esa identificación contenga al 
  menos 7 dígitos numéricos, se numerarán dichos dígitos de izquierda a 
  derecha, evitando todos los caracteres alfabéticos, y se seguirá el 
  procedimiento de publicar aquellos caracteres numéricos que ocupen las 
  posiciones cuarta, quinta, sexta y séptima. Por ejemplo, en el caso de una 
  identificación como: XY12345678AB, la publicación sería: \*\*\*\*\*4567\*\*\*
 <li> Si ese tipo de identificación es distinto de un pasaporte y tiene menos de 7
  dígitos numéricos, se numerarán todos los caracteres, alfabéticos incluidos, 
  con el mismo procedimiento anterior y se seleccionarán aquellos que ocupen 
  las cuatro últimas posiciones. Por ejemplo, en el caso de una identificación 
  como: ABCD123XY, la publicación sería: \*\*\*\*\*23XY
  </ul>';
 $string['closedate'] = 'Fin de publicación de notas';
 $string['closedate_help'] = 'La fecha y hora en que se cerrará la lista.';

 $string['configdisplayoptions'] = 'Seleccione todas las opciones que deben estar disponibles; las configuraciones existentes no se modifican. Mantenga presionada la tecla CTRL para seleccionar varios campos.';
 $string['defaultfooter'] = 'Texto predeterminado del pie de página';
 $string['defaultfooter_help'] = 'Texto predeterminado del pie de página que se mostrará al final del listado.';
 $string['defaultfootertext'] = '<h4>CALIFICACIONES PROVISIONALES</h4>
 <hr>
 <p>Revisión de las calificaciones:</p>
 <p>Fecha:</p>
 <p>Hora:</p>
 <p>Lugar:</p>
 <hr>
 <p><img class="img-fluid align-bottom" style="margin: 10px; float: left;" role="presentation" src="http://moodle3.local/moodle40/pluginfile.php/1/theme_moove/logo/1704277217/UVA_cuadro_rojo.jpg" alt="" width="64" height="64" align="left">Esta publicación se realiza con fines informativos en ejercicio de misiones de interés público previstas en la Ley Orgánica del sistema Universitario. Su uso por el estudiante para otros fines, y en particular su alteración, manipulación o distribución indebida en redes sociales u otros medios públicos puede generar responsabilidad jurídica.</p>';
 $string['defaultintro'] = 'Texto predeterminado de introducción';
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
