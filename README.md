# VG Wort Zählmarken

WordPress-Plugin zur Verwaltung von VG-Wort-Zählmarken (METIS): Bestellung über die offizielle REST-Schnittstelle, Zuweisung zu Beiträgen, automatischer Einbau des Zählpixels sowie Übermittlung der Meldung (Beteiligte, Rechteeinräumung, Text) an VG Wort.

## Funktionen

1. **Zählmarken bestellen** – direkt über die REST-Schnittstelle (`https://tom.vgwort.de/api/external/metis/rest/pixel/v1.0/order`) oder per CSV-Import aus dem T.O.M.-Meldeportal.
2. **Zuweisen** – jede Zählmarke wird genau einem Beitrag zugeordnet (Werkzeuge → VG Wort Marken-Pool bzw. Meta-Box im Editor).
3. **Zählen** – das unsichtbare 1×1-Pixel wird automatisch in den Inhalt eingebaut (inkl. Unterstützung für Bezahlschranken-Präfix „pw-" und Base64url-Kodierung für verlagsinterne Schlüssel).
4. **Melden** – Titel, Nettotext, Beteiligte (Autor/Übersetzer/Verlag) und die Rechteeinräumung werden direkt aus dem Beitrag heraus an `message/v1.0/newMessageRequest` übermittelt.

## Voraussetzungen

- WordPress ≥ 5.8, PHP ≥ 7.4
- Wahrnehmungsvertrag und Meldesystemregistrierung unter https://tom.vgwort.de
- Für die Schnittstellen-Nutzung: Benutzername/Passwort des T.O.M.-Kontos (HTTP Basic Auth über TLS)

## Installation

Das Plugin liegt im Ordner [`vgwort-zaehlmarken/`](vgwort-zaehlmarken/). Ordner als ZIP packen und über **Plugins → Installieren → Plugin hochladen** in WordPress einspielen, oder direkt nach `wp-content/plugins/` kopieren.

## Wichtige Hinweise

- Melde nur Texte mit mindestens 1.800 Zeichen, die sich nicht mehr wesentlich ändern.
- Zählmarken hinter Bezahlschranken dürfen erst nach dem Bezahlvorgang geladen werden.
- Die exakte JSON-Struktur der Meldungs-Schnittstelle sollte vor dem produktiven Einsatz gegen die [Swagger-Dokumentation](https://tom.vgwort.de/api/external/swagger-ui/index.html) verifiziert werden.

Weitere Details siehe [`vgwort-zaehlmarken/readme.txt`](vgwort-zaehlmarken/readme.txt) (WordPress-Plugin-Readme-Format).

## Weitere Dateien

- [`DOC_Verlagsmeldung_REST.pdf`](DOC_Verlagsmeldung_REST.pdf) – Integrationsbeschreibung METIS für Verlage
- [`cloudflare-cache-bypass-robots-sitemap.sh`](cloudflare-cache-bypass-robots-sitemap.sh) – Skript für Cloudflare-Cache-Bypass von robots.txt/sitemap

## Lizenz

GPLv2 or later
