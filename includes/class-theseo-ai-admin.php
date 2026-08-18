<?php
/**
 * Admin screen, settings and the two ajax endpoints.
 *
 * @package TheSEO_AI_Snippet_Previewer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the plugin shows inside wp-admin.
 */
class TheSEO_AI_Admin {

	/**
	 * Menu slug.
	 */
	const PAGE_SLUG = 'theseo-ai-snippet-previewer';

	/**
	 * Option that holds the settings.
	 */
	const OPTION = 'theseo_ai_settings';

	/**
	 * Post meta with the last measurements.
	 */
	const META_HISTORY = '_theseo_ai_history';

	/**
	 * Post meta with the page context filled in by the editor.
	 */
	const META_CONTEXT = '_theseo_ai_context';

	/**
	 * Number of measurements kept per page.
	 */
	const HISTORY_LIMIT = 10;

	/**
	 * Capability needed for the screen and both endpoints.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Analyzer.
	 *
	 * @var TheSEO_AI_Analyzer
	 */
	protected $analyzer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->analyzer = new TheSEO_AI_Analyzer();
	}

	/**
	 * Hook everything up.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_theseo_ai_snippet_analyze', array( $this, 'ajax_analyze' ) );
		add_action( 'wp_ajax_theseo_ai_snippet_load', array( $this, 'ajax_load' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( THESEO_AI_SNIPPET_PREVIEWER_FILE ),
			array( $this, 'plugin_action_links' )
		);
	}

	/**
	 * Link to the screen from the plugin list.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Open screen', 'theseo-ai-snippet-previewer' )
		);

		array_unshift( $links, $link );

		return $links;
	}

	/**
	 * Menu entry.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'AI snippet preview', 'theseo-ai-snippet-previewer' ),
			__( 'AI snippet preview', 'theseo-ai-snippet-previewer' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-visibility',
			81
		);
	}

	/**
	 * Register the settings group.
	 */
	public function register_settings() {
		register_setting(
			'theseo_ai_snippet_previewer',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Clean the settings. An empty key field keeps the stored key, because
	 * the field is never prefilled and saving the form would otherwise wipe
	 * the key every time another setting changes.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();

		$output = array( 'provider' => 'openai' );

		$posted_key = isset( $input['openai_api_key'] ) ? trim( (string) $input['openai_api_key'] ) : '';
		$remove_key = ! empty( $input['remove_api_key'] );

		$stored_key = isset( $current['openai_api_key'] ) ? (string) $current['openai_api_key'] : '';

		if ( $remove_key ) {
			$output['openai_api_key'] = '';
		} elseif ( '' === $posted_key ) {
			$output['openai_api_key'] = $stored_key;
		} elseif ( preg_match( '/^[A-Za-z0-9._\-]{20,200}$/', $posted_key ) ) {
			$output['openai_api_key'] = $posted_key;
		} else {
			// Never silently repair a key: a mangled key gives an error that
			// is impossible to trace back to this field.
			$output['openai_api_key'] = $stored_key;

			add_settings_error(
				self::OPTION,
				'theseo_ai_key',
				__( 'That does not look like an API key, so nothing was changed. A key consists of letters, digits, dots, dashes and underscores.', 'theseo-ai-snippet-previewer' ),
				'error'
			);
		}

		$model = isset( $input['openai_model'] ) ? sanitize_text_field( $input['openai_model'] ) : '';
		$model = preg_replace( '/[^A-Za-z0-9._:\-]/', '', (string) $model );

		$output['openai_model'] = '' !== $model ? substr( $model, 0, 64 ) : 'gpt-4o-mini';

		$language  = isset( $input['default_language'] ) ? sanitize_text_field( $input['default_language'] ) : 'auto';
		$languages = array_keys( $this->languages() );

		$output['default_language'] = in_array( $language, $languages, true ) ? $language : 'auto';

		return $output;
	}

	/**
	 * Languages the model may answer in.
	 *
	 * @return array
	 */
	public function languages() {
		return array(
			'auto' => __( 'Detect from site language', 'theseo-ai-snippet-previewer' ),
			'en'   => __( 'English', 'theseo-ai-snippet-previewer' ),
			'nl'   => __( 'Dutch', 'theseo-ai-snippet-previewer' ),
			'de'   => __( 'German', 'theseo-ai-snippet-previewer' ),
			'fr'   => __( 'French', 'theseo-ai-snippet-previewer' ),
		);
	}

	/**
	 * Page types that can be chosen per page.
	 *
	 * @return array
	 */
	public function page_types() {
		return array(
			'auto'    => __( 'Derive from the title', 'theseo-ai-snippet-previewer' ),
			'landing' => __( 'Landing page', 'theseo-ai-snippet-previewer' ),
			'guide'   => __( 'Guide or tutorial', 'theseo-ai-snippet-previewer' ),
			'blog'    => __( 'Blog article', 'theseo-ai-snippet-previewer' ),
			'qa'      => __( 'Q and A page', 'theseo-ai-snippet-previewer' ),
			'case'    => __( 'Case study or results page', 'theseo-ai-snippet-previewer' ),
		);
	}

	/**
	 * Goals that can be chosen per page.
	 *
	 * @return array
	 */
	public function goals() {
		return array(
			'traffic'    => __( 'More organic traffic', 'theseo-ai-snippet-previewer' ),
			'leads'      => __( 'More leads', 'theseo-ai-snippet-previewer' ),
			'ai'         => __( 'Being used in AI answers', 'theseo-ai-snippet-previewer' ),
			'conversion' => __( 'Conversion on this page', 'theseo-ai-snippet-previewer' ),
		);
	}

	/**
	 * Post types that can be analysed.
	 *
	 * @return array
	 */
	public function post_types() {
		$types = apply_filters( 'theseo_ai_post_types', array( 'post', 'page' ) );

		return array_values( array_filter( array_map( 'sanitize_key', (array) $types ) ) );
	}

	/**
	 * Load the stylesheet and script on our own screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'theseo-ai-snippet-previewer',
			THESEO_AI_SNIPPET_PREVIEWER_URL . 'assets/css/admin.css',
			array(),
			THESEO_AI_SNIPPET_PREVIEWER_VERSION
		);

		wp_enqueue_script(
			'theseo-ai-snippet-previewer',
			THESEO_AI_SNIPPET_PREVIEWER_URL . 'assets/js/admin.js',
			array(),
			THESEO_AI_SNIPPET_PREVIEWER_VERSION,
			true
		);

		wp_localize_script(
			'theseo-ai-snippet-previewer',
			'theseoAiData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'theseo_ai_nonce' ),
				'i18n'    => array(
					'choosePost'   => __( 'Please select a page or post first', 'theseo-ai-snippet-previewer' ),
					'errorGeneric' => __( 'Something went wrong while running the analysis.', 'theseo-ai-snippet-previewer' ),
					'analyzing'    => __( 'Analyzing', 'theseo-ai-snippet-previewer' ),
					'runAnalysis'  => __( 'Run analysis', 'theseo-ai-snippet-previewer' ),
					'noIssues'     => __( 'No clear shortcomings found', 'theseo-ai-snippet-previewer' ),
					'noActions'    => __( 'No additional suggestions found', 'theseo-ai-snippet-previewer' ),
					'noFaq'        => __( 'No FAQ suggestions from the model yet.', 'theseo-ai-snippet-previewer' ),
					'noHistory'    => __( 'No earlier measurement for this page yet.', 'theseo-ai-snippet-previewer' ),
					'copied'       => __( 'Copied', 'theseo-ai-snippet-previewer' ),
					'copyFailed'   => __( 'Copying failed, select the text yourself.', 'theseo-ai-snippet-previewer' ),
					'copyPrompt'   => __( 'Copy prompt', 'theseo-ai-snippet-previewer' ),
					'total'        => __( 'Total', 'theseo-ai-snippet-previewer' ),
				),
			)
		);
	}

	/**
	 * Read the page context from a request.
	 *
	 * @param array $source Raw request data.
	 * @return array
	 */
	protected function read_context( $source ) {
		$page_types = array_keys( $this->page_types() );
		$goals      = array_keys( $this->goals() );

		$keyword = isset( $source['keyword'] ) ? sanitize_text_field( wp_unslash( $source['keyword'] ) ) : '';
		$brand   = isset( $source['brand'] ) ? sanitize_text_field( wp_unslash( $source['brand'] ) ) : '';
		$tone    = isset( $source['tone'] ) ? sanitize_text_field( wp_unslash( $source['tone'] ) ) : '';

		$page_type = isset( $source['page_type'] ) ? sanitize_key( wp_unslash( $source['page_type'] ) ) : 'auto';
		$goal      = isset( $source['goal'] ) ? sanitize_key( wp_unslash( $source['goal'] ) ) : 'traffic';

		return array(
			'keyword'   => substr( $keyword, 0, 120 ),
			'brand'     => substr( $brand, 0, 200 ),
			'tone'      => substr( $tone, 0, 120 ),
			'page_type' => in_array( $page_type, $page_types, true ) ? $page_type : 'auto',
			'goal'      => in_array( $goal, $goals, true ) ? $goal : 'traffic',
		);
	}

	/**
	 * Turn the stored context into readable labels for the prompt.
	 *
	 * @param array  $context      Stored context.
	 * @param string $target_label Label derived from the title.
	 * @return array
	 */
	protected function context_labels( $context, $target_label ) {
		$page_types = $this->page_types();
		$goals      = $this->goals();

		$type_label = 'auto' === $context['page_type']
			? $target_label
			: $page_types[ $context['page_type'] ];

		return array(
			'keyword'   => $context['keyword'],
			'brand'     => $context['brand'],
			'tone'      => $context['tone'],
			'page_type' => $type_label,
			'goal'      => isset( $goals[ $context['goal'] ] ) ? $goals[ $context['goal'] ] : reset( $goals ),
		);
	}

	/**
	 * Stored history for a post, formatted for the screen.
	 *
	 * @param int $post_id Post id.
	 * @return array
	 */
	protected function history( $post_id ) {
		$stored = get_post_meta( $post_id, self::META_HISTORY, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$rows   = array();
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['score'] ) ) {
				continue;
			}

			$rows[] = array(
				'date'   => wp_date( $format, (int) $entry['time'] ),
				'score'  => (int) $entry['score'],
				'method' => isset( $entry['method'] ) ? (string) $entry['method'] : '',
				'words'  => isset( $entry['words'] ) ? (int) $entry['words'] : 0,
			);
		}

		// Newest first, with the change against the previous measurement.
		$count = count( $rows );

		for ( $i = 0; $i < $count; $i++ ) {
			$next                = isset( $rows[ $i + 1 ] ) ? $rows[ $i + 1 ]['score'] : null;
			$rows[ $i ]['delta'] = null === $next ? null : $rows[ $i ]['score'] - $next;
		}

		return $rows;
	}

	/**
	 * Store one measurement.
	 *
	 * @param int   $post_id Post id.
	 * @param array $score   Score array.
	 * @param array $signals Measured signals.
	 */
	protected function store_measurement( $post_id, $score, $signals ) {
		$stored = get_post_meta( $post_id, self::META_HISTORY, true );
		$stored = is_array( $stored ) ? $stored : array();

		$points = array();
		foreach ( $score['rows'] as $row ) {
			$points[ $row['key'] ] = (int) $row['points'];
		}

		array_unshift(
			$stored,
			array(
				'time'   => time(),
				'score'  => (int) $score['total'],
				'method' => (string) $score['method_version'],
				'words'  => (int) $signals['words'],
				'points' => $points,
				'user'   => get_current_user_id(),
			)
		);

		update_post_meta( $post_id, self::META_HISTORY, array_slice( $stored, 0, self::HISTORY_LIMIT ) );
	}

	/**
	 * Shared guard for both endpoints.
	 *
	 * @return WP_Post|void Sends a json error and stops on failure.
	 */
	protected function guarded_post() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( __( 'No access', 'theseo-ai-snippet-previewer' ), 403 );
		}

		check_ajax_referer( 'theseo_ai_nonce', '_wpnonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid page', 'theseo-ai-snippet-previewer' ), 400 );
		}

		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, $this->post_types(), true ) ) {
			wp_send_json_error( __( 'Page not found', 'theseo-ai-snippet-previewer' ), 404 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'No access to this page', 'theseo-ai-snippet-previewer' ), 403 );
		}

		return $post;
	}

	/**
	 * Return the saved context and history when a page is selected.
	 */
	public function ajax_load() {
		$post = $this->guarded_post();

		$context = get_post_meta( $post->ID, self::META_CONTEXT, true );
		$context = is_array( $context ) ? $this->read_context( $context ) : $this->read_context( array() );

		wp_send_json_success(
			array(
				'context' => $context,
				'history' => $this->history( $post->ID ),
			)
		);
	}

	/**
	 * Run the analysis.
	 */
	public function ajax_analyze() {
		$post = $this->guarded_post();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guarded_post().
		$context = $this->read_context( $_POST );

		update_post_meta( $post->ID, self::META_CONTEXT, $context );

		$analysis = $this->analyzer->analyze( $post );
		$signals  = $analysis['signals'];
		$score    = $analysis['score'];

		$title        = get_the_title( $post );
		$target_label = $this->analyzer->target_label( $title );
		$labels       = $this->context_labels( $context, $target_label );

		$this->store_measurement( $post->ID, $score, $signals );

		$settings = get_option( self::OPTION, array() );
		$model    = new TheSEO_AI_Language_Model( is_array( $settings ) ? $settings : array() );

		$prompt = TheSEO_AI_Language_Model::build_prompt( $title, $labels, $signals, $score['total'], $analysis['text'] );
		$lm     = $model->suggest( $title, $analysis['text'], $labels, $signals, $score['total'] );

		$actions = $this->analyzer->actions( $signals, $target_label );

		if ( 'ok' === $lm['status'] && ! empty( $lm['data']['extra_actions'] ) ) {
			foreach ( $lm['data']['extra_actions'] as $extra ) {
				$actions[] = array(
					'label' => $extra['label'],
					'ok'    => (bool) $extra['ok'],
				);
			}
		}

		wp_send_json_success(
			array(
				'score'        => (int) $score['total'],
				'method'       => $score['rows'],
				'methodNote'   => 'published' === $score['schema_source']
					? __( 'Measured on the published page and on the content of the editor.', 'theseo-ai-snippet-previewer' )
					: __( 'Measured on the content of the editor only, the published page could not be fetched.', 'theseo-ai-snippet-previewer' ),
				'targetLabel'  => $labels['page_type'],
				'checklist'    => $this->analyzer->checklist( $signals ),
				'actions'      => $actions,
				'extracts'     => $this->analyzer->extracts( $analysis['text'], $analysis['html'], $signals ),
				'history'      => $this->history( $post->ID ),
				'prompt'       => $prompt,
				'model'        => array(
					'status'  => $lm['status'],
					'message' => $lm['message'],
					'data'    => $lm['data'],
				),
			)
		);
	}

	/**
	 * The screen itself.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'        => $this->post_types(),
				'posts_per_page'   => 200,
				'post_status'      => array( 'publish', 'private' ),
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$settings   = get_option( self::OPTION, array() );
		$settings   = is_array( $settings ) ? $settings : array();
		$key_source = TheSEO_AI_Language_Model::key_source( $settings );
		$has_key    = 'none' !== $key_source;
		$model      = isset( $settings['openai_model'] ) ? $settings['openai_model'] : 'gpt-4o-mini';
		$lang       = isset( $settings['default_language'] ) ? $settings['default_language'] : 'auto';
		$max_points = TheSEO_AI_Analyzer::max_points();

		$permalink_structure = get_option( 'permalink_structure' );

		$site_checks = array(
			array(
				'ok'        => ! empty( $permalink_structure ),
				'label_ok'  => __( 'Pretty permalinks are enabled', 'theseo-ai-snippet-previewer' ),
				'label_nok' => __( 'Enable pretty permalinks under Settings Permalinks, the plain query string form is harder to read for people and for crawlers.', 'theseo-ai-snippet-previewer' ),
			),
			array(
				'ok'        => file_exists( ABSPATH . 'llms.txt' ),
				'label_ok'  => __( 'There is an llms.txt file in the site root', 'theseo-ai-snippet-previewer' ),
				'label_nok' => __( 'There is no llms.txt file in the site root. That file is a proposal, not a standard, and no crawler is obliged to read it.', 'theseo-ai-snippet-previewer' ),
			),
			array(
				'ok'        => file_exists( ABSPATH . 'robots.txt' ),
				'label_ok'  => __( 'There is a physical robots.txt file in the site root', 'theseo-ai-snippet-previewer' ),
				'label_nok' => __( 'There is no physical robots.txt file. WordPress then serves a virtual one, so check the address itself before you conclude anything.', 'theseo-ai-snippet-previewer' ),
			),
		);
		?>
		<div class="tseo-ai-wrap">
			<?php settings_errors( self::OPTION ); ?>
			<div class="tseo-ai-shell">
				<header class="tseo-ai-header">
					<div>
						<h1 class="tseo-ai-title">
							<?php esc_html_e( 'AI snippet preview and structure score', 'theseo-ai-snippet-previewer' ); ?>
						</h1>
						<p class="tseo-ai-subtitle">
							<?php esc_html_e( 'Measures which parts of a page can be quoted separately by a language model, and turns that into a score you can recalculate yourself. The extracts are literal quotes from your own page, not model output.', 'theseo-ai-snippet-previewer' ); ?>
						</p>
					</div>
					<div>
						<div class="tseo-ai-pill">
							<span class="tseo-ai-pill-dot"></span>
							<span>
								<?php
								printf(
									/* translators: %s: method version. */
									esc_html__( 'Method %s', 'theseo-ai-snippet-previewer' ),
									esc_html( TheSEO_AI_Analyzer::METHOD_VERSION )
								);
								?>
							</span>
						</div>
					</div>
				</header>

				<div class="tseo-ai-grid">
					<div class="tseo-ai-card">
						<div class="tseo-ai-card-inner">
							<h3>
								<?php esc_html_e( 'Page and context', 'theseo-ai-snippet-previewer' ); ?>
								<span class="tseo-help">
									?
									<span class="tseo-help-tooltip">
										<?php esc_html_e( 'The context is not part of the score. It is used for the prompt and, if you connected a model, for the request to that model. It is remembered per page.', 'theseo-ai-snippet-previewer' ); ?>
									</span>
								</span>
							</h3>

							<form id="tseo-ai-form">
								<div class="tseo-ai-form-row">
									<select id="tseo-ai-post-select" class="tseo-ai-select">
										<option value=""><?php esc_html_e( 'Select a page or post', 'theseo-ai-snippet-previewer' ); ?></option>
										<?php foreach ( $posts as $item ) : ?>
											<option value="<?php echo esc_attr( $item->ID ); ?>">
												<?php echo esc_html( '' !== $item->post_title ? $item->post_title : __( '(no title)', 'theseo-ai-snippet-previewer' ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>

									<button id="tseo-ai-analyze-btn" class="tseo-ai-button" type="submit">
										<?php esc_html_e( 'Run analysis', 'theseo-ai-snippet-previewer' ); ?>
									</button>
								</div>

								<div class="tseo-ai-context-grid">
									<div class="tseo-ai-field-row">
										<label for="tseo-ai-keyword"><?php esc_html_e( 'Main search term', 'theseo-ai-snippet-previewer' ); ?></label>
										<input id="tseo-ai-keyword" type="text" class="tseo-ai-input" autocomplete="off">
									</div>
									<div class="tseo-ai-field-row">
										<label for="tseo-ai-page-type"><?php esc_html_e( 'Page type', 'theseo-ai-snippet-previewer' ); ?></label>
										<select id="tseo-ai-page-type" class="tseo-ai-input">
											<?php foreach ( $this->page_types() as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="tseo-ai-field-row">
										<label for="tseo-ai-goal"><?php esc_html_e( 'Goal of the page', 'theseo-ai-snippet-previewer' ); ?></label>
										<select id="tseo-ai-goal" class="tseo-ai-input">
											<?php foreach ( $this->goals() as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="tseo-ai-field-row">
										<label for="tseo-ai-tone"><?php esc_html_e( 'Tone of voice', 'theseo-ai-snippet-previewer' ); ?></label>
										<input id="tseo-ai-tone" type="text" class="tseo-ai-input" autocomplete="off">
									</div>
								</div>

								<div class="tseo-ai-field-row">
									<label for="tseo-ai-brand"><?php esc_html_e( 'Brand in one line', 'theseo-ai-snippet-previewer' ); ?></label>
									<input id="tseo-ai-brand" type="text" class="tseo-ai-input" autocomplete="off">
								</div>
							</form>

							<div class="tseo-ai-score-wrap">
								<div class="tseo-ai-score-main">
									<div class="tseo-ai-score-circle" id="tseo-ai-score-value">0</div>
									<div>
										<div class="tseo-ai-score-label">
											<?php esc_html_e( 'Structure score out of 100, added up from six measured signals', 'theseo-ai-snippet-previewer' ); ?>
										</div>
										<div class="tseo-ai-meta">
											<span class="tseo-ai-badge">
												<?php esc_html_e( 'Page type', 'theseo-ai-snippet-previewer' ); ?>:
												<span id="tseo-ai-meta-target"><?php esc_html_e( 'General page', 'theseo-ai-snippet-previewer' ); ?></span>
											</span>
											<span class="tseo-help">
												?
												<span class="tseo-help-tooltip">
													<?php esc_html_e( 'The score says how well this page can be quoted in parts. It says nothing about ranking, about traffic or about whether a model will actually use your page.', 'theseo-ai-snippet-previewer' ); ?>
												</span>
											</span>
										</div>
									</div>
								</div>
								<div class="tseo-ai-score-bar">
									<div class="tseo-ai-score-bar-fill" id="tseo-ai-score-bar-fill"></div>
								</div>
							</div>

							<div class="tseo-ai-checklist">
								<div style="display:flex;align-items:center;gap:6px;">
									<strong style="font-size:12px;"><?php esc_html_e( 'How the score is built up', 'theseo-ai-snippet-previewer' ); ?></strong>
									<span class="tseo-help">
										?
										<span class="tseo-help-tooltip">
											<?php esc_html_e( 'Every row shows what was counted, the rule that turns it into points, and the maximum. The six maxima add up to one hundred, so you can check the total by hand.', 'theseo-ai-snippet-previewer' ); ?>
										</span>
									</span>
								</div>
								<table class="tseo-ai-method">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Signal', 'theseo-ai-snippet-previewer' ); ?></th>
											<th><?php esc_html_e( 'Measured', 'theseo-ai-snippet-previewer' ); ?></th>
											<th class="tseo-ai-num"><?php esc_html_e( 'Points', 'theseo-ai-snippet-previewer' ); ?></th>
										</tr>
									</thead>
									<tbody id="tseo-ai-method-body">
										<?php foreach ( $max_points as $key => $points ) : ?>
											<tr>
												<td><?php echo esc_html( $this->signal_label( $key ) ); ?></td>
												<td><?php esc_html_e( 'not measured yet', 'theseo-ai-snippet-previewer' ); ?></td>
												<td class="tseo-ai-num"><?php echo esc_html( '0 / ' . $points ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
									<tfoot>
										<tr class="tseo-ai-total">
											<td colspan="2"><?php esc_html_e( 'Total', 'theseo-ai-snippet-previewer' ); ?></td>
											<td class="tseo-ai-num" id="tseo-ai-method-total">0 / 100</td>
										</tr>
									</tfoot>
								</table>
								<span class="tseo-ai-source" id="tseo-ai-method-note"></span>
							</div>

							<div class="tseo-ai-checklist">
								<div style="display:flex;align-items:center;gap:6px;">
									<strong style="font-size:12px;"><?php esc_html_e( 'Key focus points', 'theseo-ai-snippet-previewer' ); ?></strong>
								</div>
								<ul id="tseo-ai-checklist-list">
									<li class="tseo-ok"><?php esc_html_e( 'No analysis has been run yet', 'theseo-ai-snippet-previewer' ); ?></li>
								</ul>
							</div>

							<div class="tseo-ai-checklist">
								<div style="display:flex;align-items:center;gap:6px;">
									<strong style="font-size:12px;"><?php esc_html_e( 'Suggestions', 'theseo-ai-snippet-previewer' ); ?></strong>
									<span class="tseo-help">
										?
										<span class="tseo-help-tooltip">
											<?php esc_html_e( 'Every suggestion belongs to a signal that scored below its maximum. When a model is connected its own suggestions are added below these.', 'theseo-ai-snippet-previewer' ); ?>
										</span>
									</span>
								</div>
								<ul id="tseo-ai-actions-list">
									<li class="tseo-ok"><?php esc_html_e( 'No analysis has been run yet', 'theseo-ai-snippet-previewer' ); ?></li>
								</ul>
							</div>

							<div class="tseo-ai-checklist">
								<div style="display:flex;align-items:center;gap:6px;">
									<strong style="font-size:12px;"><?php esc_html_e( 'Earlier measurements of this page', 'theseo-ai-snippet-previewer' ); ?></strong>
									<span class="tseo-help">
										?
										<span class="tseo-help-tooltip">
											<?php esc_html_e( 'The last ten measurements are stored with the page. They are removed when you delete the plugin.', 'theseo-ai-snippet-previewer' ); ?>
										</span>
									</span>
								</div>
								<ul class="tseo-ai-history" id="tseo-ai-history-list">
									<li><span><?php esc_html_e( 'No earlier measurement for this page yet.', 'theseo-ai-snippet-previewer' ); ?></span><span></span></li>
								</ul>
							</div>
						</div>
					</div>

					<div class="tseo-ai-card">
						<div class="tseo-ai-card-inner">
							<h3>
								<?php esc_html_e( 'What can be quoted from this page', 'theseo-ai-snippet-previewer' ); ?>
								<span class="tseo-help">
									?
									<span class="tseo-help-tooltip">
										<?php esc_html_e( 'These three blocks are literal text from your own page. They are not summaries and not model output, so nothing here is invented.', 'theseo-ai-snippet-previewer' ); ?>
									</span>
								</span>
							</h3>

							<div class="tseo-ai-cols">
								<div class="tseo-ai-col-card">
									<div class="tseo-ai-col-label">
										<strong><?php esc_html_e( 'Opening', 'theseo-ai-snippet-previewer' ); ?></strong>
									</div>
									<div id="tseo-ai-extract-opening" class="tseo-ai-extract">
										<?php esc_html_e( 'The first words of the page appear here after the analysis.', 'theseo-ai-snippet-previewer' ); ?>
									</div>
								</div>

								<div class="tseo-ai-col-card">
									<div class="tseo-ai-col-label">
										<strong><?php esc_html_e( 'List items', 'theseo-ai-snippet-previewer' ); ?></strong>
									</div>
									<div id="tseo-ai-extract-list" class="tseo-ai-extract">
										<?php esc_html_e( 'The list items of the page appear here, the blocks that can be quoted whole.', 'theseo-ai-snippet-previewer' ); ?>
									</div>
								</div>

								<div class="tseo-ai-col-card">
									<div class="tseo-ai-col-label">
										<strong><?php esc_html_e( 'Outline', 'theseo-ai-snippet-previewer' ); ?></strong>
									</div>
									<div id="tseo-ai-extract-structure" class="tseo-ai-extract">
										<?php esc_html_e( 'The subheadings of the page appear here in order.', 'theseo-ai-snippet-previewer' ); ?>
									</div>
								</div>
							</div>

							<div class="tseo-ai-checklist" style="margin-top:14px;">
								<div style="display:flex;align-items:center;gap:6px;justify-content:space-between;">
									<strong style="font-size:12px;"><?php esc_html_e( 'Suggestions from the language model', 'theseo-ai-snippet-previewer' ); ?></strong>
									<span class="tseo-ai-badge-pill">
										<?php if ( $has_key ) : ?>
											<span style="width:8px;height:8px;border-radius:999px;background:var(--tseo-success);box-shadow:0 0 8px rgba(126,245,41,.8);"></span>
											<?php
											printf(
												/* translators: %s: model name. */
												esc_html__( 'Model %s connected', 'theseo-ai-snippet-previewer' ),
												esc_html( $model )
											);
											?>
										<?php else : ?>
											<span style="width:8px;height:8px;border-radius:999px;background:var(--tseo-danger);box-shadow:0 0 8px rgba(255,92,122,.8);"></span>
											<?php esc_html_e( 'No API key, measurement and prompt only', 'theseo-ai-snippet-previewer' ); ?>
										<?php endif; ?>
									</span>
								</div>

								<div class="tseo-ai-notice" id="tseo-ai-lm-status">
									<?php esc_html_e( 'This block stays empty until you connect a model. The measurement, the extracts and the prompt work without one.', 'theseo-ai-snippet-previewer' ); ?>
								</div>

								<div class="tseo-ai-cols" style="margin-top:10px;">
									<div class="tseo-ai-col-card">
										<div class="tseo-ai-col-label">
											<strong><?php esc_html_e( 'Summary, long', 'theseo-ai-snippet-previewer' ); ?></strong>
										</div>
										<div id="tseo-ai-summary-long" class="tseo-ai-extract"></div>
									</div>
									<div class="tseo-ai-col-card">
										<div class="tseo-ai-col-label">
											<strong><?php esc_html_e( 'Summary, short', 'theseo-ai-snippet-previewer' ); ?></strong>
										</div>
										<div id="tseo-ai-summary-short" class="tseo-ai-extract"></div>
									</div>
									<div class="tseo-ai-col-card">
										<div class="tseo-ai-col-label">
											<strong><?php esc_html_e( 'Summary, balanced', 'theseo-ai-snippet-previewer' ); ?></strong>
										</div>
										<div id="tseo-ai-summary-balanced" class="tseo-ai-extract"></div>
									</div>
								</div>
								<p class="tseo-ai-settings-note">
									<?php esc_html_e( 'These three summaries are written by the model you connected. They are not the answer of GPT, Perplexity or Gemini, because no plugin can read those from the outside.', 'theseo-ai-snippet-previewer' ); ?>
								</p>

								<div class="tseo-ai-settings-grid" style="margin-top:10px;">
									<div>
										<div class="tseo-ai-field-row">
											<label for="tseo-ai-meta-title"><?php esc_html_e( 'Suggested meta title', 'theseo-ai-snippet-previewer' ); ?></label>
											<input id="tseo-ai-meta-title" type="text" class="tseo-ai-input" readonly>
										</div>
										<div class="tseo-ai-field-row">
											<label for="tseo-ai-meta-description"><?php esc_html_e( 'Suggested meta description', 'theseo-ai-snippet-previewer' ); ?></label>
											<textarea id="tseo-ai-meta-description" class="tseo-ai-textarea" readonly></textarea>
										</div>
										<p class="tseo-ai-settings-note">
											<?php esc_html_e( 'Copy these fields into your SEO plugin yourself. This plugin never changes metadata.', 'theseo-ai-snippet-previewer' ); ?>
										</p>
									</div>

									<div>
										<div class="tseo-ai-field-row">
											<label><?php esc_html_e( 'FAQ ideas for this page', 'theseo-ai-snippet-previewer' ); ?></label>
											<ul id="tseo-ai-faq-list" class="tseo-ai-faq-list">
												<li><?php esc_html_e( 'No FAQ suggestions from the model yet.', 'theseo-ai-snippet-previewer' ); ?></li>
											</ul>
										</div>
										<div class="tseo-ai-field-row">
											<label><?php esc_html_e( 'Schema JSON LD example', 'theseo-ai-snippet-previewer' ); ?></label>
											<pre id="tseo-ai-schema-json" class="tseo-ai-schema-block"><?php esc_html_e( 'A model writes an example here. Check it before you publish it, a schema with claims that are not on the page is worse than no schema.', 'theseo-ai-snippet-previewer' ); ?></pre>
										</div>
									</div>
								</div>
							</div>

							<div class="tseo-ai-field-row" style="margin-top:12px;">
								<label><?php esc_html_e( 'Prompt for GPT, Perplexity or Gemini', 'theseo-ai-snippet-previewer' ); ?></label>
								<div id="tseo-ai-prompt-block" class="tseo-ai-prompt-block"><?php esc_html_e( 'After the analysis the full prompt appears here, with your page and the measured numbers already filled in. It works without an API key.', 'theseo-ai-snippet-previewer' ); ?></div>
								<button type="button" class="tseo-ai-copy" id="tseo-ai-copy-prompt"><?php esc_html_e( 'Copy prompt', 'theseo-ai-snippet-previewer' ); ?></button>
							</div>
						</div>
					</div>
				</div>

				<div class="tseo-ai-card" style="margin-top:20px;">
					<div class="tseo-ai-card-inner">
						<h3>
							<?php esc_html_e( 'Language model connection', 'theseo-ai-snippet-previewer' ); ?>
							<span class="tseo-help">
								?
								<span class="tseo-help-tooltip">
									<?php esc_html_e( 'Optional. Without a key nothing leaves your server. With a key an excerpt of the page and the measured numbers are sent to OpenAI, billed on your own account.', 'theseo-ai-snippet-previewer' ); ?>
								</span>
							</span>
						</h3>

						<form method="post" action="options.php" style="margin-top:6px;">
							<?php settings_fields( 'theseo_ai_snippet_previewer' ); ?>
							<div class="tseo-ai-settings-grid">
								<div>
									<div class="tseo-ai-field-row">
										<label for="theseo_ai_openai_key"><?php esc_html_e( 'OpenAI API key', 'theseo-ai-snippet-previewer' ); ?></label>
										<?php if ( 'constant' === $key_source ) : ?>
											<p class="tseo-ai-settings-note">
												<?php esc_html_e( 'The key comes from the constant THESEO_AI_OPENAI_KEY in wp-config.php. Nothing is stored in the database and this field is switched off.', 'theseo-ai-snippet-previewer' ); ?>
											</p>
										<?php else : ?>
											<input
												id="theseo_ai_openai_key"
												type="password"
												class="tseo-ai-input"
												name="<?php echo esc_attr( self::OPTION ); ?>[openai_api_key]"
												value=""
												autocomplete="new-password"
												placeholder="<?php echo esc_attr__( 'sk-... paste your secret key here', 'theseo-ai-snippet-previewer' ); ?>"
											>
											<p class="tseo-ai-settings-note">
												<?php
												if ( 'option' === $key_source ) {
													esc_html_e( 'A key is stored. Leave this field empty to keep it. The key is never shown again.', 'theseo-ai-snippet-previewer' );
												} else {
													esc_html_e( 'The key is stored in the WordPress options table in plain text, like every other WordPress setting. Putting the constant THESEO_AI_OPENAI_KEY in wp-config.php keeps it out of the database.', 'theseo-ai-snippet-previewer' );
												}
												?>
											</p>
											<?php if ( 'option' === $key_source ) : ?>
												<label style="display:flex;gap:6px;align-items:center;margin-top:6px;">
													<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[remove_api_key]" value="1">
													<span><?php esc_html_e( 'Remove the stored key when saving', 'theseo-ai-snippet-previewer' ); ?></span>
												</label>
											<?php endif; ?>
										<?php endif; ?>
									</div>

									<div class="tseo-ai-field-row">
										<label for="theseo_ai_openai_model"><?php esc_html_e( 'OpenAI model', 'theseo-ai-snippet-previewer' ); ?></label>
										<input
											id="theseo_ai_openai_model"
											type="text"
											class="tseo-ai-input"
											name="<?php echo esc_attr( self::OPTION ); ?>[openai_model]"
											value="<?php echo esc_attr( $model ); ?>"
											placeholder="gpt-4o-mini"
										>
										<p class="tseo-ai-settings-note">
											<?php esc_html_e( 'The model name is passed on unchanged. Check in your own OpenAI account which names your key may use.', 'theseo-ai-snippet-previewer' ); ?>
										</p>
									</div>
								</div>

								<div>
									<div class="tseo-ai-field-row">
										<label for="theseo_ai_default_language"><?php esc_html_e( 'Preferred output language', 'theseo-ai-snippet-previewer' ); ?></label>
										<select
											id="theseo_ai_default_language"
											class="tseo-ai-select"
											name="<?php echo esc_attr( self::OPTION ); ?>[default_language]"
										>
											<?php foreach ( $this->languages() as $code => $label ) : ?>
												<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang, $code ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="tseo-ai-settings-note">
											<?php esc_html_e( 'Used for the prompt and for the answer of the model.', 'theseo-ai-snippet-previewer' ); ?>
										</p>
									</div>

									<div class="tseo-ai-field-row">
										<label><?php esc_html_e( 'What is sent', 'theseo-ai-snippet-previewer' ); ?></label>
										<p class="tseo-ai-settings-note">
											<?php esc_html_e( 'With a key: the title, at most eight thousand characters of the page text, the context you filled in and the measured numbers. Nothing else, and nothing at all without a key.', 'theseo-ai-snippet-previewer' ); ?>
										</p>
									</div>
								</div>
							</div>

							<?php submit_button( __( 'Save connection settings', 'theseo-ai-snippet-previewer' ), 'primary', 'submit', false ); ?>
						</form>
					</div>
				</div>

				<div class="tseo-ai-card" style="margin-top:20px;">
					<div class="tseo-ai-card-inner">
						<h3>
							<?php esc_html_e( 'Site checks', 'theseo-ai-snippet-previewer' ); ?>
							<span class="tseo-help">
								?
								<span class="tseo-help-tooltip">
									<?php esc_html_e( 'Three checks on your WordPress installation. They are not part of the page score.', 'theseo-ai-snippet-previewer' ); ?>
								</span>
							</span>
						</h3>

						<div class="tseo-ai-checklist">
							<ul>
								<?php foreach ( $site_checks as $check ) : ?>
									<li class="<?php echo esc_attr( $check['ok'] ? 'tseo-ok' : '' ); ?>">
										<?php echo esc_html( $check['ok'] ? $check['label_ok'] : $check['label_nok'] ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>

				<div class="tseo-ai-brands">
					<div class="tseo-ai-brand-links">
						<a href="https://theseo.nl" target="_blank" rel="noopener noreferrer">TheSEO</a>
					</div>
					<div class="tseo-ai-doc-link">
						<?php
						printf(
							'%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
							esc_html__( 'Documentation:', 'theseo-ai-snippet-previewer' ),
							esc_url( 'https://theseo.nl/tools-en-plugins/ai-snippet-previewer/' ),
							esc_html( 'theseo.nl/tools-en-plugins/ai-snippet-previewer' )
						);
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Label for a signal key, used for the empty table before the first run.
	 *
	 * @param string $key Signal key.
	 * @return string
	 */
	protected function signal_label( $key ) {
		$labels = array(
			'length'   => __( 'Text length', 'theseo-ai-snippet-previewer' ),
			'headings' => __( 'Subheadings H2 and H3', 'theseo-ai-snippet-previewer' ),
			'lists'    => __( 'Lists', 'theseo-ai-snippet-previewer' ),
			'schema'   => __( 'Structured data JSON LD', 'theseo-ai-snippet-previewer' ),
			'sources'  => __( 'External sources', 'theseo-ai-snippet-previewer' ),
			'internal' => __( 'Internal links', 'theseo-ai-snippet-previewer' ),
		);

		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}
}
