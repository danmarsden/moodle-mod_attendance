# mod_attendance (tilpasset versjon)

Denne repoen inneholder en tilpasset versjon av Moodle-pluginen `mod_attendance` med utvidet varslingslogikk for planlagt grunnlag (planlagte timer/økter).

For engelsk versjon av dokumentasjonen, se `README.md`.

## Hva som er gjort i denne versjonen

### 1) Nytt varslingsgrunnlag
- `Warning basis mode` støtter:
  - `Current sessions only` (opprinnelig adferd)
  - `Planned total sessions`
  - `Planned total hours`
- Nye aktivitetsfelter:
  - `Planned total sessions`
  - `Planned total hours`

### 2) Varselberegning for planlagte timer
- I `planned_hours` beregnes varselprosent som:
- `effective_percent = ((plannedtotalhours - absent_hours) / plannedtotalhours) * 100`
- For å støtte ulike poengskalaer beregnes fraværstimer proporsjonalt når økter har varighet:
- `absent_hours = taken_hours * (missing_points / max_points)`
- Fallback uten varighet: `1 point = 1 hour`.

### 3) Forbedringer i notify-task
- `mod_attendance\task\notify` bruker samme effektive prosent både for beslutning og `%percent%` i e-post.
- Kandidatutvalg for varsling bruker bare siste loggføring per student/økt (unngår dobbelttelling).
- Beskyttelse mot skjeve aggregater fra historiske rader i `attendance_warning_done`.
- Kun faktiske sendinger logges i `attendance_warning_done`.

### 4) Manuell kjøring av varseltask
- Lagt til side for manuell kjøring:
- `/mod/attendance/run_notify_task.php`
- Nyttig når `Run now` ikke er synlig i Scheduled tasks.
- Inneholder også funksjon for å slette historikk over sendte varsler (for retest).

## Kompatibilitet

- Standardoppsett (`Current sessions only`) er beholdt.
- Pluginen er bakoverkompatibel med eksisterende aktiviteter som ikke bruker planlagt basis.
- Ved oppgradering må Moodle kjøre vanlig plugin-upgrade (DB-felter for planlagt basis).

## Hvordan bruke pluginen (oppsett)

### 1) Aktiver varslinger globalt
1. Gå til plugininnstillinger for Attendance.
2. Sett `Enable warnings` til `Yes`.

### 2) Konfigurer aktivitet
I attendance-aktiviteten:
1. Sett `Warning basis mode`:
   - `Planned total hours` hvis du vil bruke total planlagt undervisningstid.
2. Sett `Planned total hours` (f.eks. `100`).
3. Lagre.

### 3) Opprett varselregel
I aktivitetens varslingsside:
1. `Warn if percentage falls under` (f.eks. `80`).
2. `Number of sessions taken before warning` (f.eks. `1`).
3. Slå på `Email user`.
4. Angi emne/innhold.
5. Lagre.

### 4) Registrer oppmøte
- Før oppmøte per økt som vanlig.
- Sørg for at økter som skal telle i varsling har `Include in absentee report`.

## Eksempel på beregning (planned total hours)

Scenario:
- `Planned total hours = 100`
- 5 økter á 10 timer (`taken_hours = 50`)
- Poengskala per økt 0-100 (f.eks. 100, 50, 100, 25, 0)

Beregning:
- `points = 275`, `max_points = 500`, `missing_points = 225`
- `absent_hours = 50 * (225/500) = 22.5`
- `effective_percent = (100 - 22.5) / 100 * 100 = 77.5`

Hvis terskel er 80%, skal varsel sendes (77.5 < 80).

## Drift i produksjon (automatisk sending)

Varsler sendes automatisk via scheduled task:
- `mod_attendance\task\notify`

Krav:
- Moodle cron kjører.
- Tasken er aktiv i `Site administration -> Server -> Scheduled tasks`.

Manuell fallback:
- Kjør: `/mod/attendance/run_notify_task.php`
- Bruk `Clear sent warnings` ved ny testsyklus.

## Feilsøking

Hvis det ikke sendes e-post:
1. Sjekk at `Enable warnings` er aktivert.
2. Sjekk at varselregel har `Email user` slått på.
3. Sjekk terskel og `Number of sessions taken before warning`.
4. Sjekk at øktene er markert for absentee report.
5. Sjekk cron/scheduled task-status.
6. Tøm cache etter kodeoppdatering.
7. Ved retest: slett sendte varsler via `run_notify_task.php`.

## Opprinnelig prosjekt

Attendance module vedlikeholdes av Dan Marsden: http://danmarsden.com

Tidligere utviklere:
- Dmitry Pupinin (Novosibirsk, Russia)
- Artem Andreev (Taganrog, Russia)

Offisiell dokumentasjon:
- https://docs.moodle.org/en/Attendance_activity
