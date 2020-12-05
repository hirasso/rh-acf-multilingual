/**
 * Parcle Bundler Script
 * Author: Rasso Hilber
 * Author URL: https://rassohilber.com
 * License: MIT
 *
 * Arguments:
 *
 *  -f:                   entry files (glob pattern supported)
 *  -o:                   outDir
 *  --https:              look for custom .crt and .key files and use them if found
 *  --production:         let the bundler run for production (default is watch mode)
 *  
 */

const argv = require('minimist')(process.argv.slice(2));
const Bundler = require('parcel-bundler');
const findParentDir = require('find-parent-dir');
const glob = require('glob');
const rimraf = require('rimraf');

/**
 * Get and transform arguments
 */
const files = argvToArray( 'f' );
const https = argv.https ? detectHTTPS() : false;
const outDir = argv.o ? argv.o : 'assets/dist';
const isProduction = !!argv.production;
process.env.NODE_ENV = isProduction ? 'production' : 'development';

if( !files ) console.warn('No entry files given.');
/**
 * Get and transform string arguments to array
 */
function argvToArray( key ) {
  let value = argv[key];
  let arr = value && value.length ? value.split(',') : false;
  if( !arr ) return false;
  // trim the array entries
  return arr.map( entry => entry.trim() );
}

/**
 * Detect .crt and .key files, set https to true if found
 * @return object|boolean – Object of .crt .key paths or false
 */
function detectHTTPS() {
  let dir = findParentDir.sync(__dirname, 'wwwroot');

  if( !dir ) {
    return false;
  }

  let cert = glob.sync(`${dir}/ssl/*.crt`);
  let key = glob.sync(`${dir}/ssl/*.key`);

  if( !cert.length || !key.length ) {
    return false;
  }
  return {
    cert: cert[0],
    key: key[0]
  }
}

/**
 * The options for the parcel bundler
 */
const options = {
  outDir: outDir,
  publicUrl: './',
  https: https,
  sourceMaps: !isProduction,
  // fixes issues with other parcel scripts and HMR on same page
  scopeHoist: isProduction 
}

/**
 * Run parcel-bundler
 * @param  {[type]} files 
 */
async function runBundler( files ) {
  const bundler = new Bundler(files, options);
  const bundle = await bundler.bundle();
}

/**
 * Initialize the bundler
 */
rimraf('./assets', {}, () => runBundler( files ));
