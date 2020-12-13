
const $ = global.jQuery;

import Cookie from './js/cookie';
import './scss/admin.scss';

export default class ACFL {

  constructor() {
    $(document).ready(() => this.onDocReady());
    this.acfWysiwyg();
    $('body').toggleClass('supports-acfl-title', $('.acfl-title').length > 0 );
    // $('#edit-slug-box').clone(true).appendTo($('.acfl-title .acf-fields .acf-input:first'));
    // Cookie.set('rh-acfl-admin-language', 'en');
  }

  /**
   * This runs on doc ready
   */
  onDocReady() {
    this.initLanguageTabs();
  }

  /**
   * Setup language switchers for translatable acf-fields
   */
  initLanguageTabs() {
    $(document).on('click', '.acfl-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      $el.blur();
      const language = $el.attr('data-language');
      this.switchLanguage($el.parents('.acfl-translatable-field:first'), language);
    });
    $(document).on('dblclick', '.acfl-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      const language = $el.attr('data-language');
      this.switchLanguage($('.acfl-translatable-field'), language);
    })
  }

  /**
   * Switches the language for an .acfl-translatable-field
   * @param {jQuery Object} $el 
   * @param {String} language 
   */
  switchLanguage($el, language) {
    const $fields = $el.find('.acf-input:first').find(".acfl-field");
    const $tabs = $el.find('.acfl-tab');
    $tabs.removeClass('is-active');
    $tabs.filter(`[data-language=${language}]`).addClass('is-active');
    $fields.removeClass('is-visible');
    $fields.filter(`[data-name=${language}]`).addClass('is-visible');
  }

  /**
   * Prepare translatable WYSIWYG fields
   */
  acfWysiwyg() {
    acf.addFilter('wysiwyg_tinymce_settings', (init, id, field) => {
      const $parent = field.$el.parents('.acfl-translatable-field');
      if( !$parent.length ) return init;
      const fieldNameClass = $parent.attr('data-name').split('_').join('-');
      init.body_class += ` acf-wysiwyg--${fieldNameClass}`;
      return init;
    })
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