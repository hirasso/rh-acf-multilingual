
const $ = global.jQuery;

import Cookie from './js/cookie';
import './scss/rh-acfl-admin.scss';

export default class ACFL {

  constructor() {
    $(document).ready(() => this.onDocReady());
    // Cookie.set('rh-acfl-admin-language', 'en');
  }

  /**
   * This runs on doc ready
   */
  onDocReady() {
    this.initLanguageTabs();
  }

  initLanguageTabs() {
    $(document).on('click', '.acfl-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      const language = $el.attr('data-language');
      const $fields = $el.parents('.acf-input:first').find(".acfl-field");
      const $tabs = $el.siblings().add($el);
      $tabs.removeClass('is-active');
      $el.addClass('is-active');
      $fields.removeClass('is-visible');
      $fields.filter(`[data-name="${language}"]`).addClass('is-visible');
    });
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

new ACFL();