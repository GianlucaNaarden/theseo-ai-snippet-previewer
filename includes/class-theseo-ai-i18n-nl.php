<?php
/**
 * Built in Dutch interface without .po and .mo files.
 *
 * The filters are only added when the site language is Dutch and only in
 * wp-admin, and the tables are built once per request instead of once per
 * translated string.
 *
 * @package TheSEO_AI_Snippet_Previewer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dutch strings for this plugin.
 */
class TheSEO_AI_I18n_NL {

	/**
	 * Text domain this class answers for.
	 */
	const DOMAIN = 'theseo-ai-snippet-previewer';

	/**
	 * Singular table.
	 *
	 * @var array|null
	 */
	protected static $single = null;

	/**
	 * Plural table.
	 *
	 * @var array|null
	 */
	protected static $plural = null;

	/**
	 * Add the filters when they are useful.
	 */
	public static function register() {
		if ( ! is_admin() ) {
			return;
		}

		if ( 0 !== strpos( get_locale(), 'nl' ) ) {
			return;
		}

		add_filter( 'gettext', array( __CLASS__, 'filter_gettext' ), 10, 3 );
		add_filter( 'ngettext', array( __CLASS__, 'filter_ngettext' ), 10, 5 );
	}

	/**
	 * Translate a single string.
	 *
	 * @param string $translated Current translation.
	 * @param string $text       Original text.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public static function filter_gettext( $translated, $text, $domain ) {
		if ( self::DOMAIN !== $domain ) {
			return $translated;
		}

		$table = self::single_table();

		return isset( $table[ $text ] ) ? $table[ $text ] : $translated;
	}

	/**
	 * Translate a plural string.
	 *
	 * @param string $translated Current translation.
	 * @param string $single     Singular form.
	 * @param string $plural     Plural form.
	 * @param int    $number     Count.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public static function filter_ngettext( $translated, $single, $plural, $number, $domain ) {
		if ( self::DOMAIN !== $domain ) {
			return $translated;
		}

		$table = self::plural_table();
		$key   = $single . '|' . $plural;

		if ( ! isset( $table[ $key ] ) ) {
			return $translated;
		}

		return 1 === (int) $number ? $table[ $key ][0] : $table[ $key ][1];
	}

	/**
	 * Plural table.
	 *
	 * @return array
	 */
	protected static function plural_table() {
		if ( null !== self::$plural ) {
			return self::$plural;
		}

		self::$plural = array(
			'%d word|%d words'                   => array( '%d woord', '%d woorden' ),
			'%d subheading|%d subheadings'       => array( '%d tussenkop', '%d tussenkoppen' ),
			'%d JSON LD block|%d JSON LD blocks' => array( '%d JSON LD blok', '%d JSON LD blokken' ),
			'%d external link|%d external links' => array( '%d externe link', '%d externe links' ),
			'%d internal link|%d internal links' => array( '%d interne link', '%d interne links' ),
			'%d list|%d lists'                   => array( '%d opsomming', '%d opsommingen' ),
			'%d item|%d items'                   => array( '%d regel', '%d regels' ),
		);

		return self::$plural;
	}

	/**
	 * Singular table.
	 *
	 * @return array
	 */
	protected static function single_table() {
		if ( null !== self::$single ) {
			return self::$single;
		}

		self::$single = array(
			// Menu and screen.
			'AI snippet preview'                                                                                                                                              => 'AI snippet preview',
			'AI snippet preview and structure score'                                                                                                                          => 'AI snippet preview en structuurscore',
			'Measures which parts of a page can be quoted separately by a language model, and turns that into a score you can recalculate yourself. The extracts are literal quotes from your own page, not model output.' => 'Meet welke delen van een pagina een taalmodel los kan citeren, en rekent dat om naar een score die je zelf kunt narekenen. De extracten zijn letterlijke tekst uit je eigen pagina, geen uitvoer van een model.',
			'Method %s'                                                                                                                                                       => 'Methode %s',
			'Open screen'                                                                                                                                                     => 'Scherm openen',
			'Documentation:'                                                                                                                                                  => 'Documentatie:',

			// Page and context.
			'Page and context'                                                                                                                                                => 'Pagina en context',
			'The context is not part of the score. It is used for the prompt and, if you connected a model, for the request to that model. It is remembered per page.'         => 'De context telt niet mee in de score. Hij wordt gebruikt voor de prompt en, als je een model hebt gekoppeld, voor de vraag aan dat model. Hij wordt per pagina onthouden.',
			'Select a page or post'                                                                                                                                           => 'Kies een pagina of bericht',
			'(no title)'                                                                                                                                                      => '(zonder titel)',
			'Run analysis'                                                                                                                                                    => 'Analyse uitvoeren',
			'Analyzing'                                                                                                                                                       => 'Bezig met analyseren',
			'Main search term'                                                                                                                                                => 'Belangrijkste zoekterm',
			'Page type'                                                                                                                                                       => 'Paginatype',
			'Goal of the page'                                                                                                                                                => 'Doel van de pagina',
			'Tone of voice'                                                                                                                                                   => 'Tone of voice',
			'Brand in one line'                                                                                                                                               => 'Merk in een regel',
			'Page title'                                                                                                                                                      => 'Paginatitel',
			'not filled in'                                                                                                                                                   => 'niet ingevuld',

			// Page types and goals.
			'Derive from the title'                                                                                                                                           => 'Afleiden uit de titel',
			'Landing page'                                                                                                                                                    => 'Landingspagina',
			'Guide or tutorial'                                                                                                                                               => 'Gids of handleiding',
			'Blog article'                                                                                                                                                    => 'Blogartikel',
			'Q and A page'                                                                                                                                                    => 'Vraag en antwoord pagina',
			'Case study or results page'                                                                                                                                      => 'Case of resultatenpagina',
			'General page'                                                                                                                                                    => 'Algemene pagina',
			'More organic traffic'                                                                                                                                            => 'Meer organisch verkeer',
			'More leads'                                                                                                                                                      => 'Meer leads',
			'Being used in AI answers'                                                                                                                                        => 'Gebruikt worden in AI antwoorden',
			'Conversion on this page'                                                                                                                                         => 'Conversie op deze pagina',

			// Score and method.
			'Structure score out of 100, added up from six measured signals'                                                                                                  => 'Structuurscore van 100, opgeteld uit zes gemeten signalen',
			'The score says how well this page can be quoted in parts. It says nothing about ranking, about traffic or about whether a model will actually use your page.'     => 'De score zegt hoe goed deze pagina in delen te citeren is. Hij zegt niets over posities, over verkeer of over de vraag of een model je pagina echt gebruikt.',
			'How the score is built up'                                                                                                                                       => 'Hoe de score is opgebouwd',
			'Every row shows what was counted, the rule that turns it into points, and the maximum. The six maxima add up to one hundred, so you can check the total by hand.' => 'Elke regel toont wat er geteld is, de regel die dat omzet in punten, en het maximum. De zes maxima tellen op tot honderd, dus je kunt het totaal met de hand controleren.',
			'Signal'                                                                                                                                                          => 'Signaal',
			'Measured'                                                                                                                                                        => 'Gemeten',
			'Points'                                                                                                                                                          => 'Punten',
			'Total'                                                                                                                                                           => 'Totaal',
			'not measured yet'                                                                                                                                                => 'nog niet gemeten',
			'Text length'                                                                                                                                                     => 'Lengte van de tekst',
			'%1$d points at %2$d words or more, proportional below that.'                                                                                                     => '%1$d punten bij %2$d woorden of meer, evenredig daaronder.',
			'Subheadings H2 and H3'                                                                                                                                           => 'Tussenkoppen H2 en H3',
			'Nothing without a subheading, half for one or two, everything from three onwards.'                                                                               => 'Niets zonder tussenkop, de helft bij een of twee, alles vanaf drie.',
			'Lists'                                                                                                                                                           => 'Opsommingen',
			'%1$s with %2$s'                                                                                                                                                  => '%1$s met %2$s',
			'All points from the first ul or ol in the content onwards.'                                                                                                       => 'Alle punten vanaf de eerste ul of ol in de inhoud.',
			'Structured data JSON LD'                                                                                                                                         => 'Gestructureerde data JSON LD',
			'Measured on the published page, so JSON LD from your SEO plugin counts as well.'                                                                                  => 'Gemeten op de gepubliceerde pagina, dus JSON LD van je SEO plugin telt ook mee.',
			'Measured on the content only, because the published page could not be fetched. JSON LD in the document head is not visible this way.'                             => 'Alleen op de inhoud gemeten, want de gepubliceerde pagina was niet op te halen. JSON LD in de head van het document is zo niet zichtbaar.',
			'External sources'                                                                                                                                                => 'Externe bronnen',
			'Half for one link to another domain, everything from two onwards.'                                                                                                => 'De helft bij een link naar een ander domein, alles vanaf twee.',
			'Internal links'                                                                                                                                                  => 'Interne links',
			'Half for one or two links inside your own site, everything from three onwards.'                                                                                   => 'De helft bij een of twee links binnen je eigen site, alles vanaf drie.',
			'Measured on the published page and on the content of the editor.'                                                                                                 => 'Gemeten op de gepubliceerde pagina en op de inhoud uit de editor.',
			'Measured on the content of the editor only, the published page could not be fetched.'                                                                             => 'Alleen gemeten op de inhoud uit de editor, de gepubliceerde pagina was niet op te halen.',

			// Checklist.
			'Key focus points'                                                                                                                                                => 'Belangrijkste aandachtspunten',
			'No analysis has been run yet'                                                                                                                                     => 'Nog geen analyse uitgevoerd',
			'Subheading present, the page has an outline'                                                                                                                     => 'Tussenkop aanwezig, de pagina heeft een indeling',
			'No H2 or H3 in the content yet'                                                                                                                                  => 'Nog geen H2 of H3 in de inhoud',
			'List present, that is a block a model can quote as a whole'                                                                                                       => 'Opsomming aanwezig, dat is een blok dat een model in zijn geheel kan citeren',
			'No list, so there is no separately quotable block'                                                                                                                => 'Geen opsomming, dus er is geen los te citeren blok',
			'JSON LD present on this page'                                                                                                                                     => 'JSON LD aanwezig op deze pagina',
			'No JSON LD found on this page'                                                                                                                                    => 'Geen JSON LD gevonden op deze pagina',
			'External source present, claims can be checked'                                                                                                                   => 'Externe bron aanwezig, uitspraken zijn te controleren',
			'No external source, nothing on this page can be verified'                                                                                                         => 'Geen externe bron, niets op deze pagina is te verifieren',
			'Internal link present, the page is part of your site'                                                                                                             => 'Interne link aanwezig, de pagina hangt aan je site vast',
			'No internal link, the page stands on its own'                                                                                                                     => 'Geen interne link, de pagina staat op zichzelf',
			'Enough text for a full answer'                                                                                                                                    => 'Genoeg tekst voor een volledig antwoord',
			'Short text, extra context helps'                                                                                                                                  => 'Korte tekst, extra context helpt',
			'No clear shortcomings found'                                                                                                                                      => 'Geen duidelijke tekortkomingen gevonden',

			// Suggestions.
			'Suggestions'                                                                                                                                                     => 'Suggesties',
			'Every suggestion belongs to a signal that scored below its maximum. When a model is connected its own suggestions are added below these.'                          => 'Elke suggestie hoort bij een signaal dat onder het maximum bleef. Is er een model gekoppeld, dan komen zijn eigen suggesties eronder.',
			'No additional suggestions found'                                                                                                                                  => 'Geen extra suggesties gevonden',
			'Split the page into at least three sections with H2 headings that name the subject of the section.'                                                                => 'Verdeel de pagina in minimaal drie secties met H2 koppen die het onderwerp van de sectie benoemen.',
			'Turn the key points into a list. A list item is a block that can be quoted without the rest of the page.'                                                          => 'Maak van de kernpunten een opsomming. Een opsommingsregel is een blok dat zonder de rest van de pagina te citeren is.',
			'Add FAQPage JSON LD for the questions and answers on this page.'                                                                                                   => 'Voeg FAQPage JSON LD toe voor de vragen en antwoorden op deze pagina.',
			'Add Article or HowTo JSON LD that describes the steps of this guide.'                                                                                              => 'Voeg Article of HowTo JSON LD toe die de stappen van deze gids beschrijft.',
			'Add JSON LD that fits this page type, through your SEO plugin or as a code block.'                                                                                 => 'Voeg JSON LD toe die bij dit paginatype past, via je SEO plugin of als codeblok.',
			'Link to two sources that can be checked, so the numbers on this page have a place to come from.'                                                                   => 'Link naar twee bronnen die te controleren zijn, zodat de cijfers op deze pagina ergens vandaan komen.',
			'Link to three pages of your own that go deeper into a part of this subject.'                                                                                       => 'Link naar drie eigen paginas die dieper ingaan op een deel van dit onderwerp.',
			'The page has %1$d words. From %2$d words onwards the length signal is complete.'                                                                                   => 'De pagina heeft %1$d woorden. Vanaf %2$d woorden is het lengtesignaal compleet.',
			'Every measured signal is at its maximum. What is left is keeping the page up to date and checking whether the facts are still correct.'                            => 'Elk gemeten signaal staat op het maximum. Wat overblijft is de pagina actueel houden en controleren of de feiten nog kloppen.',

			// History.
			'Earlier measurements of this page'                                                                                                                                => 'Eerdere metingen van deze pagina',
			'The last ten measurements are stored with the page. They are removed when you delete the plugin.'                                                                  => 'De laatste tien metingen worden bij de pagina bewaard. Ze worden verwijderd als je de plugin verwijdert.',
			'No earlier measurement for this page yet.'                                                                                                                         => 'Nog geen eerdere meting van deze pagina.',

			// Extracts.
			'What can be quoted from this page'                                                                                                                                => 'Wat er uit deze pagina te citeren valt',
			'These three blocks are literal text from your own page. They are not summaries and not model output, so nothing here is invented.'                                  => 'Deze drie blokken zijn letterlijke tekst uit je eigen pagina. Het zijn geen samenvattingen en geen uitvoer van een model, dus hier wordt niets verzonnen.',
			'Opening'                                                                                                                                                          => 'Opening',
			'The first words of the page appear here after the analysis.'                                                                                                       => 'Hier komen na de analyse de eerste woorden van de pagina.',
			'List items'                                                                                                                                                       => 'Opsommingsregels',
			'The list items of the page appear here, the blocks that can be quoted whole.'                                                                                       => 'Hier komen de opsommingsregels van de pagina, de blokken die in hun geheel te citeren zijn.',
			'Outline'                                                                                                                                                          => 'Indeling',
			'The subheadings of the page appear here in order.'                                                                                                                 => 'Hier komen de tussenkoppen van de pagina op volgorde.',
			'This page has no text content.'                                                                                                                                   => 'Deze pagina heeft geen tekstinhoud.',
			'There is no list on this page, so there is no block that can be quoted as a whole.'                                                                                 => 'Er staat geen opsomming op deze pagina, dus er is geen blok dat in zijn geheel te citeren is.',
			'There are no subheadings, so the page is one block without an outline.'                                                                                             => 'Er zijn geen tussenkoppen, dus de pagina is een blok zonder indeling.',

			// Language model.
			'Suggestions from the language model'                                                                                                                              => 'Suggesties van het taalmodel',
			'Model %s connected'                                                                                                                                               => 'Model %s gekoppeld',
			'No API key, measurement and prompt only'                                                                                                                          => 'Geen API sleutel, alleen meting en prompt',
			'This block stays empty until you connect a model. The measurement, the extracts and the prompt work without one.'                                                  => 'Dit blok blijft leeg tot je een model koppelt. De meting, de extracten en de prompt werken ook zonder.',
			'No API key set, so only the measurement and the prompt are shown.'                                                                                                 => 'Geen API sleutel ingesteld, dus alleen de meting en de prompt worden getoond.',
			'The model answered with status %1$d%2$s'                                                                                                                           => 'Het model antwoordde met status %1$d%2$s',
			'The answer from the model could not be read.'                                                                                                                      => 'Het antwoord van het model was niet te lezen.',
			'The model did not return valid JSON.'                                                                                                                              => 'Het model gaf geen geldige JSON terug.',
			'Answer received from %s.'                                                                                                                                          => 'Antwoord ontvangen van %s.',
			'The model was not reached, the measurement below is unchanged. %s'                                                                                                 => 'Het model is niet bereikt, de meting hieronder is onveranderd. %s',
			'Summary, long'                                                                                                                                                   => 'Samenvatting, lang',
			'Summary, short'                                                                                                                                                  => 'Samenvatting, kort',
			'Summary, balanced'                                                                                                                                               => 'Samenvatting, gebalanceerd',
			'These three summaries are written by the model you connected. They are not the answer of GPT, Perplexity or Gemini, because no plugin can read those from the outside.' => 'Deze drie samenvattingen zijn geschreven door het model dat jij hebt gekoppeld. Het is niet het antwoord van GPT, Perplexity of Gemini, want dat kan geen enkele plugin van buitenaf uitlezen.',
			'Suggested meta title'                                                                                                                                             => 'Voorgestelde meta titel',
			'Suggested meta description'                                                                                                                                       => 'Voorgestelde meta beschrijving',
			'Copy these fields into your SEO plugin yourself. This plugin never changes metadata.'                                                                              => 'Kopieer deze velden zelf naar je SEO plugin. Deze plugin wijzigt nooit metadata.',
			'FAQ ideas for this page'                                                                                                                                          => 'FAQ ideeen voor deze pagina',
			'No FAQ suggestions from the model yet.'                                                                                                                           => 'Nog geen FAQ suggesties van het model.',
			'Schema JSON LD example'                                                                                                                                           => 'Schema JSON LD voorbeeld',
			'A model writes an example here. Check it before you publish it, a schema with claims that are not on the page is worse than no schema.'                             => 'Een model schrijft hier een voorbeeld. Controleer het voor je het publiceert, een schema met beweringen die niet op de pagina staan is slechter dan geen schema.',
			'Prompt for GPT, Perplexity or Gemini'                                                                                                                             => 'Prompt voor GPT, Perplexity of Gemini',
			'After the analysis the full prompt appears here, with your page and the measured numbers already filled in. It works without an API key.'                           => 'Na de analyse staat hier de volledige prompt, met je pagina en de gemeten getallen er al in. Werkt zonder API sleutel.',
			'Copy prompt'                                                                                                                                                      => 'Prompt kopieren',
			'Copied'                                                                                                                                                           => 'Gekopieerd',
			'Copying failed, select the text yourself.'                                                                                                                         => 'Kopieren lukte niet, selecteer de tekst zelf.',

			// Prompt body.
			'You are an AI SEO specialist. Judge the page below on how well a language model can summarise and quote it.'                                                       => 'Je bent een AI SEO specialist. Beoordeel de pagina hieronder op hoe goed een taalmodel hem kan samenvatten en citeren.',
			'Measured on this page: score %1$d of 100, %2$d words, %3$d subheadings, %4$d lists, %5$d JSON LD blocks, %6$d external links, %7$d internal links.'                => 'Gemeten op deze pagina: score %1$d van 100, %2$d woorden, %3$d tussenkoppen, %4$d opsommingen, %5$d JSON LD blokken, %6$d externe links, %7$d interne links.',
			'Return in this order:'                                                                                                                                            => 'Geef in deze volgorde terug:',
			'1. Three summaries of at most 40 words, the way a language model would summarise this page in an answer.'                                                          => '1. Drie samenvattingen van maximaal 40 woorden, zoals een taalmodel deze pagina zou samenvatten in een antwoord.',
			'2. Per summary: which information from the page was used, and which important information was missed.'                                                             => '2. Per samenvatting: welke informatie uit de pagina het model gebruikt heeft, en welke belangrijke informatie het gemist heeft.',
			'3. What is missing in the structure: headings, lists, definitions, step by step plans, a question and answer block.'                                               => '3. Wat er aan de structuur ontbreekt: koppen, opsommingen, definities, stappenplannen, een vraag en antwoordblok.',
			'4. Which structured data is missing and why it would help here.'                                                                                                   => '4. Welke gestructureerde data ontbreekt en waarom die hier zou helpen.',
			'5. What shows that this page has authority, and what does not.'                                                                                                    => '5. Waaruit blijkt dat deze pagina gezag heeft, en waaruit niet.',
			'6. The five changes with the most effect, in order.'                                                                                                               => '6. De vijf aanpassingen die het meeste effect hebben, op volgorde.',
			'Only name what you actually find in the text. Do not invent numbers and do not invent sources.'                                                                    => 'Noem alleen wat je werkelijk in de tekst terugziet. Verzin geen cijfers en geen bronnen.',
			'The page:'                                                                                                                                                        => 'De pagina:',

			// Settings.
			'Language model connection'                                                                                                                                       => 'Koppeling met een taalmodel',
			'Optional. Without a key nothing leaves your server. With a key an excerpt of the page and the measured numbers are sent to OpenAI, billed on your own account.'    => 'Optioneel. Zonder sleutel verlaat er niets je server. Met sleutel gaan een deel van de paginatekst en de gemeten getallen naar OpenAI, op jouw eigen rekening.',
			'OpenAI API key'                                                                                                                                                  => 'OpenAI API sleutel',
			'The key comes from the constant THESEO_AI_OPENAI_KEY in wp-config.php. Nothing is stored in the database and this field is switched off.'                          => 'De sleutel komt uit de constante THESEO_AI_OPENAI_KEY in wp-config.php. Er staat niets in de database en dit veld staat uit.',
			'sk-... paste your secret key here'                                                                                                                                => 'sk-... plak hier je geheime sleutel',
			'A key is stored. Leave this field empty to keep it. The key is never shown again.'                                                                                 => 'Er is een sleutel opgeslagen. Laat dit veld leeg om hem te behouden. De sleutel wordt nooit meer getoond.',
			'The key is stored in the WordPress options table in plain text, like every other WordPress setting. Putting the constant THESEO_AI_OPENAI_KEY in wp-config.php keeps it out of the database.' => 'De sleutel staat als gewone tekst in de optietabel van WordPress, net als elke andere WordPress instelling. Met de constante THESEO_AI_OPENAI_KEY in wp-config.php houd je hem uit de database.',
			'Remove the stored key when saving'                                                                                                                                => 'Verwijder de opgeslagen sleutel bij het opslaan',
			'That does not look like an API key, so nothing was changed. A key consists of letters, digits, dots, dashes and underscores.'                                    => 'Dit lijkt geen API sleutel, er is dus niets gewijzigd. Een sleutel bestaat uit letters, cijfers, punten, streepjes en liggende streepjes.',
			'OpenAI model'                                                                                                                                                     => 'OpenAI model',
			'The model name is passed on unchanged. Check in your own OpenAI account which names your key may use.'                                                             => 'De modelnaam wordt onveranderd doorgegeven. Kijk in je eigen OpenAI account welke namen je sleutel mag gebruiken.',
			'Preferred output language'                                                                                                                                        => 'Voorkeurstaal voor de uitvoer',
			'Detect from site language'                                                                                                                                        => 'Afleiden uit de sitetaal',
			'English'                                                                                                                                                          => 'Engels',
			'Dutch'                                                                                                                                                            => 'Nederlands',
			'German'                                                                                                                                                           => 'Duits',
			'French'                                                                                                                                                           => 'Frans',
			'Used for the prompt and for the answer of the model.'                                                                                                              => 'Wordt gebruikt voor de prompt en voor het antwoord van het model.',
			'What is sent'                                                                                                                                                     => 'Wat er verstuurd wordt',
			'With a key: the title, at most eight thousand characters of the page text, the context you filled in and the measured numbers. Nothing else, and nothing at all without a key.' => 'Met sleutel: de titel, maximaal achtduizend tekens paginatekst, de context die je invulde en de gemeten getallen. Verder niets, en zonder sleutel helemaal niets.',
			'Save connection settings'                                                                                                                                         => 'Koppeling opslaan',

			// Site checks.
			'Site checks'                                                                                                                                                     => 'Controles op de site',
			'Three checks on your WordPress installation. They are not part of the page score.'                                                                                 => 'Drie controles op je WordPress installatie. Ze tellen niet mee in de score van een pagina.',
			'Pretty permalinks are enabled'                                                                                                                                    => 'Nette permalinks staan aan',
			'Enable pretty permalinks under Settings Permalinks, the plain query string form is harder to read for people and for crawlers.'                                    => 'Zet nette permalinks aan bij Instellingen Permalinks, de kale vorm met vraagtekens leest lastiger voor mensen en voor crawlers.',
			'There is an llms.txt file in the site root'                                                                                                                       => 'Er staat een llms.txt in de hoofdmap van de site',
			'There is no llms.txt file in the site root. That file is a proposal, not a standard, and no crawler is obliged to read it.'                                        => 'Er staat geen llms.txt in de hoofdmap. Dat bestand is een voorstel en geen standaard, geen enkele crawler is verplicht het te lezen.',
			'There is a physical robots.txt file in the site root'                                                                                                             => 'Er staat een fysiek robots.txt bestand in de hoofdmap',
			'There is no physical robots.txt file. WordPress then serves a virtual one, so check the address itself before you conclude anything.'                              => 'Er staat geen fysiek robots.txt bestand. WordPress serveert er dan zelf een, dus controleer het adres zelf voor je conclusies trekt.',

			// Errors.
			'No access'                                                                                                                                                       => 'Geen toegang',
			'No access to this page'                                                                                                                                           => 'Geen toegang tot deze pagina',
			'Invalid page'                                                                                                                                                    => 'Ongeldige pagina',
			'Page not found'                                                                                                                                                  => 'Pagina niet gevonden',
			'Please select a page or post first'                                                                                                                               => 'Kies eerst een pagina of bericht',
			'Something went wrong while running the analysis.'                                                                                                                 => 'Er ging iets mis tijdens de analyse.',
		);

		return self::$single;
	}
}
