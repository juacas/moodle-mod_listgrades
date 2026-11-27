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
 * Strings for component 'listgrades', language 'ca', branch 'MOODLE_20_STABLE'
 *
 * @package    mod_listgrades
 * @copyright  2023 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aepdmethod'] = 'Ocultació d\'identificadors AEPD';
$string['aepdmethod_help'] = 'Fa servir el mètode d\'ocultació d\'identificadors que s\'emprarà per complir la normativa de l\'Agència Espanyola de Protecció de Dades: <a href="https://www.aepd.es/documento/orientaciones-da7.pdf">https://www.aepd.es/documento/orientaciones-da7.pdf</a>';
$string['closedate'] = 'Fi de la publicació de les qualificacions';
$string['closedate_help'] = 'Dia i hora en què es tancarà la llista.';
$string['collapsegradecategory'] = 'Redueix la categoria de qualificació {$a}';
$string['createlisting'] = 'Crea una llista';
$string['defaultfooter'] = 'Text predeterminat del peu de pàgina';
$string['defaultfooter_help'] = 'Text predeterminat del peu de pàgina que apareixerà al final de la llista.';
$string['defaultfootertext'] = '<hr>
 <p>Revisió de les qualificacions:</p>
 <p>Data:</p>
 <p>Hora:</p>
 <p>Lloc:</p>
 <hr>
 <p><img class="img-fluid align-bottom" style="margin: 10px; float: left;" role="presentation" src="{logourl}" alt="" width="64" height="64" align="left">Aquesta publicació té una finalitat informativa en l\'exercici de missions d\'interès públic previstes a la Llei orgànica del sistema universitari. El seu ús per part de l\'estudiant per a altres finalitats i, en particular, la seva alteració, manipulació i distribució indeguda a les xarxes socials o a altres mitjans públics pot generar responsabilitat jurídica.</p>';
$string['defaultintro'] = 'Text predeterminat d\'introducció';
$string['defaultintro_help'] = 'Text predeterminat d\'introducció que apareixerà a la llista.';
$string['defaultintrotext'] = '<table border="0" width="100%"><tbody><tr><td><h3><img role="presentation" src="{logourl}" alt="" width="121" height="78"></h3></td>
 <td><h3>LLISTA DE QUALIFICACIONS PROVISIONALS</h3><p>Convocatòria: ORDINÀRIA</p></td>
 </tr></tbody></table>';
$string['dynamicicon'] = 'Icona dinàmica de l’activitat';
$string['dynamicicon_help'] = 'Si s’activa, la icona de l’activitat indica si l’usuari actual té alguna qualificació o comentari per consultar.';
$string['eventreviewaction'] = 'Acció del procés de revisió registrada';
$string['expandgradecategory'] = 'Amplia la categoria de qualificació {$a}';
$string['footer'] = 'Secció del peu de pàgina<br>No modificar aquest contingut. Una vegada desat només es visualitzarà en un idioma.';
$string['footerheader'] = 'Peu de pàgina';
$string['globalgradesnopass'] = 'Estudiants presentats: {$a}. No es mostren dades de superació perquè no s’ha configurat una nota per aprovar.';
$string['globalgradessummary'] = 'Estudiants presentats: {$a->presented}. Estudiants que superen la prova: {$a->passed} ({$a->percentage} %).';
$string['gradeitems'] = 'Elements de qualificació per publicar.';
$string['gradeitems_help'] = 'Per a cada element, seleccioneu si se’n publica la qualificació, la retroacció o totes dues. Els controls del principi de la secció seleccionen o desseleccionen una columna completa.';
$string['itemnogrades'] = 'No hi ha qualificacions.';
$string['listgrades:addinstance'] = 'Afegeix una llista de qualificacions';
$string['listgrades:managereviews'] = 'Mostra les sol·licituds de revisió de qualificacions';
$string['listgrades:view'] = 'Mostra les llistes de qualificacions';
$string['listingssettings'] = 'Llistats';
$string['listingssettings_help'] = 'Configura la informació que es mostra als llistats de qualificacions.';
$string['modulename'] = 'Publicació de qualificacions';
$string['modulename_help'] = 'El mòdul "listgrades" permet a un professor publicar les qualificacions de tots els estudiants a una pàgina del curs per complir els requisits de transparència. Protegeix la privacitat dels estudiants ocultant el camp d\'usuari seleccionat pel professor.';
$string['modulenameplural'] = 'Llista de qualificacions';
$string['nogradeitemsselected'] = 'No hi ha elements de qualificació seleccionats';
$string['notopen'] = 'Aquesta llista encara no està disponible.';
$string['numericgradesasletters'] = 'Mostra estadístiques de qualificacions com a text';
$string['numericgradesasletters_help'] = 'Afegeix barres horitzontals de percentatges fent servir les equivalències de lletres configurades al curs. L’histograma numèric i el boxplot continuen disponibles de manera independent.';
$string['numericgradewithmaximum'] = '{$a->grade}/{$a->maximum}';
$string['opendate'] = 'Publicació de qualificacions';
$string['opendate_help'] = 'Dia i hora en què es publicarà la llista.';
$string['pluginadministration'] = 'Administració del mòdul "listgrades"';
$string['pluginname'] = 'Publicació de qualificacions';
$string['printintro'] = 'Mostra la descripció de l\'encapçalament';
$string['privacy:metadata'] = 'Listgrades emmagatzema sol·licituds de revisió, comentaris de l’estudiant i del professor, l’autor i la data de cada acció, i una còpia de la taula de qualificacions publicada.';
$string['privacy:metadata:listgrades_review'] = 'Registre d’accions d’estudiants i professors en el procés de revisió de qualificacions.';
$string['privacy:metadata:listgrades_review:action'] = 'Acció registrada: sol·licitud, anul·lació, confirmació, comentari o reobertura.';
$string['privacy:metadata:listgrades_review:actorid'] = 'Usuari que ha fet aquesta acció de revisió.';
$string['privacy:metadata:listgrades_review:comment'] = 'Missatge de revisió, incloent-hi les opcions seleccionades per l’estudiant.';
$string['privacy:metadata:listgrades_review:listgradesid'] = 'Activitat de publicació associada a la revisió.';
$string['privacy:metadata:listgrades_review:snapshot'] = 'Taula de qualificacions publicades desada quan l’estudiant ha confirmat o comentat la revisió.';
$string['privacy:metadata:listgrades_review:timecreated'] = 'Data i hora en què es va registrar l’acció.';
$string['privacy:metadata:listgrades_review:userid'] = 'Estudiant a qui correspon aquest registre del procés de revisió.';
$string['privacyaggregateshidden'] = 'Els resultats agregats estan ocults perquè cinc estudiants o menys tenen qualificació en aquest ítem.';
$string['privacyallfailedhidden'] = 'Les estadístiques estan ocultes perquè totes les qualificacions estan per sota de la nota per aprovar.';
$string['privacyconditions'] = 'Condicions de privacitat';
$string['privacyconditions_help'] = 'Segons el que estableix el Reial decret 855/2026, de 21 d’octubre, pel qual s’aprova l’Estatut de l’Estudiantat Universitari d’Espanya, i que es resumeix en: «No es publicaran resultats agregats quan el nombre d’estudiants presentats sigui igual o inferior a cinc. Tampoc es publicaran el nombre i el percentatge d’aprovats quan tots els presentats hagin aprovat o suspès, ni la distribució per trams quan tots es trobin en el mateix tram.»';
$string['privacydistributionhidden'] = 'La distribució està oculta perquè tots els estudiants es troben en el mateix tram de qualificació.';
$string['privacyglobalhidden'] = 'El nombre i el percentatge d’estudiants que superen la prova estan ocults perquè tots els presentats han aprovat o suspès.';
$string['privacyteachernotice'] = '(Els estudiants no veuran aquest bloc en absolut.)';
$string['resetreviewprocess'] = 'Suprimeix l’historial del procés de revisió de qualificacions';
$string['reviewaddcomment'] = 'Afegeix un comentari';
$string['reviewcancel'] = 'Anul·la la sol·licitud de revisió';
$string['reviewcancelled'] = 'La sol·licitud de revisió s’ha anul·lat. L’acció queda registrada.';
$string['reviewcommenthistory'] = 'Historial de comentaris';
$string['reviewcommentrequired'] = 'Escriviu un comentari sobre el procés de revisió.';
$string['reviewconfirm'] = 'Confirma que s’ha fet la revisió';
$string['reviewcopyavailable'] = 'Tinc accés en línia o una còpia de les meves proves d’avaluació.';
$string['reviewcopyrequested'] = 'He reclamat una còpia de les meves proves d’avaluació.';
$string['reviewcurrentgrades'] = 'Qualificacions actuals (no hi ha cap còpia desada de la resposta)';
$string['reviewdate'] = 'Data de revisió';
$string['reviewdate_help'] = 'Data i hora previstes per a la revisió. A partir d’aquest moment, l’estudiant pot confirmar si s’ha fet o deixar-hi un comentari.';
$string['reviewdaterequired'] = 'Indiqueu la data de revisió quan activeu el procés.';
$string['reviewenabled'] = 'Activa el procés de revisió en aquesta activitat';
$string['reviewenabled_help'] = 'L’estudiant podrà sol·licitar o anul·lar una revisió abans de la data prevista. Després podrà deixar constància de si s’ha fet o afegir-hi un comentari.';
$string['reviewinvalidaction'] = 'Aquesta acció de revisió ja no està disponible.';
$string['reviewlist'] = 'Historial de sol·licituds de revisió';
$string['reviewmanage'] = 'Gestiona les revisions';
$string['reviewnone'] = 'No hi ha sol·licituds de revisió.';
$string['reviewnotrequested'] = 'No heu sol·licitat una revisió.';
$string['reviewobservations'] = 'Tinc observacions sobre el procés de revisió.';
$string['reviewoutcomecancelled'] = 'Sol·licitud anul·lada';
$string['reviewoutcomecommented'] = 'Comentari enviat';
$string['reviewoutcomevalidated'] = 'Confirmada';
$string['reviewprocess'] = 'Activa el procés de revisió de qualificacions';
$string['reviewprocess_help'] = 'Permet que el professor activi un procés de revisió a les activitats de publicació individual de qualificacions.';
$string['reviewpublishedgrade'] = 'La qualificació publicada:';
$string['reviewrecordcompletion'] = 'Registra la realització de la revisió';
$string['reviewreopen'] = 'Permet una altra resposta de l’estudiant';
$string['reviewreopened'] = 'S’ha obert una nova oportunitat de revisió.';
$string['reviewreopenedmessage'] = 'S’ha obert una nova oportunitat de revisió per a l’estudiant.';
$string['reviewrequest'] = 'Sol·licita una revisió';
$string['reviewrequestcancelled'] = 'Sol·licitud de revisió anul·lada';
$string['reviewrequestcount'] = '{$a} sol·licituds de revisió';
$string['reviewrequestcountone'] = '1 sol·licitud de revisió';
$string['reviewrequested'] = 'Revisió sol·licitada';
$string['reviewrequestedmessage'] = 'La sol·licitud de revisió ha quedat registrada. ({$a})';
$string['reviewresult'] = 'Registra el resultat de la revisió';
$string['reviewresult_help'] = 'Seleccioneu les opcions que corresponguin i, si ho voleu, afegiu comentaris sobre la revisió.';
$string['reviewsection'] = 'Procés de revisió de qualificacions';
$string['reviewseen'] = 'He vist les meves proves i he rebut les explicacions.';
$string['reviewselectionrequired'] = 'Seleccioneu almenys una opció.';
$string['reviewstatus'] = 'Estat de la revisió';
$string['reviewstudentcomment'] = 'Comentari de l’estudiant';
$string['reviewsubmitted'] = 'El resultat de la revisió ha quedat registrat.';
$string['reviewteachercomment'] = 'Comentari del professor';
$string['reviewviewsnapshot'] = 'Qualificacions publicades quan l’estudiant ha respost';
$string['reviewwaiting'] = 'Encara no ha arribat la data de revisió.';
$string['scalegradewithreference'] = '{$a->grade} ({$a->scale})';
$string['selectatleastonegradeitem'] = 'Seleccioneu almenys una qualificació o retroacció.';
$string['selectedgradeitems'] = 'Elements seleccionats';
$string['showboxplot'] = 'Mostra el boxplot';
$string['showboxplot_help'] = 'Mostra un boxplot amb el mínim, els quartils, la mediana i el màxim dels ítems de qualificació numèrics.';
$string['showglobalpercentage'] = 'Mostra el percentatge global';
$string['showglobalpercentage_help'] = 'Mostra el nombre d’estudiants presentats i el nombre i percentatge d’estudiants que assoleixen la nota per aprovar.';
$string['showhistogram'] = 'Mostra l’histograma numèric';
$string['showhistogram_help'] = 'Mostra o oculta l’histograma de les qualificacions numèriques.';
$string['showuserfield'] = 'Mostra els identificadors d\'usuari';
$string['showuserfield_help'] = 'Marca si apareixerà l\'ID de l\'usuari a la llista de qualificacions.';
$string['showusername'] = 'Mostra els noms d\'usuari';
$string['showusername_help'] = 'Marca si apareixerà el nom d\'usuari a la llista de qualificacions.';
$string['statisticssettings'] = 'Estadístiques';
$string['statisticssettings_help'] = 'Configura la informació estadística que mostren les publicacions de qualificacions.';
$string['toggleallfeedback'] = 'Selecciona/desselecciona tota la retroacció';
$string['toggleallgrades'] = 'Selecciona/desselecciona totes les qualificacions';
$string['userfield'] = 'Camp de l\'usuari';
$string['userfield_help'] = 'Marca el camp de l\'usuari que es farà servir per mostrar la llista d\'usuaris.';
$string['userfieldmask'] = 'Màscara del camp d\'usuari';
$string['userfieldmask_help'] = 'Defineix la màscara que es farà servir per amagar el camp d\'usuari. Un * substituirà el caràcter; un - ometrà el caràcter, i un + mostrarà el caràcter.';
$string['useridalways'] = 'Sempre';
$string['useridnever'] = 'Mai';
$string['useridonlyifnamecollide'] = 'Només si hi ha col·lisió de noms';
