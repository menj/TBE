#!/usr/bin/env node
/**
 * Minimal lossless TTF -> WOFF2 converter (no dependencies; uses Node's built-in Brotli).
 * Tables are stored with the "null transform" allowed by the WOFF2 spec, so glyph data is
 * byte-identical; only the Brotli compression and container change.
 * Usage: node tools/ttf2woff2.js in.ttf out.woff2
 */
'use strict';
const fs = require( 'fs' );
const zlib = require( 'zlib' );

const KNOWN = [ 'cmap', 'head', 'hhea', 'hmtx', 'maxp', 'name', 'OS/2', 'post', 'cvt ', 'fpgm', 'glyf', 'loca', 'prep', 'CFF ', 'VORG', 'EBDT', 'EBLC', 'gasp', 'hdmx', 'kern', 'LTSH', 'PCLT', 'VDMX', 'vhea', 'vmtx', 'BASE', 'GDEF', 'GPOS', 'GSUB', 'EBSC', 'JSTF', 'MATH', 'CBDT', 'CBLC', 'COLR', 'CPAL', 'SVG ', 'sbix', 'acnt', 'avar', 'bdat', 'bloc', 'bsln', 'cvar', 'fdsc', 'feat', 'fmtx', 'fvar', 'gvar', 'hsty', 'just', 'lcar', 'mort', 'morx', 'opbd', 'prop', 'trak', 'Zapf', 'Silf', 'Glat', 'Gloc', 'Feat', 'Sill' ];

function base128( n ) {
	const out = [ n & 0x7f ];
	n = Math.floor( n / 128 );
	while ( n > 0 ) {
		out.unshift( ( n & 0x7f ) | 0x80 );
		n = Math.floor( n / 128 );
	}
	return Buffer.from( out );
}

const [ , , input, output ] = process.argv;
if ( ! input || ! output ) {
	console.error( 'Usage: node tools/ttf2woff2.js in.ttf out.woff2' );
	process.exit( 1 );
}
const ttf = fs.readFileSync( input );
const flavor = ttf.readUInt32BE( 0 );
if ( 0x00010000 !== flavor ) {
	throw new Error( 'Only TrueType-flavoured fonts (0x00010000) are supported.' );
}
const count = ttf.readUInt16BE( 4 );
let tables = [];
for ( let i = 0; i < count; i++ ) {
	const o = 12 + 16 * i;
	const tag = ttf.toString( 'latin1', o, o + 4 );
	const offset = ttf.readUInt32BE( o + 8 );
	const length = ttf.readUInt32BE( o + 12 );
	tables.push( { tag, data: ttf.subarray( offset, offset + length ) } );
}
// WOFF2 requires loca to immediately follow glyf.
const loca = tables.find( ( t ) => 'loca' === t.tag );
tables = tables.filter( ( t ) => 'loca' !== t.tag );
tables.splice( tables.findIndex( ( t ) => 'glyf' === t.tag ) + 1, 0, loca );

const pad4 = ( n ) => ( n + 3 ) & ~3;
const totalSfntSize = 12 + 16 * tables.length + tables.reduce( ( s, t ) => s + pad4( t.data.length ), 0 );

const dir = [];
for ( const t of tables ) {
	const idx = KNOWN.indexOf( t.tag );
	let flags = idx >= 0 ? idx : 63;
	if ( 'glyf' === t.tag || 'loca' === t.tag ) {
		flags |= 0xc0; // transform version 3 = null transform.
	}
	dir.push( Buffer.from( [ flags ] ) );
	if ( idx < 0 ) {
		dir.push( Buffer.from( t.tag, 'latin1' ) );
	}
	dir.push( base128( t.data.length ) );
}
const dirBuf = Buffer.concat( dir );
const compressed = zlib.brotliCompressSync( Buffer.concat( tables.map( ( t ) => t.data ) ), {
	params: {
		[ zlib.constants.BROTLI_PARAM_MODE ]: zlib.constants.BROTLI_MODE_FONT,
		[ zlib.constants.BROTLI_PARAM_QUALITY ]: 11,
		[ zlib.constants.BROTLI_PARAM_LGWIN ]: 22,
	},
} );

const header = Buffer.alloc( 48 );
const length = pad4( 48 + dirBuf.length + compressed.length );
header.write( 'wOF2', 0, 'latin1' );
header.writeUInt32BE( flavor, 4 );
header.writeUInt32BE( length, 8 );
header.writeUInt16BE( tables.length, 12 );
header.writeUInt32BE( totalSfntSize, 16 );
header.writeUInt32BE( compressed.length, 20 );
const body = Buffer.concat( [ header, dirBuf, compressed ] );
fs.writeFileSync( output, Buffer.concat( [ body, Buffer.alloc( length - body.length ) ] ) );
console.log( `${ input }: ${ ttf.length } -> ${ output }: ${ length } bytes` );
