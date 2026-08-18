=== TheSEO AI Snippet Previewer ===
Contributors: theseo
Tags: seo, ai, content, schema, structured data
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Measures per page which parts a language model can quote separately, shows the full calculation behind the score, and builds a ready to use prompt.

== Description ==

A language model does not read your page the way a visitor does. It looks for parts that stand on their own and can be taken over without the rest: a definition in one sentence, a numbered list, a figure with a source next to it. Running text is summarised and disappears.

This plugin measures six things on a page that decide whether such parts exist, adds them up to a score out of 100, and prints the whole calculation on screen. Every point comes from something that was counted. There are no hidden constants and no bonus points, so you can check the total by hand.

= What the plugin does =

* Six measured signals per page, each with a fixed maximum, together exactly 100 points
* A table that shows per signal what was counted, the rule that turns it into points, and the maximum
* Three literal extracts from your own page: the opening, the list items and the outline of subheadings
* A checklist and suggestions, each one tied to a signal that scored below its maximum
* The last ten measurements per page, so you can see whether a rewrite helped
* A complete prompt with your page and the measured numbers already filled in, for GPT, Perplexity or Gemini
* Three checks on the installation: pretty permalinks, llms.txt and a physical robots.txt
* Optional: a connection to OpenAI with your own key for meta suggestions, FAQ ideas and a schema example

= What the plugin does not do =

* It does not change your content, your metadata or your schema. It only reads.
* It adds nothing to the front end. Everything happens inside wp-admin.
* It does not predict rankings, traffic or whether an AI system will actually use your page. Nobody can measure that from the outside.
* It does not imitate GPT, Perplexity or Gemini. Versions before 2.0.0 showed three blocks labelled with those names that were in fact three truncations of your own text at 45, 28 and 38 words. That is gone. What is left is what can honestly be shown: literal extracts, measured signals and a prompt you can run through a real model yourself.

= The scoring method, in full =

Version 2.0 of the method. The six maxima add up to 100.

1. Text length, 25 points. Number of words in the rendered content. 25 points at 800 words or more, proportional below that.
2. Subheadings H2 and H3, 20 points. Zero points without a subheading, 10 points for one or two, 20 points from three onwards.
3. Lists, 15 points. All 15 points from the first ul or ol in the content onwards.
4. Structured data JSON LD, 20 points. Twenty points when a JSON LD script block is present. This is measured on the published page as a visitor receives it, so JSON LD that your SEO plugin prints in the document head counts as well. When that page cannot be fetched, for example because the post is not published, the measurement falls back to the content only and the table says so.
5. External sources, 10 points. Five points for one link to another domain, 10 points from two onwards. Links to your own domain do not count here.
6. Internal links, 10 points. Five points for one or two links inside your own site, 10 points from three onwards.

Words are counted as sequences of letters and digits, UTF-8 aware, so Dutch words with accents count as one word. Links are read from the rendered content; anchors, mailto and tel links are ignored.

The score says how well this page can be quoted in parts. It says nothing more than that.

= What is sent and what is stored =

Without an API key nothing leaves your server, apart from one request from your own site to your own page for the structured data check.

With an API key the plugin sends to OpenAI: the page title, at most eight thousand characters of the page text, the context you filled in and the measured numbers. Nothing else. The traffic is billed on your own OpenAI account.

The plugin stores: the settings in one option, the last ten measurements per page and the context per page in post meta, and a cached copy of a fetched page for five minutes. Deleting the plugin removes all of it, including the API key. On a network install this happens for every site.

The key can also be kept out of the database entirely by putting a constant in wp-config.php:

`define( 'THESEO_AI_OPENAI_KEY', 'sk-your-key' );`

The settings field then switches itself off.

= Hooks for developers =

* `theseo_ai_post_types` filters which post types can be analysed, default post and page
* `theseo_ai_fetch_timeout` filters the timeout of the request to your own page, default 10 seconds
* `theseo_ai_lm_timeout` filters the timeout of the OpenAI call, default 30 seconds
* `theseo_ai_lm_payload` filters the request before it is sent
* `theseo_ai_lm_result` filters the parsed answer of the model

= Language =

English by default, and completely Dutch when the site language is nl_NL. That switch runs on two filters with a lookup table, only in wp-admin, and needs no .po or .mo files.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip through Plugins, Add new, Upload plugin.
2. Activate the plugin.
3. Open AI snippet preview in the admin menu.
4. Pick a page or post, fill in the context if you want, and press Run analysis.

The connection to OpenAI is optional. Everything except the block with model suggestions works without a key.

== Frequently Asked Questions ==

= How is the score calculated? =

From six counted signals with a fixed maximum each. The full method is in the description above and the plugin prints the same table on screen after every analysis, including what was counted per signal. If you disagree with a rule you can see exactly which one it is.

= Why is the structured data check different from other tools? =

Because it looks at the published page instead of the content in the editor. Almost every SEO plugin prints JSON LD in the head of the document and never inside the post content. A check on the content alone reports "no schema" on sites that do have schema. Versions before 2.0.0 made that mistake.

= Does this plugin call an external AI service? =

Only when you enter your own API key. Without a key the plugin does one request from your site to your own page and nothing else.

= Does it work next to Yoast, Rank Math or SEOPress? =

Yes. The plugin never writes metadata or schema. It reads the page and prints numbers. The JSON LD that those plugins output is counted as a plus in signal 4.

= Does it show how GPT, Perplexity or Gemini summarise my page? =

No, and no plugin can do that from the outside. What this plugin shows is which parts of your page can be quoted separately, and it builds the prompt with which you can put the question to a real model yourself.

= What happens to my data when I delete the plugin? =

Everything is removed: the settings including the API key, the measurement history and the context on every page, and the cached copies. Deactivating only clears the cache, so switching the plugin back on costs nothing.

= Does it work with custom post types? =

Not by default. The `theseo_ai_post_types` filter adds them.

== Screenshots ==

1. The screen with the score, the calculation per signal and the measurement history.
2. The three literal extracts and the prompt.
3. The connection settings and the site checks.

== Changelog ==

= 2.0.0 =

Honesty of the output:

* Removed the three preview blocks labelled GPT style, Perplexity style and Gemini style. They were the first 45, 28 and 38 words of your own text with a prefix, identical in method, and they claimed to represent the behaviour of three named systems.
* Replaced by three literal extracts from the page: the opening, the list items and the outline of subheadings, each marked as a quote from your own page.
* Removed the twenty free authority points that every page received without anything being measured.
* The score is now built from six signals with a fixed maximum, together exactly 100, and the plugin prints the full calculation per signal on screen.
* Added the measurement source to the screen, so it says whether the published page was reachable or whether only the content was measured.
* The tooltip that claimed the score said something about how usable a page is for language models now says what the score really covers.

Correctness of the measurement:

* Structured data is now measured on the published page instead of on the post content, so JSON LD from your SEO plugin is no longer missed. This was a false negative of twenty points on almost every site with an SEO plugin.
* Word counting is UTF-8 aware. `str_word_count()` counted Dutch words with accents as two words.
* Text is separated on tags before counting, so the last word of a paragraph and the first word of the next are no longer glued into one word.
* Links are split into internal and external with www treated as the same host, and anchors, mailto and tel links are ignored.
* Internal links were added as a sixth signal, because the plugin already advised internal linking without ever measuring it.
* The page type guess also recognises Dutch words in the title.

Bugs:

* Saving the settings no longer wipes the stored API key. The key field is never prefilled, so every save with another change stored an empty key and silently switched the model connection off.
* A key that does not have the shape of a key is refused with a message instead of being stripped down to something that will never work.
* A failed call to the model is now reported on screen. It used to fall back silently while the screen kept saying the model was active.

Security and cleanup:

* Added `uninstall.php`. Deleting the plugin now removes the settings including the API key, the measurement history, the page context and the cached pages, on a network install for every site.
* Deactivating clears the cached page copies.
* Added a capability check per post (`edit_post`) on top of the existing nonce and `manage_options` check, and the post type is validated.
* All input from the request is unslashed and sanitized against a whitelist.
* Everything the language model returns is stripped and length limited before it reaches the screen, and it is placed in the page with textContent only.
* The API key can be set with the constant `THESEO_AI_OPENAI_KEY` in wp-config.php, which keeps it out of the database.
* The two database queries for cleanup use prepared statements.

Compatibility and structure:

* Added the missing plugin headers: Requires at least, Requires PHP, License, License URI and Update URI.
* The text domain is loaded on `init` instead of on `plugins_loaded`, which is what WordPress 6.7 and later expect. Loading earlier produces a notice.
* CSS and JavaScript are proper files that are enqueued, instead of being echoed into `admin_head` and `admin_footer`.
* The Dutch translation table is built once per request instead of once per translated string, the filters are only added on a Dutch site in wp-admin, and plural forms are translated through `ngettext`.
* The plugin was split into a bootstrap file plus four classes under `includes/`.
* Added a settings link on the plugin screen.

New:

* Page context per page: main search term, page type, goal, tone of voice and brand in one line. It is stored per page and used for the prompt and for the model.
* The prompt is built for every analysis, also without an API key, with the page and the measured numbers already in it, plus a copy button.
* The last ten measurements per page are kept, with the change against the previous measurement.

Note about testing: the code of this version was checked with `php -l` on PHP 8.5 and the measurement, the scoring, the settings sanitizer and the cleaning of model output were run against test cases outside WordPress. It has not yet run on a WordPress 7.0 installation, which is why `Tested up to` still says 6.8. Raise that line after the first run on a live site.

= 1.3.0 =

* Added language model integration settings block on the admin screen
* Added optional OpenAI integration using your own API key and model name
* Added AI powered meta title and meta description suggestions per page
* Added AI powered FAQ ideas and schema JSON LD example per page
* Added a ready to use AI prompt helper block for further content improvement
* Kept full backward compatibility when no API key is set

= 1.2.0 =

* Added AI improvement suggestions block per page (schema, content depth, external sources, structure)
* Added Site AI readiness overview (checks permalinks, llms.txt, robots.txt)
* Improved contrast and text colors in the admin UI
* Adjusted tooltip behaviour so help tooltips no longer get cut off

= 1.1.1 =

* Added built in Dutch translation layer (auto switch when site language is nl_NL)
* Improved internal comments and clarified description for WordPress.org usage
* Minor UI tweaks to admin page

= 1.1.0 =

* Switched default codebase language to English for global compatibility
* Added full i18n support with text domain `theseo-ai-snippet-previewer`
* Cleaned up labels and descriptions for English installs

= 1.0.0 =

* Initial release with an AI score per page, structure checks, three example summaries and a basic checklist

== Upgrade Notice ==

= 2.0.0 =

The score is calculated differently and the calculation is now shown in full. Three preview blocks that claimed to imitate GPT, Perplexity and Gemini have been removed, because they were truncations of your own text. Scores from earlier versions are not comparable to scores from this one; the history shows which method version produced a measurement. Your settings and your API key are kept.

= 1.3.0 =

Adds optional OpenAI language model integration. No breaking changes.
