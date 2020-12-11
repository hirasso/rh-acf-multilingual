
const $ = global.jQuery;

import Cookie from './js/cookie';
import './scss/admin.scss';

export default class ACFL {

  constructor() {
    $(document).ready(() => this.onDocReady());
    this.acfWysiwyg();
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
      this.switchLanguage($el.parents('.acfl-group:first'), language);
    });
    $(document).on('dblclick', '.acfl-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      const language = $el.attr('data-language');
      this.switchLanguage($('.acfl-group'), language);
    })
  }

  /**
   * Switches the language for an .acfl-group
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
    acf.addAction('wysiwyg_tinymce_init',(editor, id, mceInit, field) => {
      // add a slug class on all wysiwyg fields (for editor-styles.css)
      editor.on('init', () => this.addClassToAcfWysiwyg(field))
    });
  }

  /**
   * add the acf field's name slug a data-attribute to the iframe body
   * @param {object} $field the acf field as a jQuery object
   */
  addClassToAcfWysiwyg(field) {
    const $parent = field.$el.parents('.acfl-group');
    if( !$parent.length ) return;
    const fieldName = $parent.attr('data-name');
    const fieldLanguage = field.$el.attr('data-name');
    const $iframe = $('iframe', field.$el).contents();
    const $html = $iframe.find('html');
    const $body = $iframe.find('body');
    $html.attr('lang', fieldLanguage);
    $body.attr('data-field-name', fieldName);
    $body.attr('data-language', fieldLanguage);
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