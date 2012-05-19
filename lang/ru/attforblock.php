<?PHP // $Id: attforblock.php,v 1.1.2.4 2009/04/12 17:50:11 dlnsk Exp $ 
      // attforblock.php - created with Moodle 1.8.2+ (2007021520)


$string['Aacronym'] = 'Н';
$string['Afull'] = 'Не был';
$string['Eacronym'] = 'У';
$string['Efull'] = 'Уважительная причина';
$string['Lacronym'] = 'О';
$string['Lfull'] = 'Опоздал';
$string['Pacronym'] = 'П';
$string['Pfull'] = 'Присутствовал';
$string['acronym'] = 'Сокращ.';
$string['add'] = 'Добавить';
$string['addmultiplesessions'] = 'Добавить несколько занятий';
$string['addsession'] = 'Добавить занятие';
$string['all'] = 'Все';
$string['allcourses'] = 'Все курсы';
$string['allpast'] = 'Все прошедшие';
$string['attendancedata'] = 'Информация о посещаемости';
$string['attendanceforthecourse'] = 'Посещаемость для курса';
$string['attendancegrade'] = 'Оценка за посещаемость';
$string['attendancenotstarted'] = 'Пока нет отметок о посещаемости в данном курсе.';
$string['attendancepercent'] = 'Процент посещаемости';
$string['attendancereport'] = 'Отчет о посещаемости';
$string['attendancesuccess'] = 'Информация о присутствии успешно запомнена';
$string['attendanceupdated'] = 'Информация о присутствии успешно обновлена';
$string['attforblock:changepreferences'] = 'Изменение настроек';
$string['attforblock:changeattendances'] = 'Редактирование посещаемости';
$string['attforblock:export'] = 'Экспорт отчетов';
$string['attforblock:manageattendances'] = 'Управление посещаемостью';
$string['attforblock:takeattendances'] = 'Отметка посещаемости';
$string['attforblock:view'] = 'Просмотр посещаемости';
$string['attforblock:viewreports'] = 'Просмотр отчетов';
$string['attrecords'] = 'Отметок о посещаемости';
$string['calclose'] = 'Закрыть';
$string['calmonths'] = 'Январь,Февраль,Март,Апрель,Май,Июнь,Июль,Август,Сентябрь,Октябрь,Ноябрь,Декабрь';
$string['calshow'] = 'Выбрать дату';
$string['caltoday'] = 'Сегодня';
$string['calweekdays'] = 'Вс,Пн,Вт,Ср,Чт,Пт,Сб';
$string['changeattendance'] = 'Изменить посещаемость';
$string['changeduration'] = 'Изменить продолжительность';
$string['changesession'] = 'Изменить занятие';
$string['column'] = 'колонка';
$string['columns'] = 'колонок';
$string['commonsession'] = 'Общее';
$string['commonsessions'] = 'Общие';
$string['countofselected'] = 'Выбрано занятий';
$string['copyfrom'] = 'Копировать данные посещаемости из занятия';
$string['createmultiplesessions'] = 'Создать несколько занятий';
$string['createonesession'] = 'Создать одно занятие для курса';
$string['days'] = 'День';
$string['defaults'] = 'По умолчанию';
$string['delete'] = 'Удалить';
$string['deletesessions'] = 'Удалить все занятия';
$string['deletelogs'] = 'Удалить информацию о посещаемости';
$string['deleteselected'] = 'Удалить выбранные';
$string['deletesession'] = 'Удалить занятие';
$string['deletingsession'] = 'Удаление занятия из курса';
$string['deletingstatus'] = 'Удаление статуса из курса';
$string['description'] = 'Описание';
$string['display'] = 'Отображать';
$string['downloadexcel'] = 'Скачать в формате Excel';
$string['downloadooo'] = 'Скачать в формате OpenOffice';
$string['downloadtext'] = 'Скачать в текстовом формате';
$string['duration'] = 'Продолжительность';
$string['editsession'] = 'Редактировать занятие';
$string['endofperiod'] = 'Конец периода';
$string['enrolmentend'] = 'Подписка на курс заканчивается {$a}';
$string['enrolmentstart'] = 'Подписка на курс начинается с {$a}';
$string['enrolmentsuspended'] = 'Подписка на курс приостановлена';
$string['errorgroupsnotselected'] = 'Выберите одну или более групп';
$string['errorinaddingsession'] = 'Ошибка при добавлении занятия';
$string['erroringeneratingsessions'] = 'Ошибка при создании занятий';
$string['gradebookexplanation'] = 'Оценка в журнале оценок';
$string['gradebookexplanation_help'] = 'Ваша оценка в модуле «Посещаемость» рассчитывается как отношение количества баллов, полученных за посещаемость, к максимальному количеству баллов, которые вы могли получить за посещаемость к текущей дате. В журнале оценок ваша оценка основывается на проценте баллов, полученных за посещаемость, и максимальном количестве баллов, которые вы можете получить за посещаемость в журнале оценок. Таким образом, оценки, отображаемые в модуле «Посещаемость» и в журнале оценок могут различаться.
    
Например, если вы на текущий момент заработали 8 баллов из 10 (80% посещаемости) и максимальный балл за посещаемость в журнале оценок – 50, то в модуле «Посещаемость» отобразится оценка «8/10», а в журнале оценок «40/50». Вы еще не заработали 40 баллов, но на данный момент ваш процент посещаемости соответствует этим баллам. Ваши баллы в модуле «Посещаемость» никогда не могут уменьшаться, т.к. они зависят только от посещаемости на текущую дату. Но оценка в журнале оценок может увеличиваться и уменьшаться, в зависимости от вашей будущей успеваемости, т.к. эта оценка зависит от посещаемости во всем курсе.
';
$string['groupsession'] = 'Групповое';
$string['hiddensessions'] = 'Скрытых занятий';
$string['hiddensessions_help'] = '
Занятия скрываются, если их дата раньше даты начала курса. Чтобы отобразить эти занятия, измените дату начала курса.

Эту возможность можно использовать для скрытия занятий, вместо их удаления. Имейте в виду, что для подсчета оценки используются только видимые занятия.';
$string['identifyby'] = 'Идентифицировать студентов по';
$string['includeall'] = 'Выбрать все занятия';
$string['includenottaken'] = 'Включить не прошедшие занятия';
$string['indetail'] = 'Подробнее...';
$string['jumpto'] = 'Перейти к занятию ';
$string['modulename'] = 'Посещаемость';
$string['modulenameplural'] = 'Посещаемость';
$string['months'] = 'Месяц';
$string['myvariables'] = 'Мои переменные';
$string['newdate'] = 'Новая дата';
$string['newduration'] = 'New duration';
$string['noattforuser'] = 'Нет отметок посещаемости для этого пользователя';
$string['nodescription'] = 'Для этого занятия нет описания';
$string['noguest'] = 'Гость не может видеть информацию о посещаемости';
$string['nogroups'] = 'Вы не можете добавлять групповые занятия. В курсе не определено ни одной группы.';
$string['noofdaysabsent'] = 'Пропущено';
$string['noofdaysexcused'] = 'Пропущено по ув. причине';
$string['noofdayslate'] = 'Опозданий';
$string['noofdayspresent'] = 'Вы присутствовали';
$string['nosessiondayselected'] = 'Не выбран день занятия';
$string['nosessionexists'] = 'В данном курсе отсутствуют занятия. Сначала добавьте их';
$string['nosessionsselected'] = 'Не выбрано ни одного занятия';
$string['notfound'] = 'Элемент курса - \"Посещаемость\" не найден в данном курсе!';
$string['olddate'] = 'Старая дата';
$string['period'] = 'Периодичность';
$string['pluginname'] = 'Посещаемость';
$string['pluginadministration'] = 'Управление модулем «Посещаемость»';
$string['remarks'] = 'Заметка';
$string['report'] = 'Отчет';
$string['resetdescription'] = 'Внимаение! Чистка данных посещаемости удаляет данные из БД. Вы можете просто скрыть устаревшие занятия, изменив дату начала курса!';
$string['resetstatuses'] = 'Восстановить набор статусов по умолчанию';
$string['restoredefaults'] = 'Востановить значения по-умолчанию';
$string['save'] = 'Сохранить посещаемость';
$string['session'] = 'Занятие';
$string['sessionadded'] = 'Занятие успешно добавлено';
$string['sessionalreadyexists'] = 'В этот день занятие уже существует';
$string['sessiondate'] = 'Дата занятия';
$string['sessiondays'] = 'Дни занятий';
$string['sessiondeleted'] = 'Занятие успешно удалено';
$string['sessionenddate'] = 'Дата завершения занятий';
$string['sessionexist'] = 'Занятие не добавлено (уже существует)!';
$string['sessions'] = 'Занятия';
$string['sessionscompleted'] = 'Прошло занятий';
$string['sessionsids'] = 'Идентификаторы занятий: ';
$string['sessionsgenerated'] = 'Занятия успешно созданы';
$string['sessionsnotfound'] = 'В выбранный диапазон времени не попадает ни одно занятие';
$string['sessionstartdate'] = 'Дата начала занятий';
$string['sessiontype'] = 'Тип занятия';
$string['sessiontype_help'] = 'Существует 2 типа занятий: общие и групповые. Возможность добавлять события различных типов зависит от группового режима элемента курса.

* В групповом режиме "Нет групп" можно добавлять только общие события.
* В групповом режиме "Доступные группы" можно добавлять и общие и групповые события.
* В групповом режиме "Отдельные группы" можно добавлять только групповые события.
';
$string['sessiontypeshort'] = 'Тип';
$string['sessionupdated'] = 'Занятие успешно изменено';
$string['setallstatusesto'] = 'Установить статус для всех учащихся в «{$a}»';
$string['settings'] = 'Настройки';
$string['showdefaults'] = 'Показать значения по-умолчанию';
$string['sortedgrid'] = 'Таблица';
$string['sortedlist'] = 'Список';
$string['startofperiod'] = 'Начало периода';
$string['status'] = 'Статус';
$string['statuses'] = 'Статусы';
$string['statusdeleted'] = 'Статус удален';
$string['strftimedm'] = '%d.%m';
$string['strftimedmy'] = '%d.%m.%Y';
$string['strftimedmyhm'] = '%d.%m.%Y %H.%M';
$string['strftimedmyw'] = '%d.%m.%y&nbsp;(%a)';
$string['strftimehm'] = '%H:%M';
$string['strftimeshortdate'] = '%%d.%%m.%%Y';
$string['studentid'] = 'ID студента';
$string['takeattendance'] = 'Отметить посещаемость';
$string['thiscourse'] = 'Текущий курс';
$string['update'] = 'Обновить';
$string['variable'] = 'переменную';
$string['variablesupdated'] = 'Переменные успешно обновлены';
$string['versionforprinting'] = 'версия для печати';
$string['viewmode'] = 'Вид: ';
$string['week'] = 'неделя(и)';
$string['weeks'] = 'Неделя';
$string['youcantdo'] = 'Вы ничего не можете сделать';

?>
