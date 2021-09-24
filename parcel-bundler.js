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
const glob = require('glob');
const rimraf = require('rimraf');
const BrowserSync = require('browser-sync');

/**
 * Get and transform arguments
 */
const files = argvToArray( 'f' );
const url = argv.url ? argv.url : '';
const https = url.indexOf('https://') === 0 ? detectHTTPS() : false;
const sync = argv.sync === true;
const outDir = argv.o ? argv.o : 'assets/dist';
const isProduction = !!argv.production;
process.env.NODE_ENV = isProduction ? 'production' : 'development';

if( !files ) {
  console.warn('No entry files given.');
  return;
}
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
  const host = url.split('//')[1];
  const dir = "/Applications/MAMP/Library/OpenSSL/certs";

  const cert = glob.sync(`${dir}/${host}.crt`);
  const key = glob.sync(`${dir}/${host}.key`);

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
const parcelOptions = {
  outDir: outDir,
  publicUrl: './',
  https: https,
  logLevel: 3,
  sourceMaps: !isProduction,
  scopeHoist: isProduction,
  // minify: false
}

/**
 * Run parcel-bundler
 * @param  {[type]} files 
 */
async function runBundler( files ) {
  const bundler = new Bundler(files, parcelOptions);
  const bundle = await bundler.bundle();
}

function initBrowserSync(_options = {}) {
  const options = {
    proxy: url,
    port: 12345,
    ghostMode: {
      clicks: false,
      forms: false,
      scroll: false,
    },
    // https: true, // NO NO NO HTTPS
    open: false,
    notify: false,
    injectChanges: false, // Parcel takes care of this
    ..._options
  };

  const bs = BrowserSync.create();
  return bs.init(options);
}

/**
 * Initialize the bundler
 */
rimraf('./assets', {}, () => {
  runBundler( files );
  if( sync ) initBrowserSync();
});
