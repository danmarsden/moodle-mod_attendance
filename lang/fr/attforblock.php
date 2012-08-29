<?PHP // $Id: attforblock.php,v 1.1.2.2 2009/02/23 19:22:45 dlnsk Exp $ 
      // attendanceblk.php - created with Moodle 1.5.3+ (2005060230)

$string['Aacronym'] = 'A';
$string['Afull'] = 'Absent';
$string['Eacronym'] = 'E';
$string['Efull'] = 'Excusé';
$string['Lacronym'] = 'R';
$string['Lfull'] = 'Retard';
$string['Pacronym'] = 'P';
$string['Pfull'] = 'Présent';
$string['acronym'] = 'Acronyme';
$string['add'] = 'Ajouter';
$string['addmultiplesessions'] = 'Ajouter plusieurs sessions';
$string['addsession'] = 'Ajouter une session';
$string['allcourses'] = 'Tous les cours';
$string['all'] = 'Tout';
$string['allpast'] = 'Sessions passées';
$string['attendancedata'] = 'Données de présence';
$string['attendanceforthecourse'] = 'Présence pour le cours';
$string['attendancegrade'] = 'Note de présence';
$string['attendancenotstarted'] = 'La prise de présence n\'a pas encore commencé pour ce cours';
$string['attendancepercent'] = 'Pourcentage de présence';
$string['attendancereport'] = 'Rapport de présence';
$string['attendancesuccess'] = 'Les présences ont bien été enregistrées';
$string['attendanceupdated'] = 'Présence mise à jour avec succès';
$string['attforblock:canbelisted'] = 'Apparaît dans la liste';
$string['attforblock:changepreferences'] = 'Modifier les préférences';
$string['attforblock:changeattendances'] = 'Modifier les présences';
$string['attforblock:export'] = 'Exporter Rapports';
$string['attforblock:manageattendances'] = 'Gérer les présences';
$string['attforblock:takeattendances'] = 'Prendre les présences';
$string['attforblock:view'] = 'Voir les présences';
$string['attforblock:viewreports'] = 'Voir les rapports';
$string['attrecords'] = 'Enregistrements des présences';
$string['calclose'] = 'Fermer';
$string['calmonths'] = 'Janvier,Février,Mars,Avril,Mai,Juin,Juillet,Août,Septembre,Octobre,Novembre,Décembre';
$string['calshow'] = 'Choisir une date';
$string['caltoday'] = 'Aujourd\'hui';
$string['calweekdays'] = 'Di,Lu,Ma,Me,Je,Ve,Sa';
$string['changeattendance'] = 'Modifier la présence';
$string['changeduration'] = 'Modifier la durée';
$string['changesession'] = 'Modifier la session';
$string['column'] = 'Colonne';
$string['columns'] = 'Colonnes';
$string['commonsession'] = 'Commune';
$string['commonsessions'] = 'Communes';
$string['countofselected'] = 'Nombre de sélections';
$string['copyfrom'] = 'Copier les données de présence de';
$string['createmultiplesessions'] = 'Créer plusieurs sessions';
$string['createmultiplesessions_help'] = 'Cette fonction vous permet de créer plusieurs sessions en une seule étape.

  * <strong>Date de début de session</strong>: Sélectionnez la date de début du cours (le 1er jour du cours)
  * <strong>Date de fin de session</strong>: Sélectionnez le dernier jour du cours (dernier jour de prise des présences).
  * <strong>Jours de session</strong>: Sélectionnez le(s) jour(s) de la semaine pendant le(s)quel(s) le cours a lieu (par exemple, Lundi/Mercredi/Vendredi).
  * <strong>Fréquence</strong>: Cela permet de régler la fréquence. Si votre cours a lieu chaque semaine, sélectionnez 1 ; s\'il a lieu toutes les 2 semaines, sélectionnez 2 ; s\'il a lieu toutes les 3 semaines, sélectionnez 3 ; etc.
';
$string['createonesession'] = 'Créer une session pour le cours';
$string['days'] = 'Jour';
$string['defaults'] = 'Valeurs par défaut';
$string['defaultdisplaymode'] = 'Mode d\'affichage par défaut';
$string['delete'] = 'Supprimer';
$string['deletelogs'] = 'Supprimer les données de présence';
$string['deleteselected'] = 'Supprimer la sélection';
$string['deletesession'] = 'Supprimer la session';
$string['deletesessions'] = 'Supprimer toutes les session';
$string['deletingsession'] = 'Supprimer la session pour le cours';
$string['deletingstatus'] = 'Supprimer le staut pour le cours';
$string['description'] = 'Description';
$string['display'] = 'Affichage';
$string['displaymode'] = 'Mode d\'affichage';
$string['downloadexcel'] = 'Télécharger au format Excel';
$string['downloadooo'] = 'Télécharger au format OpenOffice';
$string['downloadtext'] = 'Télécharger au format Texte';
$string['duration'] = 'Durée (heure - minute)';
$string['editsession'] = 'Editer la session';
$string['endtime'] = 'Heure de fin de session';
$string['endofperiod'] = 'Fin de la période';
$string['enrolmentend'] = 'Fin d\'inscription de l\'utilisateur {$a}';
$string['enrolmentstart'] = 'Début d\'inscription de l\'utilisateur {$a}';
$string['enrolmentsuspended'] = 'Inscription suspendue';
$string['errorgroupsnotselected'] = 'Sélectionner 1 ou plusieurs groupes';
$string['errorinaddingsession'] = 'Erreur d\'ajout de session';
$string['erroringeneratingsessions'] = 'Erreur de génération de sessions';
$string['gradebookexplanation'] = 'Noter dans le carnet de notes';
$string['gradebookexplanation_help'] = 'Le module de présence affiche votre note de fréquentation basée sur le nombre de points que vous avez accumulés à ce jour et le nombre de points qui auraient pu être gagnés à ce jour, il ne comprend pas les périodes des cours qui n\'ont pas encore eu lieu. Dans le carnet de notes, votre note de présence est calculée en pourcentage de présence en cours avec le nombre de points qui peuvent être gagnés pendant toute la durée du cours, y compris pour les périodes cours à venir. Ainsi, la note de présence affichée dans le module de présence et la note affichée dans le carnet de notes peut avoir un nombre de points différent, mais elles auront le même pourcentage.

Par exemple, si vous avez gagné 8 des 10 points à ce jour (80% de participation) et si la fréquentation pour l\'ensemble du cours vaut 50 points, le module de présence affichera 8/10 et le carnet de notes affichera 40/50. Vous n\'avez pas encore gagné 40 points, mais 40 est la valeur du point équivalent à votre pourcentage de participation actuel de 80%. La valeur du point que vous avez gagné dans le module de présence ne peut jamais diminuer, car il ne repose que sur la participation à ce jour. Cependant, la valeur du point de présence indiquée dans le carnet de notes peut augmenter ou diminuer en fonction de votre participation future, car elle est basée sur l\'assiduité pour l\'ensemble du cours.';
$string['gridcolumns'] = 'Colonnes de la grille';
$string['groupsession'] = 'Groupe';
$string['hiddensessions'] = 'Sessions masquées';
$string['hiddensessions_help'] = '
Les sessions sont masquées si la date du début du cours est postérieure à la date des sessions. Changer la date de début des cours permettra d\'afficher les sessions masquées.

Vous pouvez utiliser cette fonction pour cacher d\'anciennes sessions au lieu de les supprimer. Mais rappelez-vous que seules les sessions visibles sont prises en compte dans le carnet de notes.';
$string['identifyby'] = 'Identifier l\'étudiant par';
$string['includeall'] = 'Sélectionner toutes les sessions';
$string['includenottaken'] = 'Inclure les sessions non renseignées';
$string['indetail'] = 'Détails...';
$string['jumpto'] = 'Aller à';
$string['modulename'] = 'Présence';
$string['modulenameplural'] = 'Présences';
$string['months'] = 'Mois';
$string['myvariables'] = 'Mes Variables';
$string['newdate'] = 'Nouvelle date';
$string['newduration'] = 'Nouvelle durée';
$string['noattforuser'] = 'Aucun enregistrement de présence pour l\'utilisateur';
$string['nodescription'] = 'Session régulière de cours';
$string['noguest'] = 'Les invités ne peuvent voir les présences';
$string['nogroups'] = 'Vous ne pouvez pas ajouter de sessions de groupes. Il n\'y a pas de groupes définis dans ce cours.';
$string['noofdaysabsent'] = 'Nombre de jours noté comme absent';
$string['noofdaysexcused'] = 'Nombre de jours noté comme excusé';
$string['noofdayslate'] = 'Nombre de jours noté comme en retard';
$string['noofdayspresent'] = 'Nombre de jours noté comme présent';
$string['nosessiondayselected'] = 'Pas de jour de session sélectionné';
$string['nosessionexists'] = 'Aucune session n\'existe pour ce cours';
$string['nosessionsselected'] = 'Pas de session sélectionnée';
$string['notfound'] = 'Aucune activité Présence dans ce cours !';
$string['olddate'] = 'Ancienne date';
$string['period'] = 'Fréquence';
$string['pluginname'] = 'Présence';
$string['pluginadministration'] = 'Administration Présence';
$string['remarks'] = 'Remarques';
$string['report'] = 'Rapport';
$string['resetdescription'] = 'Rappelez-vous que la suppression des données sur la fréquentation va effacer ces informations de base de données. Vous pouvez simplement cacher les anciennes sessions en changeant la date de début du cours !';
$string['resetstatuses'] = 'Restaurer les statuts par défaut';
$string['restoredefaults'] = 'Restaurer les valeurs par défaut';
$string['save'] = 'Enregistrer les présences';
$string['session'] = 'Session';
$string['session_help'] = 'Session';
$string['sessionadded'] = 'Session ajoutée avec succès';
$string['sessionalreadyexists'] = 'La session existe déjà pour cette date';
$string['sessiondate'] = 'Date de session';
$string['sessiondays'] = 'Jours de session';
$string['sessiondeleted'] = 'Session supprimée avec succès';
$string['sessionenddate'] = 'Date de fin de session';
$string['sessionexist'] = 'Session non ajoutée (existe déjà)!';
$string['sessions'] = 'Sessions';
$string['sessionscompleted'] = 'Sessions réalisées';
$string['sessionsids'] = 'ID des sessions : ';
$string['sessionsgenerated'] = 'Sessions générées avec succès';
$string['sessionsnotfound'] = 'Il n\'y a pas de sessions dans la période sélectionnée';
$string['sessionstartdate'] = 'Date de début de session';
$string['sessiontype'] = 'Type de session';
$string['sessiontype_help'] = 'Il existe deux types de sessions : communes et de groupes. La possibilité d\'ajouter des différents types dépend de l\'activation du mode de groupe.

* En mode "Aucun groupe" vous pouvez seulement ajouter des sessions communes.
* En mode "Groupes visibles" vous pouvez ajouter des sessions communes et des sessions de groupes.
* En mode "Groupes séparés" vous pouvez seulement ajouter des sessions de groupes.
';
$string['sessiontypeshort'] = 'Type';
$string['sessionupdated'] = 'Session enregistrée avec succès';
$string['setallstatusesto'] = 'Mettre le statut de tous les utilisateurs sur «{$a}»';
$string['settings'] = 'Paramètres';
$string['showdefaults'] = 'Afficher les valeurs par défaut';
$string['showduration'] = 'Afficher la durée';
$string['sortedgrid'] = 'Tri en grille';
$string['sortedlist'] = 'Tri en liste';
$string['startofperiod'] = 'Début de la période';
$string['status'] = 'Statut';
$string['statuses'] = 'Statuts';
$string['statusdeleted'] = 'Statut supprimé';
$string['strftimedm'] = '%d.%m';
$string['strftimedmy'] = '%d.%m.%Y';
$string['strftimedmyhm'] = '%d.%m.%Y %H.%M';
$string['strftimedmyw'] = '%d.%m.%y&nbsp;(%a)';
$string['strftimehm'] = '%H:%M';
$string['strftimeshortdate'] = '%d.%m.%Y';
$string['studentid'] = 'ID Etudiant';
$string['takeattendance'] = 'Prendre les présences';
$string['thiscourse'] = 'Ce cours';
$string['update'] = 'Enregistrer';
$string['variable'] = 'Variable';
$string['variablesupdated'] = 'Variables mises à jour';
$string['versionforprinting'] = 'Version pour impression';
$string['viewmode'] = 'Mode d\'affichage';
$string['week'] = 'Ssemaine';
$string['weeks'] = 'Semaines';
$string['youcantdo'] = 'Vous ne pouvez rien faire';
?>