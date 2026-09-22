# Testanleitung Version 12.8.3

## Bestehende lokale Installation aktualisieren

1. MySQL/MariaDB muss in XAMPP grün laufen.
2. Datenbank und den bisherigen Websiteordner sichern.
3. Die Dateien aus Version 12.8.3 über die bisherigen Website-Dateien kopieren.

## Benutzer- und Rechteverwaltung

1. Mit dem bisherigen Admin anmelden. **Benutzer & Rechte** muss im Menü sichtbar sein und der Zugang muss als Hauptadministrator angezeigt werden.
2. Einen neuen Bereichsbenutzer mit mindestens zehn Zeichen langem Passwort anlegen.
3. Für **Hotelverwaltung** nur „Ansehen“, für **Reiseanfragen** „Ansehen“ und „Bearbeiten“ vergeben; alle anderen Bereiche abwählen.
4. Als neuer Benutzer anmelden. Im Menü dürfen nur Übersicht, Reiseanfragen und Hotelverwaltung erscheinen.
5. Hotelverwaltung öffnen. Die Nur-Lese-Meldung muss sichtbar sein; POST-Formulare müssen deaktiviert sein. Ein direkter POST-Versuch muss serverseitig abgewiesen werden.
6. Reiseanfrage öffnen, Status ändern und speichern. Das muss mit dem Bearbeitungsrecht funktionieren.
7. Eine nicht erlaubte URL wie `/admin/settings.php` direkt öffnen. Die Seite muss den Zugriff verweigern und zur Übersicht zurückführen.
8. Als Hauptadministrator das Hotelrecht auf „Bearbeiten“ erweitern und erneut testen.
9. Den Bereichsbenutzer deaktivieren. Eine erneute Anmeldung muss unmöglich sein; eine laufende Sitzung muss beim nächsten Aufruf beendet werden.
10. Prüfen, dass der eigene beziehungsweise letzte aktive Hauptadministrator weder deaktiviert noch herabgestuft werden kann.

## Hotelverwaltung und Zimmerkategorien

1. **Admin → Hotelverwaltung** öffnen. Alle bisherigen Unterkünfte müssen dort erscheinen.
2. Ein neues Hotel anlegen und speichern. Es muss ohne „Item not found“ in der Hotelliste erscheinen.
3. Das gerade angelegte Hotel muss als erster Eintrag oben in der Hotelliste stehen.
4. Das Hotel nacheinander über seinen Namen, englischen Namen, Ort, Region, Kategorie und seine ID suchen. Jeder passende Suchbegriff muss den Eintrag finden.
5. Eine Mehrwortsuche wie „Sigiriya Boutique“ testen und zusätzlich Land oder Status auswählen. Alle Filter müssen gemeinsam wirken.
6. **Zurücksetzen** anklicken. Suchtext, Land und Status müssen entfernt und alle Hotels wieder angezeigt werden.
7. Das neue Hotel öffnen und **Zimmer & Leistungen** wählen. Eine aktive Zimmerkategorie „Doppelzimmer / Double room“ muss automatisch vorhanden sein.
8. Eine weitere Zimmerkategorie mit deutschem/englischem Namen, Bild, Belegung, Basispreis und Bestand anlegen.
9. Einen Saisonpreis mit Von-/Bis-Datum, Mindestnächten und abweichendem Zimmerpreis speichern.
10. Mindestens eine Sehenswürdigkeit, Aktivität oder Zusatzleistung dem Hotel zuordnen.
11. Im Rundreiseplaner das Hotel wählen. Zimmerkategorie, Zimmerzahl und Verpflegung müssen einzeln wählbar sein.
12. Ein Startdatum innerhalb des Saisonzeitraums wählen. Die Schätzung muss den Saisonpreis pro Zimmer und Nacht verwenden.
13. Strand-, Ayurveda- oder Malediven-Planer öffnen. Zimmerkategorie wählen und prüfen, dass nur die zugeordneten Leistungen angeboten werden; ohne Zuordnung bleiben alle Leistungen des Ortes verfügbar.
14. Anfrage absenden. Zimmerkategorie, Preis, Verpflegung und Leistungen müssen im Admin und in der Druckansicht erscheinen.
15. Ein unbenutztes Testhotel löschen. Ein bereits in einer Anfrage verwendetes Hotel darf dagegen nur archiviert werden.

## Katalogtypen und Klassifizierungen

1. Unter **Admin → Kategorien** prüfen, dass Unterkunft, Sehenswürdigkeit, Aktivitätszentrum, Restaurant, Gewürzgarten, Shop und Zusatzleistung vorhanden sind.
2. Eine neue Unterkunft als Boutique-Hotel anlegen, Sterne und Verkaufssegment festlegen und Ausstattung in Deutsch und Englisch ergänzen.
3. Ein Restaurant, einen Gewürzgarten und ein Aktivitätszentrum anlegen und dem gleichen Reiseziel zuordnen.
4. Eine kostenlose Leistung sowie eine Leistung mit **Preis auf Anfrage** speichern.
5. Reiseplaner und Einzelhotel-Planer öffnen. Klassifizierung und Sterne müssen angezeigt werden; kostenlose und angefragte Preise dürfen die Schätzung nicht erhöhen.
6. Gewählte Angebote absenden und prüfen, dass sie in der gespeicherten Druckansicht unter dem richtigen Routenstopp erscheinen.

## Mobile Darstellung

1. Browserbreite auf 360–430 px stellen und den Reiseplaner öffnen.
2. Ortsliste, Nächte, Hotels, Kategorien und Anfrage in den drei mobilen Schritten bedienen.
3. Texte müssen ohne Zoomen lesbar und Schaltflächen bequem antippbar sein; es darf kein horizontales Scrollen geben.
4. Google-Karte über **Karte anzeigen** öffnen und wieder schließen.
5. Eine gespeicherte Druckansicht auf dem Smartphone öffnen. Reisedaten müssen zweispaltig und Routenstopps einspaltig in den Bildschirm passen.
6. Strand-, Ayurveda- und Malediven-Planer öffnen. Hotel- und Leistungs-Karten müssen einspaltig und mit größerer Schrift erscheinen.
7. **Reiseideen** auf einem Smartphone öffnen. Kategorien, Bildbeschriftung, Beschreibung, Orts-Chips, Nächte, Preis und Schaltfläche müssen ohne Zoomen gut lesbar sein.
8. Eine Reiseidee öffnen. Einleitung, Reisedaten, Tagesetappen, Leistungen, Preise und Anpassungs-Schaltfläche müssen mindestens in normaler Smartphone-Leseschrift erscheinen.
9. Die Seite danach ohne manuelles Löschen des Browser-Caches neu laden. Die CSS-Adresse muss einen `?v=`-Wert enthalten und die neue Schriftgröße sofort erscheinen.
10. Reiseideen am Desktop prüfen: Beschreibung, Orts-Chips, Nächte, Preise und Aktionsschaltflächen müssen deutlich größer als in Version 12.5.1 sein.
11. Unter **Reiseideen → Sport & Abenteuer** die neue Knuckles-Wanderreise öffnen. Distanz, Auf-/Abstieg, Höhenlage, Bewegungszeit, Gesamtzeit, Start/Ziel, Führerhinweis und Wikiloc-Referenz müssen sichtbar sein.
12. Die Reise drucken bzw. als PDF öffnen. Das Wanderprofil und die ausgewählte Aktivität müssen enthalten sein.
13. Unter **Admin → Fertige Reisen** die Knuckles-Wanderreise bearbeiten, einen Wert ändern und speichern. Die öffentliche Detailseite muss den geänderten Wert anzeigen.

## Flughafen und wiederholte Orte

1. Bandaranaike International Airport zur Route hinzufügen. Der Stopp muss mit 0 Nächten beginnen.
2. Negombo oder Colombo hinzufügen.
3. Beim bereits gewählten Ort **Nochmals am Routenende** anklicken. Ein zweiter, unabhängiger Stopp muss erscheinen.
4. Alternativ im Suchfeld nach dem bereits gewählten Ort suchen und **Bereits gewählt · nochmals hinzufügen** anklicken.
5. Für den ersten oder letzten Stopp 0 Nächte wählen und die Anfrage absenden.
6. Prüfen, dass beide Vorkommen in Google Maps, Zusammenfassung, gespeicherter Anfrage und Druckansicht in der richtigen Reihenfolge erscheinen.

## Farbcodierung der Route

1. Einen Ort einmal hinzufügen: Der ausgewählte Routenstopp muss hellgrün erscheinen.
2. Denselben Ort ein zweites Mal hinzufügen: Jeder Stopp dieses Ortes mit mindestens 1 Nacht muss kräftiger grün erscheinen.
3. Einen der beiden Stopps auf 0 Nächte setzen: Dieser einzelne Stopp muss orange erscheinen; der andere bleibt grün.
4. Den zweiten Stopp wieder auf mindestens 1 Nacht erhöhen: Beide Vorkommen müssen wieder grün erscheinen.
5. Einen der beiden Stopps entfernen: Der verbleibende Stopp muss wieder hellgrün erscheinen.

## Fahrzeugbilder

1. Unter **Admin → Fahrzeugflotte** ein Fahrzeug öffnen, ein JPG/PNG/WEBP hochladen und speichern.
2. Im Reiseplaner eine passende Gruppengröße wählen.
3. Prüfen, dass das Fahrzeugbild in der Zusammenfassung erscheint.
4. Anfrage absenden und die Druckansicht öffnen. Das Bild und der bei Einreichung gespeicherte Fahrzeugname müssen angezeigt werden.

## Reiseleiter

1. Im Footer **Reiseleiter registrieren** öffnen und eine Testbewerbung mit Profilfoto sowie einem PDF/JPG-Nachweis einreichen.
2. Unter **Admin → Reiseleiter** prüfen, dass der Status zunächst **Neu / Prüfung** ist.
3. Das private Dokument öffnen. Abgemeldet darf eine direkte Dokument-URL keinen Zugriff erlauben.
4. Status auf **Freigegeben** setzen, **Aktiv** markieren, Tageshonorar eintragen und speichern.
5. Im Reiseplaner den Guide auswählen. Prüfen, dass `Tageshonorar × Fahrzeugtage` zur Schätzung addiert wird.
6. Anfrage absenden und prüfen, dass Name, Sprachen, Foto und Kosten in Admin-Anfrage und Druckansicht erscheinen.

## Malediven-Starterbilder

1. `maldives.php` öffnen und prüfen, dass Atolle und Resorts ohne eigenes Bild ein Startermotiv zeigen.
2. In **Admin → Reisebausteine** ein echtes lizenziertes Resortbild hochladen. Das hochgeladene Bild muss das Startermotiv ersetzen und darf bei einem späteren Upgrade nicht überschrieben werden.
4. `config.local.php`, `storage/install.lock` und eigene Bilder im Ordner `uploads` behalten.
5. Die Website einmal öffnen. Die Migrationen ergänzen automatisch die Reisevorlagen, Einzelhotel-Bereiche, Malediven-Resorts sowie wiederholbare Routenstopps mit 0 Nächten.
6. Danach den Browser mit `Ctrl + F5` vollständig aktualisieren.

## Kundenregistrierung und Portal prüfen

1. Im öffentlichen Menü **Kundenkonto** öffnen und ein neues Konto registrieren.
2. Mit demselben Konto anmelden und Name, Telefon sowie Sprache im Profil ändern.
3. Eine neue Rundreise oder einen Hotelaufenthalt absenden.
4. Die Anfrage muss sofort im Kundenkonto erscheinen.
5. Die Anfrage und die private Druckansicht dürfen nur für dieses Kundenkonto oder einen Administrator erreichbar sein.
6. Abmelden und den direkten Dokument-Link erneut öffnen. Der Zugriff muss verweigert werden.
7. Im Admin unter **Kundenkonten** den Kunden öffnen, den Zugang sperren und prüfen, dass die Anmeldung nicht mehr möglich ist.

## Buchungsmanagement und Zahlungen prüfen

1. Im Admin eine Kundenanfrage öffnen und **Als Buchung übernehmen** wählen.
2. Es muss eine eindeutige Referenz mit `BK-` entstehen.
3. Reisebeginn, Reiseende, Gesamtbetrag, Anzahlung und Zahlungsziel bearbeiten.
4. Eine Teilzahlung mit Zahlungsart und Transaktionsreferenz erfassen.
5. Der Status muss **Teilbezahlt** anzeigen und der Restbetrag muss stimmen.
6. Eine zweite Zahlung über den Restbetrag erfassen. Der Status muss **Bezahlt** anzeigen.
7. Im Kundenkonto müssen Buchung, Zahlungsverlauf und Restbetrag mit dem Admin übereinstimmen.

## B2B-Agenturen prüfen

1. Unter **Admin → B2B-Agenturen** eine Agentur mit Code, Kontakt, Login-Passwort und Provision anlegen.
2. Mit dem Agenturzugang über das öffentliche Kunden-/Agentur-Login anmelden.
3. Für einen Agenturkunden eine Reiseanfrage absenden.
4. Im Agentur-Portal darf nur diese Agentur ihre Kundenanfrage sehen.
5. Im Admin muss die Anfrage mit der Agentur gekennzeichnet sein.
6. Die Anfrage in eine Buchung umwandeln. Die aktuelle Provision muss als Buchungs-Snapshot übernommen werden.
7. Danach die Agenturprovision ändern. Die bereits erstellte Buchung muss ihren ursprünglichen Prozentsatz behalten.

## Globale Preisanpassung prüfen

1. Unter **Admin → Einstellungen** die globale Preisanpassung auf `10 %` setzen.
2. Einen Basispreis von `100 €` im Katalog kontrollieren; der Basispreis muss dort unverändert bleiben.
3. Im Kundenplaner muss derselbe Zimmer- oder Leistungspreis als `110 €` erscheinen.
4. Ein Angebot mit einem Basis-Zwischenergebnis von `1.000 €` muss als Kundenpreis `1.100 €` ergeben.
5. Eine Anfrage absenden und anschließend die globale Preisanpassung auf `15 %` ändern.
6. Die alte Anfrage und ihre Druckansicht müssen weiterhin den gespeicherten 10-%-Preis zeigen.
7. Eine neue Anfrage muss mit 15 % berechnet werden.
8. Im Admin der gespeicherten Anfrage müssen der verwendete Prozentsatz und der enthaltene Anpassungsbetrag sichtbar sein.

## Neues Design und mobile Bedienung prüfen

1. Die Startseite öffnen. Zunächst dürfen nur acht empfohlene Reiseziele sichtbar sein.
2. Suche sowie die Filter Kultur, Natur & Safari, Strände, Ayurveda und Alle 32 Orte testen.
3. Unter 760 px Browserbreite den Reiseplaner öffnen.
4. Die Ortsauswahl muss als bildschirmfüllende, durchsuchbare Liste erscheinen.
5. Unten müssen die drei festen Schritte Route, Aufenthalt und Preis sichtbar sein.
6. Einen Ort wählen; der Planer muss automatisch zu Hotel & Erlebnisse wechseln.
7. Unter Admin → Einstellungen Firmenangaben und einen Social-Media-Link ändern. Der Footer muss die Änderung anzeigen; leere Social-Media-Felder bleiben unsichtbar.

## Fertige Reisen prüfen

1. **Reiseideen** im öffentlichen Menü öffnen. Zehn veröffentlichte Starterreisen müssen erscheinen.
2. Kategorien filtern und eine Reise öffnen. Route, Gesamtnächte, Richtpreis, enthaltene Leistungen und Saisonpreise müssen zweisprachig sein.
3. Die Druckfunktion testen. Die Reisebeschreibung, alle Stationen sowie vorausgewählte Hotels und Leistungen müssen sichtbar bleiben.
4. Bei eingerichtetem Google-Schlüssel muss die Straßenroute in der Reihenfolge der Vorlage erscheinen.
5. **Diese Reise anpassen** öffnen. Alle Stopps, Nächte, das vorausgewählte Hotel und die Leistungen müssen in den normalen Planer übernommen werden.
6. Einen Stopp verändern und die Anfrage absenden. Die ursprüngliche öffentliche Vorlage darf sich dadurch nicht verändern.
7. Unter **Admin → Fertige Reisen** eine Vorlage duplizieren. Die Kopie muss als unveröffentlichter Entwurf entstehen.
8. In der Kopie Stationen verschieben, eine Station mit 0 Nächten anlegen, Hotel und Leistungen auswählen, eine Saisonpreiszeile ergänzen und veröffentlichen.
9. Die öffentliche Vorschau muss genau diese neue Reihenfolge und Auswahl zeigen.

## Katalog prüfen

Unter **Admin → Reisebausteine** muss jedes Reiseziel mindestens Folgendes anzeigen:

- 2 Unterkünfte
- 2 Sehenswürdigkeiten
- 2 Aktivitäten
- 1 Shop

Die Namen mit „Beispielhotel“ oder „Beispielinhalt“ sind Testeinträge. Sie können vollständig bearbeitet oder ersetzt werden.

## Zimmerpreis prüfen

1. Den Reiseplaner direkt öffnen. Es darf kein Ort gewählt sein und die Schätzung muss `0 €` betragen.
2. Zwei Reisende auswählen.
3. Ein Reiseziel und ein Hotel wählen.
4. Die Zimmerzahl muss automatisch `1 Zimmer` anzeigen.
5. Drei Nächte wählen.
6. Der Hotelanteil der Berechnung ist `Zimmerpreis × 1 Zimmer × 3 Nächte`.
7. Auf drei Reisende wechseln. Die Mindestzahl muss automatisch auf `2 Zimmer` steigen.
8. Eine weitere Nacht hinzufügen. Die Schätzung muss um Folgendes steigen:

   `Zimmerpreis × Zimmerzahl + Verpflegungszuschlag × Reisende`

## Strandurlaub prüfen

1. Auf der Startseite **Strandurlaub** öffnen.
2. Ohne Hotel muss die Schätzung `0 €` anzeigen und die Anfrage-Schaltfläche deaktiviert sein.
3. Zwei Erwachsene, ein Doppelzimmer, eine Nacht und ein Hotel mit `100 €` Zimmerpreis wählen.
4. Ohne Verpflegung und Zusatzleistungen muss die Schätzung `100 €` betragen.
5. Einen dritten Erwachsenen hinzufügen. Das Zusatzbett kostet `30 €`; der Hotelanteil beträgt `130 €`.
6. Stattdessen zwei Erwachsene und ein Kind bis 12 wählen. Das Kinder-Zusatzbett kostet `15 €`; der Hotelanteil beträgt `115 €`.
7. Bei vier Gästen und einem Zimmer muss eine Belegungswarnung erscheinen. Zwei Zimmer müssen die Anfrage wieder ermöglichen.
8. Verpflegung auswählen. Erwachsene zahlen 100 %, Kinder 50 % des Verpflegungspreises pro Nacht.
9. Transfer, Ausflug oder Aktivität auswählen und die Anfrage absenden.
10. Die Druckansicht muss Hotel, Zimmer, Nächte, Zusatzbetten, Verpflegung und jede gewählte Zusatzleistung enthalten.

## Ayurveda-Aufenthalt prüfen

1. **Ayurveda-Hotels** öffnen und ein Hotel wählen.
2. Es dürfen nur die eingerichteten Aufenthalte `7`, `14`, `21` und `28` Nächte angeboten werden.
3. All-inclusive muss automatisch gewählt werden.
4. Das Ayurveda-Paket muss unter **Leistungen** separat auswählbar sein.
5. Das Paket darf nicht im Zimmerpreis oder All-inclusive-Preis versteckt sein.
6. Bei einem Preis pro Person/Nacht muss die Berechnung lauten:

   `Paketpreis × (Erwachsene + Kinder × 50 %) × Nächte`

7. Die gespeicherte Druckansicht muss das Ayurveda-Paket als eigene Position zeigen.

## Malediven-Aufenthalt prüfen

1. Im öffentlichen Menü **Malediven** öffnen. Es müssen 25 Resorts in 9 Atoll-Filtern angeboten werden.
2. Ein Resort öffnen. Richtpreis pro Doppelzimmer/Nacht, Belegung, Verpflegung und optionale Leistungen müssen sichtbar sein.
3. **Dieses Resort auswählen** öffnen. Genau dieses Resort muss im Planer bereits gewählt sein.
4. Zwei Erwachsene, ein Doppelzimmer und sieben Nächte einstellen. Der Zimmeranteil muss `Zimmerpreis × 1 Zimmer × 7 Nächte` sein.
5. Halb-, Vollpension oder All-inclusive wählen. Der Zuschlag muss pro Person und Nacht berechnet werden.
6. Den Transfer pro Buchung sowie eine Aktivität oder einen Ausflug pro Person auswählen.
7. Anfrage absenden. Die Referenz muss mit `MV-` beginnen.
8. Die Druckansicht muss Resort, Atoll, Zimmer, Nächte, Verpflegung, Transfer, Ausflug/Aktivität und alle Einzelpreise enthalten.
9. Die Anfrage im Admin öffnen; Typ **Malediven-Aufenthalt** und alle gewählten Leistungen müssen sichtbar sein.
10. Im normalen Sri-Lanka-Routenplaner dürfen keine Malediven-Atolle erscheinen.

## Admin-Einstellungen prüfen

Unter **Admin → Reisebausteine → Unterkunft bearbeiten** müssen verfügbar sein:

- Strandurlaub aktiv
- Ayurveda-Aufenthalt aktiv
- Malediven-Urlaub aktiv
- Standardgäste pro Zimmer
- Maximale Gästezahl mit Zusatzbett
- Zusatzbett Erwachsene in Prozent
- Kinderpreis in Prozent
- Kinder-Altersgrenze
- Ayurveda-Nächte

Bei Zusatzleistungen muss die Berechnung **pro Person**, **pro Person/Nacht** oder **pro Buchung** auswählbar sein.

## Reise und Druckansicht prüfen

1. Mehrere Reiseziele auswählen und mit den Pfeilen sortieren.
2. Nächte verändern. **Gesamtnächte** muss genau der `Summe der gewählten Nächte` entsprechen. Bei einer Nacht muss `1` stehen.
3. Anfrage absenden.
4. In der gespeicherten Reise müssen Hotel, Zimmerzahl, Zimmerpreis pro Nacht, Verpflegung und alle Leistungen erscheinen.
5. Google-Route öffnen und die Reise als PDF drucken.

## Rückkehr zum gleichen Ort und 0 Nächte prüfen

1. Negombo als ersten Ort wählen.
2. Beim gewählten Negombo-Stopp auf das kleine `＋` neben den Reihenfolgepfeilen klicken.
3. Negombo muss nun als erster und als letzter Routenstopp erscheinen.
4. Der zweite Negombo-Stopp muss automatisch `0 Nächte` anzeigen.
5. Für beide Negombo-Stopps müssen Hotel und Leistungen unabhängig auswählbar sein.
6. Beim ersten Stopp eine Unterkunft wählen; sie darf beim letzten Stopp nicht automatisch gewählt werden.
7. Die Google-Route muss in Negombo beginnen und wieder in Negombo enden.
8. Die Gesamtnächte dürfen den 0-Nächte-Stopp nicht erhöhen.
9. Anfrage speichern. Adminansicht und Druckansicht müssen beide Negombo-Stopps in der richtigen Reihenfolge anzeigen.

## Alle Leistungen in der Druckansicht prüfen

1. Für mindestens ein Reiseziel ein Hotel mit Verpflegung auswählen.
2. Zusätzlich eine Sehenswürdigkeit, eine Aktivität und einen Shop auswählen.
3. **Route anzeigen & drucken** öffnen. Alle vier Kategorien müssen unter dem richtigen Reiseziel erscheinen.
4. Bei der Unterkunft müssen Zimmerzahl, Verpflegung und Zimmerpreis/Nacht erscheinen.
5. Bei Sehenswürdigkeit, Aktivität und Shop muss der Preis pro Person oder **Inklusive** erscheinen.
6. Anfrage absenden und anschließend die gespeicherte Druckansicht öffnen. Dieselben ausgewählten Leistungen müssen dort ebenfalls vollständig erscheinen.

## Fahrzeugempfehlung prüfen

Unter **Admin → Fahrzeugflotte** müssen Auto, Mini Van, Van, Mini Bus und Bus editierbar sein.

- 1–3 Reisende → Auto
- 4 Reisende → Mini Van
- 5–7 Reisende → Van
- 8–20 Reisende → Mini Bus
- 21–50 Reisende → Bus

Im Planer muss automatisch das kleinste passende aktive Fahrzeug gewählt werden. Ein größeres passendes Fahrzeug kann manuell gewählt werden. Das Fahrzeug muss nach dem Absenden im Adminbereich sowie in der Druckansicht erscheinen.

## Fahrzeugpreise prüfen

1. Unter **Admin → Fahrzeugflotte** beim Auto einen Tagespreis mit Fahrer, einen Tagespreis ohne Fahrer und `0,30 €` pro km eintragen.
2. Beim Auto bis 3 Reisende muss **Ohne Fahrer erlauben** aktiviert sein.
3. Bei allen größeren Fahrzeugen darf im Planer nur **Mit Fahrer** angeboten werden.
4. Eine Reise mit 3 Nächten ergibt 4 Fahrzeugtage.
5. Mit Fahrer lautet die Berechnung:

   `Tagespreis mit Fahrer × Fahrzeugtage + Fahrstrecke × Kilometerpreis`

6. Ohne Fahrer lautet die Berechnung:

   `Tagespreis ohne Fahrer × Fahrzeugtage`

7. Die Fahrstrecke muss von Google Maps übernommen oder manuell eintragbar sein.
8. Fahrzeugoption, Tage, Tagespreis, Kilometerpreis und Fahrzeugkosten müssen im Adminbereich und in beiden Druckansichten erscheinen.
