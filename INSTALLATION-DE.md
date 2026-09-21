# Lokale Installation mit XAMPP

## Voraussetzungen

- Windows mit XAMPP
- PHP 8.1 oder neuer
- Apache und MySQL gestartet

## Installation

1. Den Ordner sri-lanka-tailor-tours nach C:\xampp\htdocs\ kopieren.
2. Im XAMPP Control Panel Apache und MySQL starten.
3. Im Browser öffnen:

   http://localhost/sri-lanka-tailor-tours/install.php

4. Für eine normale neue XAMPP-Installation folgende Daten verwenden:

   - Datenbank-Host: 127.0.0.1
   - Port: 3306
   - Datenbankname: srilanka_tours
   - Benutzer: root
   - Passwort: leer lassen

5. Namen, Administrator-E-Mail und ein Passwort mit mindestens zehn Zeichen eingeben.
6. Auf Install website klicken.
7. Danach im Adminbereich anmelden:

   http://localhost/sri-lanka-tailor-tours/admin/login.php

Die Datenbank, Tabellen und ersten Beispielinhalte werden automatisch erstellt.

Version 12.5.2 verwendet **Sri Lanka Expert by Raonex GmbH** als Standardmarke. Firmenadresse, Telefon, E-Mail, Website und Social-Media-Links können danach unter **Admin → Einstellungen** bearbeitet werden. Nicht ausgefüllte Social-Media-Links werden im Footer nicht angezeigt.

## Aktualisierung auf Version 12.5.2

1. Datenbank sowie die Ordner `uploads` und `storage` sichern.
2. Die Dateien aus Version 12.5.2 über die bisherigen Programmdateien kopieren. Vorhandene `config.local.php`, Uploads und gespeicherte Dokumente nicht löschen.
3. Eine beliebige Seite öffnen. Die Migration `017_catalog_classifications_and_mobile.php` wird automatisch einmal ausgeführt. Sie ergänzt Klassifizierungen und neue Katalogfelder, ohne vorhandene Reisebausteine oder Preise zu ersetzen.
4. Unter **Admin → Fahrzeugflotte** Fahrzeugbilder ergänzen.
5. Unter **Admin → Reiseleiter** eingegangene Registrierungen prüfen. Ein Profil wird nur im Planer angezeigt, wenn der Status **Freigegeben** und die Checkbox **Aktiv** gesetzt sind.

Version 12.5.2 hängt automatisch den Änderungszeitpunkt an lokale CSS- und JavaScript-Dateien an. Dadurch lädt der Browser nach einem Update die neue Gestaltung und verwendet nicht weiterhin eine alte, zwischengespeicherte Datei.

Unter **Admin → Kategorien** können Unterkunftsarten und Unterkategorien für Sehenswürdigkeiten, Aktivitätszentren, Restaurants, Gewürzgärten, Shops und Zusatzleistungen jederzeit ergänzt, übersetzt, sortiert oder deaktiviert werden. Unter **Admin → Reisebausteine** besitzt jedes Angebot zusätzlich Klassifizierung, Sterne, Verkaufssegment, Ausstattung, Öffnungszeiten, Reservierungspflicht und interne Lieferantenfelder.

Der Webserver muss Schreibrechte für `uploads` und `storage/guide-documents` besitzen. Apache schützt den gesamten Ordner `storage` über die mitgelieferte `.htaccess`. Bei einem anderen Webserver muss der direkte Webzugriff auf `storage` ebenfalls ausdrücklich gesperrt werden.

Die Migration `014_customer_booking_agent_portal.php` ergänzt Kundenkonten, Buchungen, Zahlungen und B2B-Agenturen automatisch. Es werden dabei keine Testkunden oder Agenturkonten erstellt. Kunden registrieren sich öffentlich über **Kundenkonto**. B2B-Agenturen und deren erste Zugangsdaten werden ausschließlich unter **Admin → B2B-Agenturen** angelegt.

Unter **Admin → Reiseanfragen** kann eine geprüfte Anfrage mit **Als Buchung übernehmen** in eine Buchung umgewandelt werden. Danach werden Status, Termine, Gesamtbetrag, Anzahlung, Zahlungsziel und eingegangene Zahlungen unter **Admin → Buchungen** verwaltet.

Unter **Admin → Einstellungen → Globale Preisanpassung** kann jederzeit ein prozentualer Aufschlag zwischen 0 und 200 % eingetragen werden. Die Basispreise in Reisebausteinen, Reisevorlagen und Fahrzeugflotte bleiben unverändert. Der Kundenpreis wird für neue Anzeigen und Berechnungen automatisch erhöht. Bereits gespeicherte Anfragen behalten den damals verwendeten Prozentsatz und Preis.

## Malediven in Version 12.1

Die Migration `012_maldives_resorts.php` ergänzt bestehende Installationen automatisch um 9 Atoll-Regionen und 25 editierbare Resort-Starter. Die Malediven besitzen einen getrennten öffentlichen Katalog unter `maldives.php` und einen Einzelresort-Planer unter `stay.php?type=maldives`. Gewählte Zimmer, Verpflegung, Transfer, Ausflüge und Aktivitäten werden in der Anfrage gespeichert und erscheinen in der Druckansicht.

Die mitgelieferten Preise sind ausschließlich editierbare Richt- und Testwerte. Vor einer Veröffentlichung müssen Resortnamen, Beschreibungen, Verfügbarkeit, Bilder, Zimmerpreise, Verpflegungszuschläge und Transferpreise mit den aktuellen Verträgen geprüft werden. Malediven-Atolle werden nicht im Sri-Lanka-Straßenroutenplaner angeboten.

## Fertige, anpassbare Reisen

Version 12 ergänzt zehn editierbare Reisevorlagen. Sie erscheinen öffentlich unter **Reiseideen** und werden im Adminbereich unter **Fertige Reisen** verwaltet. Jede Vorlage besitzt zweisprachige Texte, eine geordnete Route, Nächte, vorausgewählte Hotels und Leistungen sowie optionale Saisonpreise. Vorlagen können dupliziert, als Entwurf gespeichert, hervorgehoben und veröffentlicht werden.

Mit **Diese Reise anpassen** wird eine unabhängige Kopie in den normalen Reiseplaner geladen. Der Kunde kann Ziele, Reihenfolge, Nächte, Hotel, Verpflegung, Sehenswürdigkeiten, Aktivitäten, Shops und Fahrzeug verändern. Die ursprüngliche Reisevorlage bleibt unverändert. Bei bestehenden Installationen erzeugt die Migration `011_ready_made_tours.php` die Tabellen und Starterreisen automatisch; eigene Inhalte werden nicht ersetzt.

## Google Maps und Fahrroute aktivieren

Die Website funktioniert auch ohne Google-Schlüssel. Für die interaktive Straßenroute mit Markern, Fahrstrecke und Fahrzeit:

1. In Google Cloud ein Projekt mit aktivierter Abrechnung verwenden.
2. **Maps JavaScript API** und **Routes API** aktivieren.
3. Einen Browser-API-Schlüssel erstellen und auf genau diese APIs beschränken.
4. Als Website-Einschränkung für den lokalen Test `http://localhost/*` eintragen. Später zusätzlich `https://ihre-domain.de/*` eintragen.
5. Im Adminbereich **Einstellungen** öffnen und den Schlüssel speichern.

Für Tests kann die Map ID `DEMO_MAP_ID` bleiben. Neue Reiseziele brauchen Breiten- und Längengrad; diese Werte werden beim Bearbeiten eines Reiseziels gepflegt. Bereits installierte Versionen führen das Datenbank-Upgrade beim nächsten Seitenaufruf automatisch aus.

Neue Orte können direkt beim Anlegen eines Reisebausteins über **+ Neuen Ort hinzufügen** erstellt werden. Nach dem Speichern öffnet sich das Baustein-Formular erneut und der neue Ort ist bereits ausgewählt.

Nach der Einrichtung des API-Schlüssels kann die Position eines Reiseziels direkt auf einer Google-Karte angeklickt oder per Marker verschoben werden. Der Reiseplaner zeigt die Straßenroute und bietet eine Druckansicht. Gespeicherte Anfragen besitzen zusätzlich eine professionelle Reiseverlauf-Seite zum Drucken oder Speichern als PDF.

## Hotels, Zimmerpreise, Verpflegung und Nächte

Der Hotelpreis wird als Doppelzimmerpreis pro Zimmer und Nacht berechnet:

`Zimmerpreis × Anzahl Zimmer × Nächte`

Bei ein bis zwei Reisenden wird automatisch mindestens ein Doppelzimmer gewählt, bei drei bis vier Reisenden mindestens zwei Zimmer usw. Der Kunde kann die Zimmerzahl erhöhen. Anschließend wählt er die für dieses Hotel verfügbare Verpflegung: nur Übernachtung, Frühstück, Halb-, Vollpension oder All-inclusive. Verpflegungszuschläge bleiben pro Person und Nacht.

Die Anzahl der Nächte wird im Reiseplaner direkt mit Plus und Minus zwischen 0 und 14 eingestellt. **0 Nächte** ist für Transit-, Ankunfts-, Abfahrts- und Tagesstopps vorgesehen. **Gesamtnächte** entspricht genau der Summe der gewählten Nächte; es wird kein zusätzlicher Übernachtungstag addiert. Hotel, Zimmerzahl, Zimmerpreis, Verpflegung, Nächte, Sehenswürdigkeiten, Aktivitäten und das empfohlene Fahrzeug erscheinen auch in der gespeicherten Druckansicht.

Version 11 erlaubt denselben Ort mehrfach in einer Route. Mit dem ＋ neben einem bereits gewählten Stopp wird der Ort erneut am Routenende eingefügt, zum Beispiel Negombo am Anfang und am Ende. Der zusätzliche Rückkehrstopp startet mit 0 Nächten und besitzt eine eigene Auswahl für Hotel, Verpflegung und Leistungen. Die Migration `009_repeat_route_stops_zero_nights.php` aktualisiert bestehende Installationen automatisch.

Auf Smartphones ist der Planer in drei übersichtliche Bereiche geteilt: **Route**, **Hotel & Erlebnisse** und **Preis & Anfrage**. Die Ortsauswahl öffnet als bildschirmfüllende, durchsuchbare Liste; über die feste Leiste am unteren Rand kann jederzeit zwischen den Schritten gewechselt werden.

Version 8 zeigt außerdem bereits in der direkten Routen-Druckansicht alle gewählten Unterkünfte, Verpflegungen, Sehenswürdigkeiten, Aktivitäten und Shops unter dem jeweiligen Reiseziel. Die gespeicherte Druckansicht enthält dieselben Leistungen mit Preisangaben.

Version 9 ergänzt die editierbaren Fahrzeugpreise. Autos bis 3 Reisende können mit Fahrer oder ohne Fahrer/Selbstfahrer angeboten werden; größere Fahrzeuge sind nur mit Fahrer möglich. Der Kilometerpreis mit Fahrer ist standardmäßig 0,30 € und kann für jedes Fahrzeug geändert werden. Tagespreise starten bei 0,00 € und müssen im Adminbereich eingetragen werden. Fahrzeugtage entsprechen Gesamtnächten plus einem Kalendertag.

Version 10 ergänzt zwei Einzelhotel-Bereiche: **Strandurlaub** und **Ayurveda-Aufenthalt**. Pro Anfrage ist genau ein Hotel möglich; Ausflüge, Aktivitäten, Shops, Transfers und Ayurveda-Pakete können als Zusatzleistungen ausgewählt werden. Die Migration `008_beach_and_ayurveda_stays.php` ergänzt die benötigten Felder automatisch und bewahrt vorhandene Reisebausteine. Beispiel-Flughafentransfers und Ayurveda-Pakete sind editierbare Testeinträge.

Version 7 ergänzt bei einer bestehenden Installation automatisch die editierbare Fahrzeugflotte und korrigiert gespeicherte Gesamtnächte. Eigene vorhandene Reisebausteine werden dabei nicht ersetzt.

## Fahrzeugflotte

Unter **Admin → Fahrzeugflotte** können Namen, Kapazitäten, Status und Sortierung verändert oder weitere Fahrzeuge hinzugefügt werden. Standardmäßig werden Auto bis 3, Mini Van bis 4, Van bis 7, Mini Bus bis 20 und Bus bis 50 Reisende angelegt. Der Planer empfiehlt das kleinste passende aktive Fahrzeug; dafür wird kein automatischer Fahrzeugpreis berechnet.

## Wichtige Seiten

- Website: http://localhost/sri-lanka-tailor-tours/
- Reiseplaner: http://localhost/sri-lanka-tailor-tours/plan.php
- Fertige Reisen: http://localhost/sri-lanka-tailor-tours/tours.php
- Malediven-Resorts: http://localhost/sri-lanka-tailor-tours/maldives.php
- Malediven-Planer: http://localhost/sri-lanka-tailor-tours/stay.php?type=maldives
- Administration: http://localhost/sri-lanka-tailor-tours/admin/
- Reisevorlagen: http://localhost/sri-lanka-tailor-tours/admin/tours.php
- Fahrzeugflotte: http://localhost/sri-lanka-tailor-tours/admin/vehicles.php
- Google-Maps-Einstellungen: http://localhost/sri-lanka-tailor-tours/admin/settings.php

## Späterer Upload zu Plesk

Für die Serverinstallation config.local.php und storage/install.lock nicht vom lokalen System übernehmen. Die Dateien ohne diese beiden lokalen Installationsdateien hochladen und install.php auf der endgültigen Domain öffnen. Dort die in Plesk angelegten MySQL-Zugangsdaten eintragen.
