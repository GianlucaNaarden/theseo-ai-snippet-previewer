<?php
/**
 * Measurement and scoring.
 *
 * Every point in the score comes from one measured signal with a fixed
 * maximum. The six maxima add up to 100, so the number can be recalculated
 * by hand from the table the plugin shows. There are no bonus points and no
 * hidden constants.
 *
 * @package TheSEO_AI_Snippet_Previewer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analyses a single post or page.
 */
class TheSEO_AI_Analyzer {

	/**
	 * Version of the scoring method. Stored with every measurement so old
	 * measurements are never compared to a newer method without saying so.
	 */
	const METHOD_VERSION = '2.0';

	/**
	 * Word count that earns the full length score.
	 */
	const FULL_LENGTH_WORDS = 800;

	/**
	 * Maximum points per signal. These six values add up to 100.
	 *
	 * @return array
	 */
	public static function max_points() {
		return array(
			'length'   => 25,
			'headings' => 20,
			'lists'    => 15,
			'schema'   => 20,
			'sources'  => 10,
			'internal' => 10,
		);
	}

	/**
	 * Run the full analysis for one post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	public function analyze( $post ) {
		$content_html = $this->rendered_content( $post );
		$text         = $this->plain_text( $content_html );
		$published    = $this->fetch_published_html( $post );

		$signals = $this->signals( $content_html, $text, $published );
		$score   = $this->score( $signals );

		return array(
			'signals' => $signals,
			'score'   => $score,
			'text'    => $text,
			'html'    => $content_html,
		);
	}

	/**
	 * Rendered content of the post, with the_content filters applied so
	 * blocks and shortcodes produce the same HTML a visitor gets.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function rendered_content( $post ) {
		global $wp_query;

		if ( isset( $wp_query ) && is_object( $wp_query ) ) {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );
		}

		$html = apply_filters( 'the_content', $post->post_content );

		if ( isset( $wp_query ) && is_object( $wp_query ) ) {
			wp_reset_postdata();
		}

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Plain text version of rendered HTML.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	public function plain_text( $html ) {
		// A space in front of every tag, otherwise the last word of a
		// paragraph and the first word of the next one become one word.
		$text = wp_strip_all_tags( str_replace( '<', ' <', (string) $html ) );
		$text = str_replace( array( '&nbsp;', "\xc2\xa0" ), ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = preg_replace( '/\s+([.,;:!?])/u', '$1', (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Count words in a UTF-8 safe way. str_word_count() only understands
	 * single byte letters and splits Dutch words with accents in two.
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	public function count_words( $text ) {
		if ( '' === $text ) {
			return 0;
		}

		$found = preg_match_all( '/[\p{L}\p{N}]+(?:[\'\x{2019}\-][\p{L}\p{N}]+)*/u', $text, $matches );

		if ( false === $found || null === $found ) {
			// Fall back only when the PCRE unicode engine is unavailable.
			return (int) str_word_count( $text );
		}

		return (int) $found;
	}

	/**
	 * Fetch the published page the way a crawler receives it. Used for the
	 * structured data signal, because most SEO plugins print JSON LD in the
	 * document head and never inside the post content.
	 *
	 * Returns null when the page is not publicly reachable or when the
	 * request fails. The caller must state which source was used.
	 *
	 * @param WP_Post $post Post object.
	 * @return string|null
	 */
	public function fetch_published_html( $post ) {
		if ( 'publish' !== get_post_status( $post ) ) {
			return null;
		}

		$url = get_permalink( $post );

		if ( ! $url ) {
			return null;
		}

		$cache_key = 'theseo_ai_page_' . md5( $url . '|' . $post->post_modified_gmt );
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) ) {
			return '' === $cached ? null : $cached;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => (int) apply_filters( 'theseo_ai_fetch_timeout', 10 ),
				'redirection' => 3,
				'user-agent'  => 'TheSEO AI Snippet Previewer/' . THESEO_AI_SNIPPET_PREVIEWER_VERSION . '; ' . home_url( '/' ),
				'headers'     => array( 'Accept' => 'text/html' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient( $cache_key, '', 5 * MINUTE_IN_SECONDS );

			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 || '' === $body ) {
			set_transient( $cache_key, '', 5 * MINUTE_IN_SECONDS );

			return null;
		}

		set_transient( $cache_key, $body, 5 * MINUTE_IN_SECONDS );

		return $body;
	}

	/**
	 * Measure every signal. Nothing is estimated here, everything is counted.
	 *
	 * @param string      $content_html Rendered post content.
	 * @param string      $text         Plain text of the content.
	 * @param string|null $published    Full published page HTML, or null.
	 * @return array
	 */
	public function signals( $content_html, $text, $published ) {
		$links = $this->links( $content_html );

		$schema_source = 'content';
		$schema_html   = $content_html;

		if ( is_string( $published ) && '' !== $published ) {
			$schema_source = 'published';
			$schema_html   = $published;
		}

		return array(
			'words'          => $this->count_words( $text ),
			'headings'       => $this->count_headings( $content_html ),
			'lists'          => $this->count_lists( $content_html ),
			'list_items'     => $this->count_matches( '/<li\b/i', $content_html ),
			'jsonld'         => $this->count_matches( '/<script[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>/i', $schema_html ),
			'schema_source'  => $schema_source,
			'external_links' => $links['external'],
			'internal_links' => $links['internal'],
			'heading_texts'  => $this->heading_texts( $content_html ),
		);
	}

	/**
	 * Count regex matches.
	 *
	 * @param string $pattern Regex.
	 * @param string $subject Subject.
	 * @return int
	 */
	protected function count_matches( $pattern, $subject ) {
		if ( ! is_string( $subject ) || '' === $subject ) {
			return 0;
		}

		$found = preg_match_all( $pattern, $subject, $matches );

		return $found ? (int) $found : 0;
	}

	/**
	 * Count second and third level headings.
	 *
	 * @param string $html Rendered content.
	 * @return int
	 */
	public function count_headings( $html ) {
		return $this->count_matches( '/<h[23]\b[^>]*>/i', $html );
	}

	/**
	 * Count unordered and ordered lists.
	 *
	 * @param string $html Rendered content.
	 * @return int
	 */
	public function count_lists( $html ) {
		return $this->count_matches( '/<(?:ul|ol)\b[^>]*>/i', $html );
	}

	/**
	 * Heading texts in document order, used for the structure extract.
	 *
	 * @param string $html Rendered content.
	 * @return array
	 */
	public function heading_texts( $html ) {
		$out = array();

		if ( ! is_string( $html ) || '' === $html ) {
			return $out;
		}

		if ( preg_match_all( '/<h([23])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$label = trim( wp_strip_all_tags( $match[2] ) );

				if ( '' === $label ) {
					continue;
				}

				$out[] = array(
					'level' => (int) $match[1],
					'text'  => $label,
				);
			}
		}

		return $out;
	}

	/**
	 * Split the links in the content into internal and external. Anchors,
	 * mail and telephone links are ignored because they are neither.
	 *
	 * @param string $html Rendered content.
	 * @return array
	 */
	public function links( $html ) {
		$result = array(
			'internal' => 0,
			'external' => 0,
		);

		if ( ! is_string( $html ) || '' === $html ) {
			return $result;
		}

		if ( ! preg_match_all( '/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches ) ) {
			return $result;
		}

		$home_host = $this->normalise_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		foreach ( $matches[1] as $href ) {
			$href = trim( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ) );

			if ( '' === $href || 0 === strpos( $href, '#' ) ) {
				continue;
			}

			if ( preg_match( '/^(mailto|tel|javascript|data):/i', $href ) ) {
				continue;
			}

			if ( 0 === strpos( $href, '/' ) || 0 === strpos( $href, '?' ) ) {
				++$result['internal'];
				continue;
			}

			$host = $this->normalise_host( (string) wp_parse_url( $href, PHP_URL_HOST ) );

			if ( '' === $host ) {
				++$result['internal'];
				continue;
			}

			if ( $host === $home_host ) {
				++$result['internal'];
			} else {
				++$result['external'];
			}
		}

		return $result;
	}

	/**
	 * Strip a leading www so example.com and www.example.com are one site.
	 *
	 * @param string $host Host name.
	 * @return string
	 */
	protected function normalise_host( $host ) {
		$host = strtolower( trim( (string) $host ) );

		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Turn the measured signals into points. Returns the total plus one row
	 * per signal, so the admin screen can print the full calculation.
	 *
	 * @param array $signals Measured signals.
	 * @return array
	 */
	public function score( $signals ) {
		$max   = self::max_points();
		$rows  = array();
		$words = (int) $signals['words'];

		$length_points = (int) round( min( 1, $words / self::FULL_LENGTH_WORDS ) * $max['length'] );

		$rows[] = array(
			'key'      => 'length',
			'label'    => __( 'Text length', 'theseo-ai-snippet-previewer' ),
			'measured' => sprintf(
				/* translators: %d: number of words. */
				_n( '%d word', '%d words', $words, 'theseo-ai-snippet-previewer' ),
				$words
			),
			'how'      => sprintf(
				/* translators: 1: maximum points, 2: word count needed for the maximum. */
				__( '%1$d points at %2$d words or more, proportional below that.', 'theseo-ai-snippet-previewer' ),
				$max['length'],
				self::FULL_LENGTH_WORDS
			),
			'points'   => $length_points,
			'max'      => $max['length'],
		);

		$headings        = (int) $signals['headings'];
		$heading_points  = 0;
		if ( $headings >= 3 ) {
			$heading_points = $max['headings'];
		} elseif ( $headings >= 1 ) {
			$heading_points = (int) round( $max['headings'] / 2 );
		}

		$rows[] = array(
			'key'      => 'headings',
			'label'    => __( 'Subheadings H2 and H3', 'theseo-ai-snippet-previewer' ),
			'measured' => sprintf(
				/* translators: %d: number of subheadings. */
				_n( '%d subheading', '%d subheadings', $headings, 'theseo-ai-snippet-previewer' ),
				$headings
			),
			'how'      => __( 'Nothing without a subheading, half for one or two, everything from three onwards.', 'theseo-ai-snippet-previewer' ),
			'points'   => $heading_points,
			'max'      => $max['headings'],
		);

		$lists       = (int) $signals['lists'];
		$list_points = $lists >= 1 ? $max['lists'] : 0;

		$rows[] = array(
			'key'      => 'lists',
			'label'    => __( 'Lists', 'theseo-ai-snippet-previewer' ),
			'measured' => sprintf(
				/* translators: 1: number of lists, 2: number of list items. */
				__( '%1$s with %2$s', 'theseo-ai-snippet-previewer' ),
				sprintf(
					/* translators: %d: number of lists. */
					_n( '%d list', '%d lists', $lists, 'theseo-ai-snippet-previewer' ),
					$lists
				),
				sprintf(
					/* translators: %d: number of list items. */
					_n( '%d item', '%d items', (int) $signals['list_items'], 'theseo-ai-snippet-previewer' ),
					(int) $signals['list_items']
				)
			),
			'how'      => __( 'All points from the first ul or ol in the content onwards.', 'theseo-ai-snippet-previewer' ),
			'points'   => $list_points,
			'max'      => $max['lists'],
		);

		$jsonld        = (int) $signals['jsonld'];
		$schema_points = $jsonld >= 1 ? $max['schema'] : 0;

		$schema_how = 'published' === $signals['schema_source']
			? __( 'Measured on the published page, so JSON LD from your SEO plugin counts as well.', 'theseo-ai-snippet-previewer' )
			: __( 'Measured on the content only, because the published page could not be fetched. JSON LD in the document head is not visible this way.', 'theseo-ai-snippet-previewer' );

		$rows[] = array(
			'key'      => 'schema',
			'label'    => __( 'Structured data JSON LD', 'theseo-ai-snippet-previewer' ),
			'measured' => sprintf(
				/* translators: %d: number of JSON LD blocks. */
				_n( '%d JSON LD block', '%d JSON LD blocks', $jsonld, 'theseo-ai-snippet-previewer' ),
				$jsonld
			),
			'how'      => $schema_how,
			'points'   => $schema_points,
			'max'      => $max['schema'],
		);

		$external       = (int) $signals['external_links'];
		$source_points  = 0;
		if ( $external >= 2 ) {
			$source_points = $max['sources'];
		} elseif ( 1 === $external ) {
			$source_points = (int) round( $max['sources'] / 2 );
		}

		$rows[] = array(
			'key'      => 'sources',
			'label'    => __( 'External sources', 'theseo-ai-snippet-previewer' ),
			'measured' => sprintf(
				/* translators: %d: number of external links. */
				_n( '%d external link', '%d external links', $external, 'theseo-ai-snippet-previewer' ),
				$external
			),
			'how'      => __( 'Half for one link to another domain, everything from two onwards.', 'theseo-ai-snippet-previewer' ),
			'points'   => $source_points,
			'max'      => $max['sources'],
		);

		$internal        = (int) $signals['internal_links'];
		$internal_points = 0;
		if ( $internal >= 3 ) {
			$internal_points = $max['internal'];
		} elseif ( $internal >= 1 ) {
			$internal_points = (int) round( $max['internal'] / 2 );
		}

		$rows[] = array(
			'key'      => 'internal',
			'label'    => __( 'Internal links', 'theseo-ai-snippet-previewer' ),
			'measured' => sprintf(
				/* translators: %d: number of internal links. */
				_n( '%d internal link', '%d internal links', $internal, 'theseo-ai-snippet-previewer' ),
				$internal
			),
			'how'      => __( 'Half for one or two links inside your own site, everything from three onwards.', 'theseo-ai-snippet-previewer' ),
			'points'   => $internal_points,
			'max'      => $max['internal'],
		);

		$total = 0;
		foreach ( $rows as $row ) {
			$total += (int) $row['points'];
		}

		return array(
			'total'          => (int) $total,
			'rows'           => $rows,
			'method_version' => self::METHOD_VERSION,
			'schema_source'  => $signals['schema_source'],
		);
	}

	/**
	 * Label for the page type, derived from words in the title. This is a
	 * guess based on the title and nothing else, so the admin screen says so.
	 *
	 * @param string $title Post title.
	 * @return string
	 */
	public function target_label( $title ) {
		$title_low = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );

		$map = array(
			'guide'     => 'guide',
			'tutorial'  => 'guide',
			'handleid'  => 'guide',
			'gids'      => 'guide',
			'stappen'   => 'guide',
			'faq'       => 'qa',
			'question'  => 'qa',
			'vraag'     => 'qa',
			'veelgeste' => 'qa',
			'case'      => 'case',
			'result'    => 'case',
			'resultaat' => 'case',
			'klantverh' => 'case',
		);

		$type = 'general';

		foreach ( $map as $needle => $candidate ) {
			if ( false !== strpos( $title_low, $needle ) ) {
				$type = $candidate;
				break;
			}
		}

		switch ( $type ) {
			case 'guide':
				return __( 'Guide or tutorial', 'theseo-ai-snippet-previewer' );
			case 'qa':
				return __( 'Q and A page', 'theseo-ai-snippet-previewer' );
			case 'case':
				return __( 'Case study or results page', 'theseo-ai-snippet-previewer' );
		}

		return __( 'General page', 'theseo-ai-snippet-previewer' );
	}

	/**
	 * Three literal extracts from the page. These are quotes from your own
	 * content, not summaries and not model output.
	 *
	 * @param string $text    Plain text of the content.
	 * @param string $html    Rendered content.
	 * @param array  $signals Measured signals.
	 * @return array
	 */
	public function extracts( $text, $html, $signals ) {
		$opening = '' === $text
			? __( 'This page has no text content.', 'theseo-ai-snippet-previewer' )
			: wp_trim_words( $text, 45, '...' );

		$items = array();
		if ( preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $item ) {
				$item = trim( wp_strip_all_tags( $item ) );

				if ( '' === $item ) {
					continue;
				}

				$items[] = '- ' . wp_trim_words( $item, 25, '...' );

				if ( count( $items ) >= 4 ) {
					break;
				}
			}
		}

		$list_extract = empty( $items )
			? __( 'There is no list on this page, so there is no block that can be quoted as a whole.', 'theseo-ai-snippet-previewer' )
			: implode( "\n", $items );

		$headings = array();
		foreach ( $signals['heading_texts'] as $heading ) {
			$headings[] = ( 3 === (int) $heading['level'] ? '   ' : '' ) . 'H' . $heading['level'] . ' ' . $heading['text'];

			if ( count( $headings ) >= 8 ) {
				break;
			}
		}

		$structure_extract = empty( $headings )
			? __( 'There are no subheadings, so the page is one block without an outline.', 'theseo-ai-snippet-previewer' )
			: implode( "\n", $headings );

		return array(
			'opening'   => $opening,
			'list'      => $list_extract,
			'structure' => $structure_extract,
		);
	}

	/**
	 * Checklist rows: one measured fact per line.
	 *
	 * @param array $signals Measured signals.
	 * @return array
	 */
	public function checklist( $signals ) {
		$rows = array();

		$rows[] = array(
			'label' => $signals['headings'] >= 1
				? __( 'Subheading present, the page has an outline', 'theseo-ai-snippet-previewer' )
				: __( 'No H2 or H3 in the content yet', 'theseo-ai-snippet-previewer' ),
			'ok'    => $signals['headings'] >= 1,
		);

		$rows[] = array(
			'label' => $signals['lists'] >= 1
				? __( 'List present, that is a block a model can quote as a whole', 'theseo-ai-snippet-previewer' )
				: __( 'No list, so there is no separately quotable block', 'theseo-ai-snippet-previewer' ),
			'ok'    => $signals['lists'] >= 1,
		);

		$rows[] = array(
			'label' => $signals['jsonld'] >= 1
				? __( 'JSON LD present on this page', 'theseo-ai-snippet-previewer' )
				: __( 'No JSON LD found on this page', 'theseo-ai-snippet-previewer' ),
			'ok'    => $signals['jsonld'] >= 1,
		);

		$rows[] = array(
			'label' => $signals['external_links'] >= 1
				? __( 'External source present, claims can be checked', 'theseo-ai-snippet-previewer' )
				: __( 'No external source, nothing on this page can be verified', 'theseo-ai-snippet-previewer' ),
			'ok'    => $signals['external_links'] >= 1,
		);

		$rows[] = array(
			'label' => $signals['internal_links'] >= 1
				? __( 'Internal link present, the page is part of your site', 'theseo-ai-snippet-previewer' )
				: __( 'No internal link, the page stands on its own', 'theseo-ai-snippet-previewer' ),
			'ok'    => $signals['internal_links'] >= 1,
		);

		$rows[] = array(
			'label' => $signals['words'] >= 600
				? __( 'Enough text for a full answer', 'theseo-ai-snippet-previewer' )
				: __( 'Short text, extra context helps', 'theseo-ai-snippet-previewer' ),
			'ok'    => $signals['words'] >= 600,
		);

		return $rows;
	}

	/**
	 * Suggestions, each one tied to a signal that scored below its maximum.
	 *
	 * @param array  $signals      Measured signals.
	 * @param string $target_label Page type label.
	 * @return array
	 */
	public function actions( $signals, $target_label ) {
		$actions = array();

		if ( $signals['headings'] < 3 ) {
			$actions[] = array(
				'label' => __( 'Split the page into at least three sections with H2 headings that name the subject of the section.', 'theseo-ai-snippet-previewer' ),
				'ok'    => false,
			);
		}

		if ( $signals['lists'] < 1 ) {
			$actions[] = array(
				'label' => __( 'Turn the key points into a list. A list item is a block that can be quoted without the rest of the page.', 'theseo-ai-snippet-previewer' ),
				'ok'    => false,
			);
		}

		if ( $signals['jsonld'] < 1 ) {
			if ( __( 'Q and A page', 'theseo-ai-snippet-previewer' ) === $target_label ) {
				$actions[] = array(
					'label' => __( 'Add FAQPage JSON LD for the questions and answers on this page.', 'theseo-ai-snippet-previewer' ),
					'ok'    => false,
				);
			} elseif ( __( 'Guide or tutorial', 'theseo-ai-snippet-previewer' ) === $target_label ) {
				$actions[] = array(
					'label' => __( 'Add Article or HowTo JSON LD that describes the steps of this guide.', 'theseo-ai-snippet-previewer' ),
					'ok'    => false,
				);
			} else {
				$actions[] = array(
					'label' => __( 'Add JSON LD that fits this page type, through your SEO plugin or as a code block.', 'theseo-ai-snippet-previewer' ),
					'ok'    => false,
				);
			}
		}

		if ( $signals['external_links'] < 2 ) {
			$actions[] = array(
				'label' => __( 'Link to two sources that can be checked, so the numbers on this page have a place to come from.', 'theseo-ai-snippet-previewer' ),
				'ok'    => false,
			);
		}

		if ( $signals['internal_links'] < 3 ) {
			$actions[] = array(
				'label' => __( 'Link to three pages of your own that go deeper into a part of this subject.', 'theseo-ai-snippet-previewer' ),
				'ok'    => false,
			);
		}

		if ( $signals['words'] < self::FULL_LENGTH_WORDS ) {
			$actions[] = array(
				'label' => sprintf(
					/* translators: 1: current word count, 2: word count that earns the full length score. */
					__( 'The page has %1$d words. From %2$d words onwards the length signal is complete.', 'theseo-ai-snippet-previewer' ),
					(int) $signals['words'],
					self::FULL_LENGTH_WORDS
				),
				'ok'    => false,
			);
		}

		if ( empty( $actions ) ) {
			$actions[] = array(
				'label' => __( 'Every measured signal is at its maximum. What is left is keeping the page up to date and checking whether the facts are still correct.', 'theseo-ai-snippet-previewer' ),
				'ok'    => true,
			);
		}

		return $actions;
	}
}
