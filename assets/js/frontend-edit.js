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

	function Editor( props ) {
		var s = useState( props.initial );
		var blocks = s[0], setBlocks = s[1];
		var title = useState( props.title ), passage = useState( props.passage );
		var status = useState( '' ), busy = useState( false ), dirty = useState( false );
		var id = useState( props.id );

		function change( next ) { setBlocks( next ); dirty[1]( true ); }

		function save() {
			busy[1]( true ); status[1]( t.saving );
			var body = { title: title[0], content: wp.blocks.serialize( blocks ), tbe_passage_text: passage[0] };
			var path = '/wp/v2/tbe_commentary' + ( id[0] ? '/' + id[0] : '' );
			if ( ! id[0] ) { body.status = 'draft'; }
			wp.apiFetch( { path: path, method: 'POST', data: body } ).then( function ( post ) {
				id[1]( post.id ); dirty[1]( false ); busy[1]( false );
				status[1]( t.saved + ( ctx.mode === 'new' ? ' ' + t.draft : '' ) );
				if ( ctx.mode === 'edit' ) { props.onSaved( post ); }
			} ).catch( function () { busy[1]( false ); status[1]( t.failed ); } );
		}

		function close() {
			if ( dirty[0] && ! window.confirm( t.unsaved ) ) { return; }
			props.onClose();
		}

		useEffect( function () {
			function key( e ) { if ( e.key === 'Escape' ) { close(); } }
			document.addEventListener( 'keydown', key );
			return function () { document.removeEventListener( 'keydown', key ); };
		} );

		return el( 'div', { className: 'tbe-edit__drawer', role: 'dialog', 'aria-label': ctx.mode === 'edit' ? t.edit : t.add },
			el( 'div', { className: 'tbe-edit__bar' },
				el( 'span', { className: 'tbe-edit__status', 'aria-live': 'polite' }, status[0] ),
				el( 'button', { type: 'button', className: 'tbe-edit__btn tbe-edit__btn--primary', disabled: busy[0], onClick: save }, t.save ),
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
		var open = useState( false ), data = useState( null ), loading = useState( false );

		function start() {
			open[1]( true );
			if ( ctx.mode === 'new' ) {
				data[1]( { id: 0, title: '', passage: ctx.passage || '', blocks: [ wp.blocks.createBlock( 'core/paragraph' ) ] } );
				return;
			}
			loading[1]( true );
			wp.apiFetch( { path: '/wp/v2/tbe_commentary/' + ctx.id + '?context=edit' } ).then( function ( post ) {
				data[1]( {
					id: post.id,
					title: ( post.title && post.title.raw ) || '',
					passage: ( post.meta && post.meta.tbe_passage ) || '',
					blocks: wp.blocks.parse( ( post.content && post.content.raw ) || '' )
				} );
				loading[1]( false );
			} ).catch( function () { loading[1]( false ); open[1]( false ); } );
		}

		return el( wp.element.Fragment, null,
			el( 'button', { type: 'button', className: 'tbe-edit__open', onClick: start }, ctx.mode === 'edit' ? t.edit : t.add ),
			open[0] ? ( data[0] && ! loading[0]
				? el( Editor, { initial: data[0].blocks, title: data[0].title, passage: data[0].passage, id: data[0].id, onClose: function () { open[1]( false ); }, onSaved: function () {} } )
				: el( 'div', { className: 'tbe-edit__drawer tbe-edit__drawer--loading' }, t.loading ) ) : null
		);
	}

	wp.element.render( el( App ), root );
}() );
