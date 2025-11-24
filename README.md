# Hadi – jádro (modular backup)

Tato verze pluginu je strukturovaná pro GitHub:

- `hadi-core.php` – hlavní plugin soubor, který jen načte původní kód
- `includes/legacy-hadi-core.php` – původní monolitický plugin (beze změn logiky)

WordPress používá pouze header z `hadi-core.php`. Všechny funkce, hooky a logika zůstávají totožné s původním pluginem.
