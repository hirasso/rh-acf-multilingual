const fs = require('fs');
const child = require('child_process');
const argv = require('minimist')(process.argv.slice(2));

const pluginFiles = ['rh-multilang.php', 'rh-acf-localized.php', 'rh-acf-multilingual.php', 'acfml.php'];
const blacklist = [
  'Merge branch ', 
  'prepare-commit-msg', 
  'pre-commit-msg', 
  '#ignore', 
  'update TODO',
  'cleanup',
  'cleanupp',
  'add documentation',
  'test_'
];

/**
 * Get the plugin version from a file
 * @param {String} pluginFile 
 */
function getPluginVersion( pluginFile ) {
  return String(pluginFile).match(/^ \* Version: ([0-9|.].*)/m)[1]
}

/**
 * Add a commit message to the changelog
 * 
 * @param {string} pluginVersion 
 * @param {string} message 
 * @param {object} changelog 
 */
function addCommitMessageToChangelog( pluginVersion, message, changelog = {}, date = null ) {
  if( isMessageBlacklisted(message) ) return changelog;
  if( !changelog[pluginVersion] ) changelog[pluginVersion] = {
    date: date,
    messages: []
  };
  changelog[pluginVersion].messages.push( message );
  return changelog;
}

/**
 * Check if a message is in the blacklist
 * @param {string} message 
 */
function isMessageBlacklisted(message) {
  for( const substring of blacklist ) {
    if( message.toLowerCase().indexOf(substring.toLowerCase()) !== -1 ) return true;
  }
  return false;
}

function getCommits() {
  const delimiter = "----DELIMITER----";
  // https://git-scm.com/docs/pretty-formats
  const output = child.execSync(`git log --pretty=format:'%B###%H###%ad\n${delimiter}'`).toString('utf-8');
  
  const commits = output.split(`\n${delimiter}`).map(commit => {
    let [message, hash, date] = commit.split('###');
    message = message.trim()
    // message = message.replace(/(?:\r\n|\r|\n)/g, '+newline+').trim();
    // only add the first line of the commit to the changelog
    message = message.split('\n', 1)[0].trim()
    return { message, hash, date };
  }).filter(commit => Boolean(commit.hash));
  return commits;
}

/**
 * 
 * @param {string} file 
 * @param {string} commit 
 */
function fileExistsInCommit(file, hash) {
  try { child.execSync(`git cat-file -e ${hash}:${file} > /dev/null 2>&1`); return true; } catch(e) { return false; }
}

/**
 * Get the main plugin file from a certain commit
 * 
 * @param {string} hash 
 */
function getPluginFileInCommit(hash) {
  for( const file of pluginFiles ) {
    if( fileExistsInCommit(file, hash) ) {
      return child.execSync(`git show ${hash}:${file}`);
    }
  }
  return false;
}

/**
 * Generate the changelog
 */
async function generateChangelog() {
  const commitsArray = getCommits();
  let changelog = {};
  
  let lastCommit = null;
  for( const commit of Object.values(commitsArray) ) {
    // continue;
    let pluginFile = getPluginFileInCommit(commit.hash);
    if( !pluginFile ) continue;
    pluginFile = pluginFile.toString('utf-8');
    if( lastCommit && lastCommit.message === commit.message ) continue;
    lastCommit = commit;
    let date = new Date(commit.date).toISOString().split('T')[0];
    let shortHash = commit.hash.substr(0,7);

    let messages = commit.message.split('- ');
    messages = messages.filter(message => Boolean(message));
    messages = messages.map(message => {
      message = `${message} (#${shortHash})`
      return message;
    });
    for( const message of messages ) {
      changelog = addCommitMessageToChangelog( getPluginVersion( pluginFile ), message, changelog, date );  
    }
    
    
  }
  return changelog;
}

/**
 * Write the changelog
 * @param {object} changelog 
 */
async function writeChangelog( changelog ) {
  let file = '';
  for( const [version, versionInfo] of Object.entries(changelog) ) {
    file += `#### ${version} (${versionInfo.date})\n\n`;
    for( const change of versionInfo.messages ) {
      file += `- ${change}\n`;
    }
    file += `\n`;
  }
  fs.writeFileSync('./changelog.md', file);
}

generateChangelog().then(changelog => writeChangelog(changelog));