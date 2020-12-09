
var jQuery = $ = global.jQuery;

import './scss/rh-acfl-admin.scss';

export default class ACFL {

  constructor() {
    jQuery(document).ready(() => this.onDocReady());
  }

  /**
   * This runs on doc ready
   */
  onDocReady() {
    
  }

  /**
   * Stores something in session storage
   * 
   * @param {string} key 
   * @param {mixed} value 
   */
  addToStore(key, value) {
    sessionStorage.setItem(this.getStorageKey(key), JSON.stringify(value));
  }

  /**
   * Removes something from session storage
   * @param {string} key 
   */
  removeFromStore(key) {
    sessionStorage.removeItem(this.getStorageKey(key));
  }

  /**
   * Gets something from store
   * @param {string} key 
   */
  getFromStore(key) {
    let value = sessionStorage.getItem(this.getStorageKey(key));
    return value ? JSON.parse(value) : value;
  }

  /**
   * Get storage key for scrollTop
   */
  getStorageKey(key) {
    var path = window.location.pathname;
    return key + ":" + path.replace(/^\/+/g, '').split('/').join('-');
  }

}

new RHAU();