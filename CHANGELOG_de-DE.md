# 1.0.0
- Erste Version des Freshdesk-Integrationsformulars für Shopware 6.5

# 2.0.0
- Erste Version des Freshdesk-Integrationsformulars für Shopware 6.6

# 3.0.0
- Erste Version des Freshdesk-Integrationsformulars für Shopware 6.7

# 3.0.1
- Schalter für die Sichtbarkeit von Feldern in der Plugin-Konfiguration hinzugefügt (Felder ein-/ausblenden)
- Fehler bei fehlendem Betreff durch Hinzufügen eines Fallbacks zur Standardkonfiguration behoben
- Fehler bei ungültigem Tickettyp durch Hinzufügen einer Englisch-Deutsch-Zuordnung behoben (z. B. Spare part -> Ersatzteil)
- Syntaxfehler in FreshdeskService.php behoben
- Datenbank-Schema aktualisiert, um Telefon, Betreff und Typ in der Tabelle "submissions" optional (nullable) zu machen
- Unterstützung für die Auswahl benutzerdefinierter Feldtypen in der Administration hinzugefügt
- Mehrsprachige Unterstützung für de-DE, fr-FR, nl-NL, es-ES, de-CH, fr-CH hinzugefügt
- Auswahl des Snippets für die Erfolgsmeldung in der Plugin-Konfiguration über sw-snippet-field hinzugefügt
- Feld für Standardbetreff zur CMS-Element-Konfiguration mit Fallback auf globale Konfiguration hinzugefügt
- composer.json für bessere internationale Unterstützung lokalisiert
- Fehlende Textbaustein-Dateien für Französisch, Niederländisch und Spanisch hinzugefügt