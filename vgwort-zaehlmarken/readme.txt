=== VG Wort Zählmarken ===
Contributors: —
Tags: vg wort, metis, zählmarke, urheberrecht
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Verwaltet VG-Wort-Zählmarken (METIS): Bestellung über die offizielle REST-Schnittstelle,
Zuweisung zu Beiträgen, automatischer Einbau des Zählpixels sowie Übermittlung der
Meldung (Beteiligte, Rechteeinräumung, Text) an VG Wort.

== Beschreibung ==

Dieses Plugin bildet den kompletten METIS-Workflow für Verlage ab, wie in der
"Integrationsbeschreibung METIS für Verlage" (VG Wort / stepscon) beschrieben:

1. **Zählmarken bestellen** – direkt über die REST-Schnittstelle
   (`https://tom.vgwort.de/api/external/metis/rest/pixel/v1.0/order`) oder per
   CSV-Import aus dem T.O.M.-Meldeportal.
2. **Zuweisen** – jede Zählmarke wird genau einem Beitrag zugeordnet
   (Werkzeuge → VG Wort Marken-Pool bzw. Meta-Box im Editor).
3. **Zählen** – das unsichtbare 1×1-Pixel wird automatisch in den Inhalt
   eingebaut (inkl. Unterstützung für Bezahlschranken-Präfix „pw-“ und
   Base64url-Kodierung für verlagsinterne Schlüssel).
4. **Melden** – Titel, Nettotext, Beteiligte (Autor/Übersetzer/Verlag) und die
   Rechteeinräumung werden direkt aus dem Beitrag heraus an
   `message/v1.0/newMessageRequest` übermittelt.

= Voraussetzungen =

* Wahrnehmungsvertrag und Meldesystemregistrierung unter https://tom.vgwort.de
* Für die Schnittstellen-Nutzung: Benutzername/Passwort deines T.O.M.-Kontos
  (HTTP Basic Auth über TLS)

= Wichtige Hinweise =

* Melde nur Texte mit mindestens 1.800 Zeichen, die sich nicht mehr wesentlich ändern.
* Zählmarken hinter Bezahlschranken dürfen erst nach dem Bezahlvorgang geladen werden.
* Für Verlage mit Zugriff auf einen Testzugang: metis.support@vgwort.de.
* Die exakte JSON-Struktur der Meldungs-Schnittstelle sollte vor dem produktiven
  Einsatz gegen die Swagger-Dokumentation
  (https://tom.vgwort.de/api/external/swagger-ui/index.html) verifiziert werden.

== Changelog ==

= 1.0.0 =
* Erste Veröffentlichung.
