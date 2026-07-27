# Translations

CiviVerify uses CiviCRM's standard PHP Gettext integration. English strings in
the source code are the fallback language. A translation belongs in
`l10n/<locale>/LC_MESSAGES/civiverify.po` and must be compiled to the adjacent
`civiverify.mo` file before deployment.

Use the CiviCRM `civistrings` utility to refresh the source catalogue after
changing user-facing strings:

```bash
civistrings -o l10n/civiverify.pot .
msgmerge --update l10n/de_DE/LC_MESSAGES/civiverify.po l10n/civiverify.pot
msgfmt --check --output-file=l10n/de_DE/LC_MESSAGES/civiverify.mo l10n/de_DE/LC_MESSAGES/civiverify.po
```

New locales copy `l10n/civiverify.pot` to their own `<locale>/LC_MESSAGES/`
directory, translate the `.po` file, and commit both `.po` and `.mo` files.
Use CiviCRM locale names such as `de_DE`, `fr_FR`, or `nl_NL`.
