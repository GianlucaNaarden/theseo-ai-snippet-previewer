/**
 * TheSEO AI Snippet Previewer, admin screen.
 *
 * All values coming back from the server are written with textContent or
 * value, never with innerHTML, so nothing from a page or from a language
 * model can execute here.
 */
( function () {
	'use strict';

	var data = window.theseoAiData || {};
	var i18n = data.i18n || {};

	function el( id ) {
		return document.getElementById( id );
	}

	var form = el( 'tseo-ai-form' );

	if ( ! form ) {
		return;
	}

	var select = el( 'tseo-ai-post-select' );
	var button = el( 'tseo-ai-analyze-btn' );

	var fields = {
		keyword: el( 'tseo-ai-keyword' ),
		page_type: el( 'tseo-ai-page-type' ),
		goal: el( 'tseo-ai-goal' ),
		brand: el( 'tseo-ai-brand' ),
		tone: el( 'tseo-ai-tone' )
	};

	Array.prototype.forEach.call( document.querySelectorAll( '.tseo-help' ), function ( help ) {
		help.addEventListener( 'mouseenter', function () {
			help.classList.add( 'is-active' );
		} );
		help.addEventListener( 'mouseleave', function () {
			help.classList.remove( 'is-active' );
		} );
	} );

	function request( action, extra ) {
		var body = new URLSearchParams();

		body.append( 'action', action );
		body.append( '_wpnonce', data.nonce || '' );
		body.append( 'post_id', select ? select.value : '' );

		Object.keys( extra || {} ).forEach( function ( key ) {
			body.append( key, extra[ key ] );
		} );

		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( json && json.data ? json.data : 'error' );
			}

			return json.data;
		} );
	}

	function setLoading( state ) {
		if ( ! button ) {
			return;
		}

		button.disabled = state;
		button.textContent = state ? i18n.analyzing : i18n.runAnalysis;
	}

	function setScore( score ) {
		var circle = el( 'tseo-ai-score-value' );
		var bar = el( 'tseo-ai-score-bar-fill' );

		if ( circle ) {
			circle.textContent = String( score );
		}

		if ( bar ) {
			bar.style.width = Math.max( 0, Math.min( 100, score ) ) + '%';
		}
	}

	function fillList( list, items, emptyText ) {
		if ( ! list ) {
			return;
		}

		list.textContent = '';

		if ( ! items || ! items.length ) {
			var empty = document.createElement( 'li' );
			empty.className = 'tseo-ok';
			empty.textContent = emptyText;
			list.appendChild( empty );
			return;
		}

		items.forEach( function ( item ) {
			var row = document.createElement( 'li' );

			if ( item.ok ) {
				row.className = 'tseo-ok';
			}

			row.textContent = item.label;
			list.appendChild( row );
		} );
	}

	function fillMethod( rows, total, note ) {
		var body = el( 'tseo-ai-method-body' );
		var totalCell = el( 'tseo-ai-method-total' );
		var noteEl = el( 'tseo-ai-method-note' );

		if ( body ) {
			body.textContent = '';

			rows.forEach( function ( row ) {
				var tr = document.createElement( 'tr' );

				var label = document.createElement( 'td' );
				label.textContent = row.label;

				var how = document.createElement( 'span' );
				how.className = 'tseo-ai-how';
				how.textContent = row.how;
				label.appendChild( how );

				var measured = document.createElement( 'td' );
				measured.textContent = row.measured;

				var points = document.createElement( 'td' );
				points.className = 'tseo-ai-num';
				points.textContent = row.points + ' / ' + row.max;

				tr.appendChild( label );
				tr.appendChild( measured );
				tr.appendChild( points );
				body.appendChild( tr );
			} );
		}

		if ( totalCell ) {
			totalCell.textContent = total + ' / 100';
		}

		if ( noteEl ) {
			noteEl.textContent = note || '';
		}
	}

	function fillHistory( rows ) {
		var list = el( 'tseo-ai-history-list' );

		if ( ! list ) {
			return;
		}

		list.textContent = '';

		if ( ! rows || ! rows.length ) {
			var empty = document.createElement( 'li' );
			var emptyLabel = document.createElement( 'span' );
			emptyLabel.textContent = i18n.noHistory;
			empty.appendChild( emptyLabel );
			empty.appendChild( document.createElement( 'span' ) );
			list.appendChild( empty );
			return;
		}

		rows.forEach( function ( row ) {
			var item = document.createElement( 'li' );

			var when = document.createElement( 'span' );
			when.textContent = row.date;

			var value = document.createElement( 'span' );
			value.textContent = row.score + ' / 100';

			if ( row.delta !== null && typeof row.delta !== 'undefined' && row.delta !== 0 ) {
				var delta = document.createElement( 'span' );
				delta.className = row.delta > 0 ? 'tseo-ai-delta-up' : 'tseo-ai-delta-down';
				delta.textContent = ' ' + ( row.delta > 0 ? '+' : '' ) + row.delta;
				value.appendChild( delta );
			}

			item.appendChild( when );
			item.appendChild( value );
			list.appendChild( item );
		} );
	}

	function fillFaq( items ) {
		var list = el( 'tseo-ai-faq-list' );

		if ( ! list ) {
			return;
		}

		list.textContent = '';

		if ( ! items || ! items.length ) {
			var empty = document.createElement( 'li' );
			empty.textContent = i18n.noFaq;
			list.appendChild( empty );
			return;
		}

		items.forEach( function ( item ) {
			var row = document.createElement( 'li' );
			row.textContent = item.answer ? item.question + ' ' + item.answer : item.question;
			list.appendChild( row );
		} );
	}

	function setText( id, value ) {
		var target = el( id );

		if ( target ) {
			target.textContent = value || '';
		}
	}

	function setValue( id, value ) {
		var target = el( id );

		if ( target ) {
			target.value = value || '';
		}
	}

	function fillModel( model ) {
		var status = el( 'tseo-ai-lm-status' );

		if ( status ) {
			status.textContent = model.message || '';
			status.className = 'tseo-ai-notice' + ( 'error' === model.status ? ' is-error' : ( 'ok' === model.status ? ' is-ok' : '' ) );
		}

		var payload = model.data || {};

		setText( 'tseo-ai-summary-long', payload.summary_long );
		setText( 'tseo-ai-summary-short', payload.summary_short );
		setText( 'tseo-ai-summary-balanced', payload.summary_balanced );

		setValue( 'tseo-ai-meta-title', payload.suggested_meta_title );
		setValue( 'tseo-ai-meta-description', payload.suggested_meta_description );
		setText( 'tseo-ai-schema-json', payload.schema_jsonld );
		fillFaq( payload.faq_items );
	}

	function fillContext( context ) {
		Object.keys( fields ).forEach( function ( key ) {
			if ( fields[ key ] && typeof context[ key ] !== 'undefined' ) {
				fields[ key ].value = context[ key ];
			}
		} );
	}

	function collectContext() {
		var out = {};

		Object.keys( fields ).forEach( function ( key ) {
			out[ key ] = fields[ key ] ? fields[ key ].value : '';
		} );

		return out;
	}

	if ( select ) {
		select.addEventListener( 'change', function () {
			if ( ! select.value ) {
				return;
			}

			request( 'theseo_ai_snippet_load', {} ).then( function ( payload ) {
				fillContext( payload.context || {} );
				fillHistory( payload.history || [] );
			} ).catch( function () {
				fillHistory( [] );
			} );
		} );
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		if ( ! select || ! select.value ) {
			window.alert( i18n.choosePost );
			return;
		}

		setLoading( true );

		request( 'theseo_ai_snippet_analyze', collectContext() ).then( function ( payload ) {
			setScore( payload.score || 0 );
			fillMethod( payload.method || [], payload.score || 0, payload.methodNote );
			fillList( el( 'tseo-ai-checklist-list' ), payload.checklist, i18n.noIssues );
			fillList( el( 'tseo-ai-actions-list' ), payload.actions, i18n.noActions );
			fillHistory( payload.history || [] );

			var extracts = payload.extracts || {};
			setText( 'tseo-ai-extract-opening', extracts.opening );
			setText( 'tseo-ai-extract-list', extracts.list );
			setText( 'tseo-ai-extract-structure', extracts.structure );

			setText( 'tseo-ai-meta-target', payload.targetLabel );
			setText( 'tseo-ai-prompt-block', payload.prompt );

			fillModel( payload.model || {} );
		} ).catch( function ( error ) {
			window.console.error( error );
			window.alert( error && error.message ? error.message : i18n.errorGeneric );
		} ).then( function () {
			setLoading( false );
		} );
	} );

	var copyButton = el( 'tseo-ai-copy-prompt' );

	if ( copyButton ) {
		copyButton.addEventListener( 'click', function () {
			var block = el( 'tseo-ai-prompt-block' );

			if ( ! block ) {
				return;
			}

			var done = function () {
				copyButton.textContent = i18n.copied;
				window.setTimeout( function () {
					copyButton.textContent = i18n.copyPrompt;
				}, 2000 );
			};

			var failed = function () {
				copyButton.textContent = i18n.copyFailed;
				window.setTimeout( function () {
					copyButton.textContent = i18n.copyPrompt;
				}, 4000 );
			};

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( block.textContent ).then( done ).catch( failed );
				return;
			}

			failed();
		} );
	}
}() );
