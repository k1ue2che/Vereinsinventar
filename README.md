# Vereinsinventar

Eine kleine, eigenständige Nextcloud-App für das Inventar eines Vereins.
App-ID, Installationsordner und vorgeschlagener GitHub-Repository-Name: **`vereinsinventar`**.
Version: **0.1.0 (MVP)**. Lizenz: AGPL-3.0-or-later.

## Umfang

- Jede bestehende Nextcloud-Gruppe hat einen eigenen Inventarbereich.
- Mitglieder sehen ausschließlich die Bereiche ihrer eigenen Gruppen.
- Kategorien können beliebig verschachtelt angelegt und umbenannt werden.
- Gegenstände: Name (Pflicht), Anzahl (ganze Zahl, auch 0), Inventarnummer, Standort, Notiz.
- Gegenstände anlegen, bearbeiten, in eine andere Kategorie derselben Gruppe verschieben und nach Bestätigung löschen.
- Deutsche Oberfläche, serverseitig gerenderte Formulare, Tastaturbedienung und schmaler Bildschirm berücksichtigt.
- Keine Composer-Pakete, npm-Pakete, externen Dienste oder Build-Schritte.

Bewusst nicht enthalten: Ausleihe, Bilder, Anhänge, QR-Codes, Import/Export, Suche, individuelle Rollen oder gruppenübergreifendes Teilen. Kategorien werden im MVP nicht verschoben oder gelöscht. Damit bleiben Hierarchie und untergeordnete Einträge bei parallelen Änderungen stabil. Es gibt keine Papierkorb- oder Versionsfunktion; parallele Gegenstandsänderungen verwenden den zuletzt gespeicherten Stand.

## Name

**Vereinsinventar** beschreibt den vorgesehenen Einsatz und ist für deutschsprachige Vereine verständlich. Die generische Nextcloud-App [Inventory](https://github.com/raimund-schluessler/inventory) existiert bereits und verfolgt einen größeren Funktionsumfang. Alternativen wie „Gruppeninventar“ beschreiben das Berechtigungsmodell genauer, sind aber weniger auf den Verein ausgerichtet. Empfehlung für dieses MVP: `vereinsinventar` beibehalten.

Die öffentliche Recherche vom 12.10.2026 hat keinen offensichtlichen gleichnamigen Nextcloud-Treffer ergeben. Sie garantiert weder Markenfreiheit noch die Verfügbarkeit einer App-Store-ID. Ein GitHub-Repository-Name ist immer innerhalb des eigenen Kontos bzw. der eigenen Organisation eindeutig. Vor Veröffentlichung App-Store-ID separat prüfen und reservieren.

## Voraussetzungen und Kompatibilität

- Ziel: Nextcloud **34 und 35**, PHP **8.3 oder neuer**, gemäß den Anforderungen der jeweiligen Nextcloud-Version.
- Standardmäßige Nextcloud-PHP-Erweiterungen einschließlich `mbstring`.
- Eigene Nextcloud-Installation mit Zugriff auf `custom_apps` und `occ`.
- Die App speichert Daten in der Nextcloud-Datenbank, unabhängig von Dateien und Team Folders.

`info.xml` erlaubt Version 34–35. Das ist eine deklarierte Zielspanne, keine abgeschlossene Laufzeit-Zertifizierung. Öffentliche OCP-Schnittstellen und die Änderungen für 35 wurden geprüft; insbesondere verwendet die Migration keine Doctrine-Schema-Typen in Methodensignaturen. Lokal wurden Syntax und isolierte Rechte-/Servicetests mit PHP 8.2.12 ausgeführt. Eine GitHub-Actions-Matrix für echte Nextcloud-34/35-Installationen mit SQLite ist vorbereitet, wurde hier aber noch nicht ausgeführt. Andere Datenbanken und die tatsächliche Darstellung innerhalb Nextcloud müssen vor produktivem Einsatz geprüft werden.

Quellen: [Metadaten](https://docs.nextcloud.com/server/35/developer_manual/app_development/info.html), [Änderungen in Nextcloud 35](https://docs.nextcloud.com/server/35/developer_manual/release_notes/critical_changes.html), [Migration 34](https://github.com/nextcloud/server/blob/stable34/lib/public/Migration/SimpleMigrationStep.php), [Migration 35](https://github.com/nextcloud/server/blob/stable35/lib/public/Migration/SimpleMigrationStep.php).

## Installation

Beispiel für Linux, Nextcloud liegt in `/var/www/nextcloud`. Pfade und Webserver-Benutzer bei Bedarf anpassen.

1. Release-Archiv entpacken und den enthaltenen Ordner `vereinsinventar` nach `/var/www/nextcloud/custom_apps/` kopieren. Entscheidend: `custom_apps/vereinsinventar/appinfo/info.xml` muss vorhanden sein.
2. Leserechte für den Webserver sicherstellen:

   ```sh
   sudo chown -R www-data:www-data /var/www/nextcloud/custom_apps/vereinsinventar
   ```

3. App aktivieren (legt die Tabelle automatisch über die Migration an):

   ```sh
   sudo -u www-data php /var/www/nextcloud/occ app:enable vereinsinventar
   ```

4. In Nextcloud Gruppen erstellen bzw. vorhandene verwenden und Mitglieder zuweisen. Anschließend „Vereinsinventar“ in der Navigation öffnen.

Eine Installation aus einem Git-Checkout ist ebenfalls möglich: das Repository direkt als Ordner `custom_apps/vereinsinventar` ablegen. Es ist kein Build erforderlich. Das MVP ist nicht für den Nextcloud App Store signiert. Keine Versionsprüfung mit `--force` umgehen.

## Gruppenberechtigungen

| Person / Einstellung | Lesen | Schreiben |
| --- | --- | --- |
| Gruppenmitglied, Standard | Ja | Ja |
| Mitglied einer Nur-Lesen-Gruppe | Ja | Nein |
| Kein Gruppenmitglied | Nein | Nein |
| Nextcloud-Administrator ohne Mitgliedschaft | Nein | Nein |

Gruppen werden über die Nextcloud-Benutzerverwaltung verwaltet. Gruppennamen dienen der Anzeige; gespeichert wird die stabile Gruppen-ID. Jeder Zugriff prüft die aktuelle Mitgliedschaft. Auch direkt angefragte Objekt- und Kategorie-IDs müssen zur ausgewählten Gruppe gehören. Die Anwendung vergibt keine zusätzlichen Rechte an Gruppenadministratoren.

Einzelne Gruppen global auf Nur-Lesen setzen (JSON-Liste der **Gruppen-IDs**, nicht Anzeigenamen):

```sh
sudo -u www-data php /var/www/nextcloud/occ config:app:set vereinsinventar readonly_groups --value='["vorstand", "archiv"]'
```

Zurücksetzen:

```sh
sudo -u www-data php /var/www/nextcloud/occ config:app:delete vereinsinventar readonly_groups
```

Diese Einstellung gilt für alle Mitglieder der genannten Gruppe, auch Administratoren. Das MVP bietet keine Mischung aus Lesern und Bearbeitern innerhalb derselben Gruppe. Fehlerhaftes JSON sperrt Schreibzugriffe vorsichtshalber; Lesen bleibt erlaubt. Änderungen an einer Gruppen-ID erzeugen einen anderen Inventarbereich. Beim Löschen einer Gruppe bleiben deren Datensätze erhalten: dieselbe später neu angelegte Gruppen-ID würde sie wieder sichtbar machen. Gruppen-IDs daher nicht für andere Personenkreise wiederverwenden; eine bereinigende Gruppen-Löschfunktion ist nicht Bestandteil dieses MVP.

## Sicherheit und Daten

- Nextcloud-Anmeldung erforderlich; keine öffentlichen Routen.
- Schreibende Aktionen ausschließlich per POST mit Nextcloud-CSRF-Prüfung.
- Serverseitige Gruppenprüfung und gruppengebundene Datenbankabfragen.
- Ausgabe wird mit Nextcloud `p()` HTML-escaped; Notizen sind reiner Text.
- Parametrisierte Abfragen; Feldlängen und Anzahl serverseitig begrenzt.
- Kategorien können keinen neuen Elternknoten bekommen; damit entstehen über die App keine Zyklen.
- Normales Nextcloud-Backup einschließlich Datenbank sichert das Inventar. Vor Updates sichern.
- Das Deaktivieren der App ist kein Datenexport und keine Datenlöschung.

## Repository

```text
appinfo/                 Metadaten und Routen
lib/AppInfo/             Registrierung
lib/Controller/          Seiten und Formulare
lib/Db/                  Gruppengebundene Datenbankabfragen
lib/Migration/           Initiales Schema
lib/Service/             Rechte und Eingabeprüfung
templates/               Deutsche Oberfläche
css/ und img/            Darstellung und Navigationssymbol
tests/                   Isolierte Tests und Nextcloud-Integration
.github/workflows/       Syntax, Rechte, Nextcloud 34/35 mit SQLite
README.md
CHANGELOG.md
LICENSE
```

Das mitgelieferte lokale Git-Repository enthält drei nachvollziehbare Entwicklungsschritte: Datenmodell/Rechte, Oberfläche, Dokumentation/Tests. Es wurde kein GitHub-Repository angelegt und nichts hochgeladen. Für die Veröffentlichung ein leeres Repository `vereinsinventar` im eigenen GitHub-Konto anlegen und die lokale Historie dorthin pushen. Autor und Kontakt in `appinfo/info.xml` vor Veröffentlichung anpassen; anschließend tatsächliche Repository-, Fehlerbericht- und Homepage-URLs ergänzen. Im MVP stehen dort bewusst keine erfundenen Links.

## Prüfungen

Ohne Nextcloud (PHP mit mbstring):

```sh
php tests/run.php
php tests/template.php
find appinfo lib templates tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Die isolierten Tests verwenden kleine OCP-Doubles; sie prüfen Rechte und Validierung, ersetzen aber keine Prüfung gegen echte Nextcloud-Schnittstellen. Die CI installiert die App zusätzlich in offiziellen Docker-Images für 34 und 35 und führt `tests/integration.php` mit echter Datenbank und Gruppenverwaltung aus. Integration ausschließlich in einer separaten Testinstanz ausführen: dabei werden kurzzeitig Testbenutzer/-gruppen und die Nur-Lesen-Einstellung verändert.

Nach erfolgreicher CI manuell in beiden Versionen prüfen:

1. Zwei Gruppen und Benutzer mit getrennten bzw. überlappenden Mitgliedschaften anlegen.
2. Gruppenauswahl, Unterkategorien und Gegenstände anlegen; Gegenstand verschieben, bearbeiten und löschen.
3. Mitgliedschaft entziehen und direkte URLs erneut aufrufen: Zugriff muss gesperrt sein.
4. Nur-Lesen-Gruppe konfigurieren: Lesen möglich, POST-Schreibversuche verweigert.
5. POST ohne gültiges `requesttoken` muss durch Nextcloud abgewiesen werden.
6. HTML-Zeichen in Namen/Notizen müssen als Text erscheinen; schmale Ansicht und Tastaturbedienung prüfen.

Lizenz: [GNU AGPL Version 3 oder später](https://www.gnu.org/licenses/agpl-3.0.html).
