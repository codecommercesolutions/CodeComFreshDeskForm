# 1.0.0
- First version of the freshdesk integration form for Shopware 6.5

# 2.0.0
- First version of the freshdesk integration form for Shopware 6.6

# 3.0.0
- First version of the freshdesk integration form for Shopware 6.7

# 3.0.1
- Added field visibility switches in plugin configuration (show/hide fields)
- Fix missing subject error by adding fallback to default config
- Fix invalid ticket type error by adding English to German mapping (e.g. Spare part -> Ersatzteil)
- Fix syntax error in FreshdeskService.php
- Updated database schema to make phone, subject, and type nullable in submissions table
- Added support for custom field type selection in administration
- Added multilingual support for de-DE, fr-FR, nl-NL, es-ES, de-CH, fr-CH
- Added success message snippet selection in plugin configuration using sw-snippet-field
- Added default subject field to CMS element configuration with fallback to global config
- Localized composer.json for better international support
- Added missing snippet files for French, Dutch, and Spanish locales