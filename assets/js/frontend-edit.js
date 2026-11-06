/**
 * Front-end editor (0.10.15). Built only from WordPress's own packages (wp.element, wp.blockEditor, wp.blocks,
 * wp.blockLibrary, wp.formatLibrary, wp.apiFetch); nothing else is loaded. A drawer holds the title, the passage
 * and the core block editor. Saving goes to the core REST API as the signed-in user.
 */
( function () {
	'use strict';
	var cfg = window.tbeEdit;
	var root = document.getElementById( 'tbe-edit-root' );
	if ( ! cfg || ! root || ! window.wp || ! wp.blockEditor || ! wp.blocks || ! wp.element || ! wp.apiFetch ) {
		return;
	}
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var t = cfg.i18n;
	var ctx = cfg.context;

	if ( wp.blockLibrary && wp.blockLibrary.registerCoreBlocks ) {
		wp.blockLibrary.registerCoreBlocks();
	}

	/* An open book, drawn as an inline SVG so it takes the button's colour in every scheme. */
	function BookIcon() {
		return el( 'svg', { className: 'tbe-edit__icon', viewBox: '0 0 24 24', width: 18, height: 18, 'aria-hidden': 'true', focusable: 'false', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round' },
			el( 'path', { d: 'M12 6.5C10 5 7 4.5 3.5 5v13c3.5-.5 6.5 0 8.5 1.5 2-1.5 5-2 8.5-1.5V5C17 4.5 14 5 12 6.5z' } ),
			el( 'path', { d: 'M12 6.5v13' } )
		);
	}

	function Editor( props ) {
		var s = useState( props.initial );
		var blocks = s[0], setBlocks = s[1];
		var title = useState( props.title ), passage = useState( props.passage );
		var status = useState( '' ), busy = useState( false ), dirty = useState( false );
		var id = useState( props.id ), state = useState( props.status );

		function change( next ) { setBlocks( next ); dirty[1]( true ); }

		function save( publish ) {
			busy[1]( true ); status[1]( t.saving );
			var body = { title: title[0], content: wp.blocks.serialize( blocks ), tbe_passage_text: passage[0] };
			var path = '/wp/v2/tbe_commentary' + ( id[0] ? '/' + id[0] : '' );
			if ( publish === true ) { body.status = 'publish'; } else if ( ! id[0] ) { body.status = 'draft'; }
			wp.apiFetch( { path: path, method: 'POST', data: body } ).then( function ( post ) {
				id[1]( post.id ); state[1]( post.status ); dirty[1]( false ); busy[1]( false );
				props.onSaved( { id: post.id, status: post.status, title: title[0], passage: passage[0], blocks: blocks } );
				status[1]( post.status === 'publish' ? t.published : t.saved + ' ' + t.draft );
			} ).catch( function () { busy[1]( false ); status[1]( t.failed ); } );
		}

		function close() {
			if ( dirty[0] && ! window.confirm( t.unsaved ) ) { return; }
			props.onClose();
		}

		useEffect( function () {
			function key( e ) {
				/* Escape also closes the editor's own menus; leave those to them. */
				if ( e.key === 'Escape' && ! e.defaultPrevented && ! e.target.closest( '.components-popover, [role="menu"], [role="listbox"]' ) ) { close(); }
			}
			document.addEventListener( 'keydown', key );
			return function () { document.removeEventListener( 'keydown', key ); };
		} );

		return el( 'div', { className: 'tbe-edit__drawer', role: 'dialog', 'aria-label': ctx.mode === 'edit' ? t.edit : t.add },
			el( 'div', { className: 'tbe-edit__bar' },
				el( 'span', { className: 'tbe-edit__status', 'aria-live': 'polite' }, status[0] ),
				el( 'button', { type: 'button', className: 'tbe-edit__btn tbe-edit__btn--primary', disabled: busy[0], onClick: function () { save( false ); } }, t.save ),
				( state[0] !== 'publish' && ctx.canPublish ) ? el( 'button', { type: 'button', className: 'tbe-edit__btn tbe-edit__btn--primary', disabled: busy[0], onClick: function () { save( true ); } }, t.publish ) : null,
				el( 'button', { type: 'button', className: 'tbe-edit__btn', onClick: close }, t.close )
			),
			el( 'div', { className: 'tbe-edit__fields' },
				el( 'label', null, t.title, el( 'input', { type: 'text', value: title[0], onChange: function ( e ) { title[1]( e.target.value ); dirty[1]( true ); } } ) ),
				el( 'label', null, t.passage, el( 'input', { type: 'text', value: passage[0], placeholder: t.hint, onChange: function ( e ) { passage[1]( e.target.value ); dirty[1]( true ); } } ) )
			),
			el( 'div', { className: 'tbe-edit__canvas editor-styles-wrapper' },
				el( wp.blockEditor.BlockEditorProvider, { value: blocks, onInput: setBlocks, onChange: change, settings: { hasFixedToolbar: true } },
					el( wp.blockEditor.BlockTools, null,
						el( wp.blockEditor.WritingFlow, null,
							el( wp.blockEditor.ObserveTyping, null, el( wp.blockEditor.BlockList, null ) )
						)
					)
				)
			)
		);
	}

	function App() {
		var open = useState( false ), data = useState( null ), loading = useState( false ), saved = useState( false );

		function start() {
			open[1]( true );
			if ( data[0] ) { return; }
			if ( ctx.mode === 'new' ) {
				data[1]( { id: 0, status: 'draft', title: '', passage: ctx.passage || '', blocks: [ wp.blocks.createBlock( 'core/paragraph' ) ] } );
				return;
			}
			loading[1]( true );
			wp.apiFetch( { path: '/wp/v2/tbe_commentary/' + ctx.id + '?context=edit' } ).then( function ( post ) {
				data[1]( {
					id: post.id,
					status: post.status,
					title: ( post.title && post.title.raw ) || '',
					passage: ( post.meta && post.meta.tbe_passage ) || '',
					blocks: wp.blocks.parse( ( post.content && post.content.raw ) || '' )
				} );
				loading[1]( false );
			} ).catch( function () { loading[1]( false ); open[1]( false ); } );
		}

		return el( wp.element.Fragment, null,
			el( 'button', { type: 'button', className: 'tbe-edit__open', onClick: start }, el( BookIcon ), ctx.mode === 'edit' ? t.edit : t.add ),
			open[0] ? ( data[0] && ! loading[0]
				? el( Editor, { initial: data[0].blocks, title: data[0].title, passage: data[0].passage, id: data[0].id, status: data[0].status, onClose: function () { if ( saved[0] && ctx.mode === 'edit' ) { window.location.reload(); return; } open[1]( false ); }, onSaved: function ( next ) { data[1]( next ); saved[1]( true ); } } )
				: el( 'div', { className: 'tbe-edit__drawer tbe-edit__drawer--loading' }, t.loading ) ) : null
		);
	}

	if ( wp.element.createRoot ) {
		wp.element.createRoot( root ).render( el( App ) );
	} else {
		wp.element.render( el( App ), root );
	}
}() );
