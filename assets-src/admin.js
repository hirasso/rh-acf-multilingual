
const $ = global.jQuery;

import Cookie from './js/cookie';
import './scss/admin.scss';

export default class ACFML {

  constructor() {
    this.initMultilingualWysiwyg();
    this.initMultilingualPostTitle();
    this.initMultilingualTermName();
    this.initLanguageTabs();
    this.initValidationHandling();
  }

  /**
   * Setup language switchers for multilingual acf-fields
   */
  initLanguageTabs() {
    acf.addAction('acfml/switch_language', ($field, language) => {
      // don't do anything if listening to anotherfield
      if( $field.data('acfml-ui-listen-to') ) return;
      // switch possibly listening other fields
      this.switchLanguage($(`[data-acfml-ui-listen-to="${$field.data('name')}"]`), language);
    })
    $(document).on('click', '.acfml-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      $el.blur();
      const language = $el.attr('data-language');
      this.switchLanguage($el.parents('.acfml-ui-style--tabs:first'), language);
    });
    $(document).on('dblclick', '.acfml-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      const language = $el.attr('data-language');
      this.switchLanguage($('.acfml-ui-style--tabs:not([data-acfml-ui-listen-to])'), language);
    });
    // store active language tabs before submitting a form or reloading the page
    window.addEventListener('beforeunload', () => this.storeActiveLanguageTabs());
    this.removeFromStore('acfml_language_tabs');
  }

  /**
   * Switches the language for an .acfml-multilingual-field
   * @param {jQuery Object} $fields 
   * @param {String} language 
   */
  switchLanguage($fields, language) {
    $fields.each((i, el) => {
      const $el = $(el);
      const $childFields = $el.find('.acf-input:first').find(".acfml-field");
      const $tabs = $el.find('.acfml-tab');
      
      $tabs.removeClass('is-active');
      $tabs.filter(`[data-language=${language}]`).addClass('is-active');
      $childFields.removeClass('acfml-is-visible');

      // find the active field
      const $activeField = $childFields.filter(`[data-name=${language}]:first`);
      $activeField.addClass('acfml-is-visible');
      
      // initializes delayed WYSIWYG fields
      $activeField.find('.acf-editor-wrap.delay').trigger('mousedown');

      $el.attr('data-acfml-language', language);
      acf.doAction('acfml/switch_language', $el, language);
    })
  }

  /**
   * Prepare multilingual WYSIWYG fields
   */
  initMultilingualWysiwyg() {
    acf.addFilter('wysiwyg_tinymce_settings', (init, id, field) => {
      const $parent = field.$el.parents('.acfml-multilingual-field');
      if( !$parent.length ) return init;
      const fieldNameClass = $parent.attr('data-name').split('_').join('-');
      init.body_class += ` acf-wysiwyg--${fieldNameClass}`;
      // https://www.tiny.cloud/docs-3x/reference/Configuration3x/Configuration3x@directionality/
      const textDirection = field.get('acfmlTextDirection');
      init.directionality = textDirection;
      return init;
    })
  }

  /**
   * Multilingual Post Titles
   */
  initMultilingualPostTitle() {
    acf.addAction(`ready_field/name=acfml_post_title`, field => {
      $('#titlediv').remove();
      $('[data-setting="title"]').remove();
    });
    acf.addAction(`ready_field/key=field_acfml_post_title_${acfml.defaultLanguage}`, field => {
      if( !acfml.isMobile && !field.val() ) field.$input().focus();
    });
    acf.addAction(`ready_field/key=field_acfml_slug`, $field => {
      // $('.postbox#slugdiv').remove();
    });
  }

  /**
   * Multilingual Term Names
   */
  initMultilingualTermName() {
    acf.addAction('ready_field/key=field_acfml_term_name', $field => {
      $('.form-field.term-name-wrap').remove();
    })
  }

  /**
   * Stores active language tabs for acf fields
   * @param {object} e 
   */
  storeActiveLanguageTabs() {
    let acfml_language_tabs = {};
    $('.acfml-multilingual-field').each((i, el) => {
      const key = $(el).attr('data-key');
      const language = $(el).find('.acfml-field.acfml-is-visible').attr('data-name');
      acfml_language_tabs[key] = language;
    })
    this.addToStore('acfml_language_tabs', acfml_language_tabs);
  }

  /**
   * Switches to the default language for required fields on validation error
   */
  initValidationHandling() {
    acf.addAction('invalid_field', field => {
      if( field.data.required && field.$el.hasClass('acfml-field') && !field.val() ) {
        this.switchLanguage(field.$el.parents('.acfml-multilingual-field'), field.data.name);
      }
    }); 
  }

  /**
   * Stores something in session storage
   * 
   * @param {string} key 
   * @param {mixed} value 
   */
  addToStore(key, value) {
    Cookie.set(this.getStorageKey(key), JSON.stringify(value), 1);
    // sessionStorage.setItem(this.getStorageKey(key), JSON.stringify(value));
  }

  /**
   * Removes something from session storage
   * @param {string} key 
   */
  removeFromStore(key) {
    Cookie.delete(this.getStorageKey(key));
    // sessionStorage.removeItem(this.getStorageKey(key));
  }

  /**
   * Gets something from store
   * @param {string} key 
   */
  getFromStore(key) {
    // let value = sessionStorage.getItem(this.getStorageKey(key));
    let value = Cookie.get(this.getStorageKey(key));
    return value ? JSON.parse(value) : value;
  }

  /**
   * Get storage key for scrollTop
   */
  getStorageKey(key) {
    return `${key}_${acfml.cookieHashForCurrentUri}`;
  }

}

new ACFML();