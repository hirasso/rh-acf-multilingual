const wpPot = require('wp-pot');

wpPot({
  destFile: './lang/acfml.pot',
  domain: 'acfml',
  package: 'ACF Multilingual',
  src: '../**/*.php'
});
