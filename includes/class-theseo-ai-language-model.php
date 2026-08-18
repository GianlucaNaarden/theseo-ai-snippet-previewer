<?php
/**
 * Optional language model call and the prompt builder.
 *
 * The prompt is always built, also without an API key, because it is the
 * part you can use by hand. The API call only happens when a key is present.
 *
 * @package TheSEO_AI_Snippet_Previewer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to OpenAI and builds the manual prompt.
 */
class TheSEO_AI_Language_Model {

	/**
	 * Endpoint used for the optional call.
	 */
	const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	/**
	 * The API key in use. A key in wp-config.php wins over the database, so
	 * a site can keep the secret out of the options table entirely.
	 *
	 * @return string
	 */
	public static function api_key( $settings = array() ) {
		if ( defined( 'THESEO_AI_OPENAI_KEY' ) && '' !== (string) THESEO_AI_OPENAI_KEY ) {
			return (string) THESEO_AI_OPENAI_KEY;
		}

		return isset( $settings['openai_api_key'] ) ? trim( (string) $settings['openai_api_key'] ) : '';
	}

	/**
	 * Where the key comes from: constant, option or nowhere.
	 *
	 * @param array $settings Plugin settings.
	 * @return string One of constant, option, none.
	 */
	public static function key_source( $settings = array() ) {
		if ( defined( 'THESEO_AI_OPENAI_KEY' ) && '' !== (string) THESEO_AI_OPENAI_KEY ) {
			return 'constant';
		}

		if ( ! empty( $settings['openai_api_key'] ) ) {
			return 'option';
		}

		return 'none';
	}

	/**
	 * Output language, either chosen or derived from the site locale.
	 *
	 * @return string
	 */
	public function language() {
		$lang = isset( $this->settings['default_language'] ) ? $this->settings['default_language'] : 'auto';

		if ( 'auto' !== $lang ) {
			return $lang;
		}

		$locale = get_locale();

		foreach ( array( 'nl', 'de', 'fr' ) as $code ) {
			if ( 0 === strpos( $locale, $code ) ) {
				return $code;
			}
		}

		return 'en';
	}

	/**
	 * Build the prompt a person can paste into any AI chat tool. It contains
	 * the page context and the measured signals, and it forbids the model to
	 * invent numbers or sources.
	 *
	 * @param string $title    Page title.
	 * @param array  $context  Page context supplied in the form.
	 * @param array  $signals  Measured signals.
	 * @param int    $score    Total score.
	 * @param string $text     Plain text of the page.
	 * @return string
	 */
	public static function build_prompt( $title, $context, $signals, $score, $text ) {
		$excerpt = self::cut( $text, 6000 );

		$lines = array(
			__( 'You are an AI SEO specialist. Judge the page below on how well a language model can summarise and quote it.', 'theseo-ai-snippet-previewer' ),
			'',
			sprintf( '%s: %s', __( 'Page title', 'theseo-ai-snippet-previewer' ), $title ),
			sprintf( '%s: %s', __( 'Main search term', 'theseo-ai-snippet-previewer' ), '' !== $context['keyword'] ? $context['keyword'] : __( 'not filled in', 'theseo-ai-snippet-previewer' ) ),
			sprintf( '%s: %s', __( 'Page type', 'theseo-ai-snippet-previewer' ), $context['page_type'] ),
			sprintf( '%s: %s', __( 'Goal of the page', 'theseo-ai-snippet-previewer' ), $context['goal'] ),
			sprintf( '%s: %s', __( 'Brand in one line', 'theseo-ai-snippet-previewer' ), '' !== $context['brand'] ? $context['brand'] : __( 'not filled in', 'theseo-ai-snippet-previewer' ) ),
			sprintf( '%s: %s', __( 'Tone of voice', 'theseo-ai-snippet-previewer' ), '' !== $context['tone'] ? $context['tone'] : __( 'not filled in', 'theseo-ai-snippet-previewer' ) ),
			'',
			sprintf(
				/* translators: 1: score, 2: words, 3: subheadings, 4: lists, 5: JSON LD blocks, 6: external links, 7: internal links. */
				__( 'Measured on this page: score %1$d of 100, %2$d words, %3$d subheadings, %4$d lists, %5$d JSON LD blocks, %6$d external links, %7$d internal links.', 'theseo-ai-snippet-previewer' ),
				(int) $score,
				(int) $signals['words'],
				(int) $signals['headings'],
				(int) $signals['lists'],
				(int) $signals['jsonld'],
				(int) $signals['external_links'],
				(int) $signals['internal_links']
			),
			'',
			__( 'Return in this order:', 'theseo-ai-snippet-previewer' ),
			__( '1. Three summaries of at most 40 words, the way a language model would summarise this page in an answer.', 'theseo-ai-snippet-previewer' ),
			__( '2. Per summary: which information from the page was used, and which important information was missed.', 'theseo-ai-snippet-previewer' ),
			__( '3. What is missing in the structure: headings, lists, definitions, step by step plans, a question and answer block.', 'theseo-ai-snippet-previewer' ),
			__( '4. Which structured data is missing and why it would help here.', 'theseo-ai-snippet-previewer' ),
			__( '5. What shows that this page has authority, and what does not.', 'theseo-ai-snippet-previewer' ),
			__( '6. The five changes with the most effect, in order.', 'theseo-ai-snippet-previewer' ),
			'',
			__( 'Only name what you actually find in the text. Do not invent numbers and do not invent sources.', 'theseo-ai-snippet-previewer' ),
			'',
			__( 'The page:', 'theseo-ai-snippet-previewer' ),
			$excerpt,
		);

		return implode( "\n", $lines );
	}

	/**
	 * Ask the model for extra suggestions. Returns an array with a status so
	 * the screen can say what happened instead of silently showing nothing.
	 *
	 * @param string $title   Page title.
	 * @param string $text    Plain text of the page.
	 * @param array  $context Page context.
	 * @param array  $signals Measured signals.
	 * @param int    $score   Total score.
	 * @return array
	 */
	public function suggest( $title, $text, $context, $signals, $score ) {
		$api_key = self::api_key( $this->settings );

		if ( '' === $api_key ) {
			return array(
				'status'  => 'off',
				'message' => __( 'No API key set, so only the measurement and the prompt are shown.', 'theseo-ai-snippet-previewer' ),
				'data'    => array(),
			);
		}

		$model = ! empty( $this->settings['openai_model'] ) ? $this->settings['openai_model'] : 'gpt-4o-mini';
		$lang  = $this->language();

		$system = sprintf(
			/* translators: %s: two letter language code. */
			'You are an SEO and AI content assistant. Return only JSON. Write every natural language field in the language with code "%s". Never invent numbers, sources or claims that are not in the supplied text.',
			$lang
		);

		$user = sprintf(
			"Analyse this page for SEO and AI readiness.\n\nTitle: %1\$s\nMain search term: %2\$s\nPage type: %3\$s\nGoal: %4\$s\nBrand: %5\$s\nTone of voice: %6\$s\n\nMeasured structure score: %7\$d of 100 (%8\$d words, %9\$d subheadings, %10\$d lists, %11\$d JSON LD blocks, %12\$d external links, %13\$d internal links).\n\nBody excerpt:\n%14\$s\n\nReturn a JSON object with these keys:\n- summary_long: summary of at most 60 words\n- summary_short: summary of at most 25 words\n- summary_balanced: summary of at most 40 words\n- suggested_meta_title: at most 60 characters\n- suggested_meta_description: at most 155 characters\n- faq_items: array of objects with question and answer\n- schema_jsonld: one JSON LD block as a string that fits this page type\n- extra_actions: array of objects with label and ok, only for actions that follow from the supplied text",
			$title,
			$context['keyword'],
			$context['page_type'],
			$context['goal'],
			$context['brand'],
			$context['tone'],
			(int) $score,
			(int) $signals['words'],
			(int) $signals['headings'],
			(int) $signals['lists'],
			(int) $signals['jsonld'],
			(int) $signals['external_links'],
			(int) $signals['internal_links'],
			self::cut( $text, 8000 )
		);

		$payload = array(
			'model'           => $model,
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			'temperature'     => 0.3,
			'response_format' => array( 'type' => 'json_object' ),
		);

		/**
		 * Filter the payload before it is sent.
		 *
		 * @param array  $payload Request payload.
		 * @param string $title   Page title.
		 * @param string $text    Plain text of the page.
		 * @param array  $context Page context.
		 * @param int    $score   Total score.
		 */
		$payload = apply_filters( 'theseo_ai_lm_payload', $payload, $title, $text, $context, $score );

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => (int) apply_filters( 'theseo_ai_lm_timeout', 30 ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->failure( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$detail  = '';
			$decoded = json_decode( $body, true );

			if ( is_array( $decoded ) && ! empty( $decoded['error']['message'] ) ) {
				$detail = ': ' . $decoded['error']['message'];
			}

			return $this->failure(
				sprintf(
					/* translators: 1: HTTP status code, 2: error detail from the API. */
					__( 'The model answered with status %1$d%2$s', 'theseo-ai-snippet-previewer' ),
					$code,
					$detail
				)
			);
		}

		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) || empty( $decoded['choices'][0]['message']['content'] ) ) {
			return $this->failure( __( 'The answer from the model could not be read.', 'theseo-ai-snippet-previewer' ) );
		}

		$lm_data = json_decode( $decoded['choices'][0]['message']['content'], true );

		if ( ! is_array( $lm_data ) ) {
			return $this->failure( __( 'The model did not return valid JSON.', 'theseo-ai-snippet-previewer' ) );
		}

		/**
		 * Filter the parsed model result.
		 *
		 * @param array  $lm_data Parsed result.
		 * @param string $title   Page title.
		 * @param string $text    Plain text of the page.
		 * @param array  $context Page context.
		 * @param int    $score   Total score.
		 */
		$lm_data = apply_filters( 'theseo_ai_lm_result', $lm_data, $title, $text, $context, $score );

		return array(
			'status'  => 'ok',
			'message' => sprintf(
				/* translators: %s: model name. */
				__( 'Answer received from %s.', 'theseo-ai-snippet-previewer' ),
				$model
			),
			'data'    => $this->clean( $lm_data ),
		);
	}

	/**
	 * Build a failure answer.
	 *
	 * @param string $message Error message.
	 * @return array
	 */
	protected function failure( $message ) {
		return array(
			'status'  => 'error',
			'message' => sprintf(
				/* translators: %s: error message. */
				__( 'The model was not reached, the measurement below is unchanged. %s', 'theseo-ai-snippet-previewer' ),
				wp_strip_all_tags( (string) $message )
			),
			'data'    => array(),
		);
	}

	/**
	 * Clean everything the model returned before it reaches the screen.
	 *
	 * @param array $raw Raw model output.
	 * @return array
	 */
	protected function clean( $raw ) {
		$out = array(
			'summary_long'               => '',
			'summary_short'              => '',
			'summary_balanced'           => '',
			'suggested_meta_title'       => '',
			'suggested_meta_description' => '',
			'faq_items'                  => array(),
			'schema_jsonld'              => '',
			'extra_actions'              => array(),
		);

		foreach ( array( 'summary_long', 'summary_short', 'summary_balanced' ) as $key ) {
			if ( ! empty( $raw[ $key ] ) && is_string( $raw[ $key ] ) ) {
				$out[ $key ] = self::cut( wp_strip_all_tags( $raw[ $key ] ), 1200 );
			}
		}

		if ( ! empty( $raw['suggested_meta_title'] ) && is_string( $raw['suggested_meta_title'] ) ) {
			$out['suggested_meta_title'] = self::cut( sanitize_text_field( $raw['suggested_meta_title'] ), 200 );
		}

		if ( ! empty( $raw['suggested_meta_description'] ) && is_string( $raw['suggested_meta_description'] ) ) {
			$out['suggested_meta_description'] = self::cut( sanitize_text_field( $raw['suggested_meta_description'] ), 400 );
		}

		if ( ! empty( $raw['faq_items'] ) && is_array( $raw['faq_items'] ) ) {
			foreach ( array_slice( $raw['faq_items'], 0, 10 ) as $item ) {
				if ( is_string( $item ) ) {
					$out['faq_items'][] = array(
						'question' => self::cut( sanitize_text_field( $item ), 300 ),
						'answer'   => '',
					);
					continue;
				}

				if ( ! is_array( $item ) || empty( $item['question'] ) ) {
					continue;
				}

				$out['faq_items'][] = array(
					'question' => self::cut( sanitize_text_field( (string) $item['question'] ), 300 ),
					'answer'   => isset( $item['answer'] ) ? self::cut( sanitize_text_field( (string) $item['answer'] ), 600 ) : '',
				);
			}
		}

		if ( ! empty( $raw['schema_jsonld'] ) ) {
			$schema = is_array( $raw['schema_jsonld'] ) ? wp_json_encode( $raw['schema_jsonld'] ) : (string) $raw['schema_jsonld'];
			$schema = wp_strip_all_tags( $schema );
			$parsed = json_decode( $schema, true );

			if ( is_array( $parsed ) ) {
				$schema = wp_json_encode( $parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}

			$out['schema_jsonld'] = self::cut( (string) $schema, 4000 );
		}

		if ( ! empty( $raw['extra_actions'] ) && is_array( $raw['extra_actions'] ) ) {
			foreach ( array_slice( $raw['extra_actions'], 0, 10 ) as $action ) {
				$label = '';

				if ( is_string( $action ) ) {
					$label = $action;
				} elseif ( is_array( $action ) && ! empty( $action['label'] ) ) {
					$label = (string) $action['label'];
				}

				if ( '' === trim( $label ) ) {
					continue;
				}

				$out['extra_actions'][] = array(
					'label' => self::cut( sanitize_text_field( $label ), 400 ),
					'ok'    => is_array( $action ) && ! empty( $action['ok'] ),
				);
			}
		}

		return $out;
	}

	/**
	 * Cut a string to a maximum length without breaking UTF-8.
	 *
	 * @param string $text   Input.
	 * @param int    $length Maximum length.
	 * @return string
	 */
	protected static function cut( $text, $length ) {
		$text = (string) $text;

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $length, 'UTF-8' );
		}

		return substr( $text, 0, $length );
	}
}
