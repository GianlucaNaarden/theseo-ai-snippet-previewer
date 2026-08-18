# TheSEO AI Snippet Previewer

Meet per pagina welke delen een taalmodel los kan citeren, toont de volledige berekening achter de score, en bouwt een kant-en-klare prompt. Gebouwd door [TheSEO](https://theseo.nl/) en hier open source beschikbaar onder GPL-2.0 of nieuwer.

Een taalmodel leest je pagina niet zoals een bezoeker dat doet. Het zoekt naar delen die op zichzelf staan en zonder de rest over te nemen zijn: een definitie in een zin, een genummerde lijst, een cijfer met een bron ernaast. Lopende tekst wordt samengevat en verdwijnt.

Deze plugin meet zes dingen op een pagina die bepalen of zulke delen bestaan, telt ze op tot een score van 100, en drukt de hele berekening op het scherm af. Elk punt komt uit iets dat geteld is. Er zijn geen verborgen constanten en geen bonuspunten, dus je kunt het totaal met de hand narekenen.

## Downloaden en installeren

* Zip via theseo.nl: [tools-en-plugins/ai-snippet-previewer](https://theseo.nl/tools-en-plugins/ai-snippet-previewer/)
* Of via de [releases](https://github.com/GianlucaNaarden/theseo-ai-snippet-previewer/releases) hier op GitHub

Na activeren: AI snippet preview in het beheermenu. Kies een pagina of bericht, vul eventueel de context in en druk op Run analysis.

Eisen: WordPress 6.2 of nieuwer, PHP 7.4 of nieuwer.

## De rekenmethode, volledig

Versie 2.0 van de methode. De zes maxima tellen op tot 100.

1. **Tekstlengte, 25 punten.** Aantal woorden in de gerenderde inhoud. 25 punten vanaf 800 woorden, evenredig daaronder.
2. **Tussenkoppen H2 en H3, 20 punten.** Nul punten zonder tussenkop, 10 punten bij een of twee, 20 punten vanaf drie.
3. **Lijsten, 15 punten.** Alle 15 punten vanaf de eerste `ul` of `ol` in de inhoud.
4. **Gestructureerde data JSON-LD, 20 punten.** Twintig punten als er een JSON-LD scriptblok aanwezig is. Dit wordt gemeten op de gepubliceerde pagina zoals een bezoeker die krijgt, dus JSON-LD die je SEO-plugin in de `head` zet telt mee. Kan die pagina niet opgehaald worden, dan valt de meting terug op alleen de inhoud en zegt de tabel dat erbij.
5. **Externe bronnen, 10 punten.** Vijf punten bij een link naar een ander domein, 10 punten vanaf twee. Links naar je eigen domein tellen hier niet mee.
6. **Interne links, 10 punten.** Vijf punten bij een of twee links binnen je eigen site, 10 punten vanaf drie.

Woorden worden geteld als reeksen letters en cijfers, UTF-8-bewust, dus Nederlandse woorden met accenten tellen als een woord. Links worden uit de gerenderde inhoud gelezen, waarbij ankers, mailto en tel worden genegeerd.

De score zegt hoe goed deze pagina in delen te citeren is. Meer zegt hij niet.

## Wat de plugin niet doet

* Hij verandert je inhoud, je metadata en je schema niet. Hij leest alleen.
* Hij voegt niets toe aan de voorkant. Alles gebeurt binnen wp-admin.
* Hij voorspelt geen posities, geen verkeer en niet of een AI-systeem je pagina echt gebruikt. Dat kan niemand van buitenaf meten.
* Hij bootst GPT, Perplexity en Gemini niet na. Versies voor 2.0.0 toonden drie blokken met die namen erboven die in werkelijkheid drie afkappingen van je eigen tekst waren op 45, 28 en 38 woorden. Dat is eruit. Wat er staat is wat eerlijk te tonen valt: letterlijke fragmenten, gemeten signalen en een prompt die je zelf door een echt model kunt halen.

## Wat er wordt verstuurd en bewaard

Zonder API-sleutel verlaat er niets je server, op een verzoek na van je eigen site naar je eigen pagina voor de controle op gestructureerde data.

Met een API-sleutel stuurt de plugin naar OpenAI: de paginatitel, hoogstens achtduizend tekens van de paginatekst, de context die je hebt ingevuld en de gemeten getallen. Verder niets. Het verkeer wordt afgerekend op je eigen OpenAI-account.

De plugin bewaart de instellingen in een optie, de laatste tien metingen per pagina en de context per pagina in postmeta, en een kopie van een opgehaalde pagina voor vijf minuten. Bij verwijderen gaat dat allemaal weg, inclusief de API-sleutel. Op een netwerkinstallatie gebeurt dat voor elke site.

De sleutel kan ook helemaal buiten de database blijven met een constante in `wp-config.php`:

```php
define( 'THESEO_AI_OPENAI_KEY', 'sk-jouw-sleutel' );
```

Het instellingenveld schakelt zichzelf dan uit.

## Filters voor ontwikkelaars

* `theseo_ai_post_types` bepaalt welke posttypes geanalyseerd kunnen worden, standaard post en page
* `theseo_ai_fetch_timeout` de timeout van het verzoek aan je eigen pagina, standaard 10 seconden
* `theseo_ai_lm_timeout` de timeout van de OpenAI-aanroep, standaard 30 seconden
* `theseo_ai_lm_payload` filtert het verzoek voordat het verstuurd wordt
* `theseo_ai_lm_result` filtert het geparste antwoord van het model

## Taal

Standaard Engels, en volledig Nederlands als de sitetaal `nl_NL` is. Die omschakeling loopt over twee filters met een opzoektabel, alleen in wp-admin, en heeft geen po- of mo-bestanden nodig.

## Wat er in 2.0.0 is rechtgezet

De drie blokken met de namen GPT, Perplexity en Gemini zijn verwijderd, want ze waren drie afkappingen van dezelfde tekst. De score gaf voorheen iedere pagina 20 gratis punten voor autoriteit zonder dat er iets gemeten was. JSON-LD werd alleen in de post-inhoud gezocht, waardoor vrijwel elke site met schema in de `head` ten onrechte punten verloor. De woordentelling was niet UTF-8-veilig. Interne links werden nooit geteld terwijl het advies er wel over ging. En het API-sleutelveld werd altijd leeg gerenderd, waarna opslaan de sleutel wiste. De volledige lijst staat in [`readme.txt`](readme.txt).

## Opbouw

```
theseo-ai-snippet-previewer.php          bootstrap, taal laden, deactivatie-opruiming
includes/class-theseo-ai-analyzer.php    de zes metingen en het ophalen van de pagina
includes/class-theseo-ai-language-model.php   de optionele OpenAI-aanroep
includes/class-theseo-ai-admin.php       het beheerscherm en de AJAX-endpoints
includes/class-theseo-ai-i18n-nl.php     de Nederlandse opzoektabel
uninstall.php                            instellingen, historie en cache opruimen
```

## Licentie

GPL-2.0 of nieuwer, zie [LICENSE](LICENSE).

## Meer

Uitleg, achtergrond en de andere gratis tools staan op [theseo.nl/tools-en-plugins](https://theseo.nl/tools-en-plugins/).
