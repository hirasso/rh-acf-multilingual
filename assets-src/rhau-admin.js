
var jQuery = $ = global.jQuery;

import './scss/rhau.scss';

export default class RHAU {

  constructor() {
    jQuery(document).ready(() => this.onDocReady());
    this.reopenSavedAcfFieldObjects();
    this.restoreScrollTop();
    $('form#post').one( 'submit', (e) => this.beforeSubmitPostForm(e) );
    
  }

  /**
   * This runs on doc ready
   */
  onDocReady() {
    this.enableSubmitDiv();
    this.initAdminBarButtons();
    this.restoreScrollTop();
    this.removeFromStore('scrollTop');
  }

  /**
   * Is being fired before saving/publishing/updating a post
   */
  beforeSubmitPostForm(e) {
    // e.preventDefault();
    this.saveOpenAcfFieldObjects();
    this.addToStore('scrollTop', $(document).scrollTop());
  }

  /**
   * Restores scrollTop
   */
  restoreScrollTop() {
    var scrollTop = this.getFromStore('scrollTop');
    if( scrollTop ) $(document).scrollTop(parseInt(scrollTop));
  }

  /**
   * Inits Admin Bar Buttons
   */
  initAdminBarButtons() {
    // publish/update button
    let $wpPublish = $('#submitdiv input[id="publish"]:visible');
    let $rhPublish = $('#wp-admin-bar-rh-publish a');
    if( $wpPublish.length ) {
      $rhPublish.text($wpPublish.val()).click(e => {
        e.preventDefault();
        $wpPublish.click();
        $rhPublish.addClass('is-disabled');
      })
    }
    // save draft button
    let $wpSave = $('#submitdiv input[id="save-post"]:visible');
    let $rhSave = $('#wp-admin-bar-rh-save a');
    if( $wpSave.length ) {
      $rhSave.text($wpSave.val()).click(e => {
        e.preventDefault();
        $wpSave.click();
        $rhSave.addClass('is-disabled');
      })
    }
    if( typeof acf !== 'undefined' && typeof acf.addFilter !== 'undefined' ) {
      acf.addFilter('validation_complete', (json, $form) => {
        // check errors
        if( json.errors ) {
          if( $rhPublish.length ) $rhPublish.removeClass('is-disabled');
          if( $rhSave.length ) $rhSave.removeClass('is-disabled');
        }

        // return
        return json;
      })
    }
  }

  /**
   * Enables the #submitdiv
   */
  enableSubmitDiv() {
    $('#submitdiv').addClass("rhau-enabled");
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
   * Stores open Field objects
   */
  saveOpenAcfFieldObjects() {
    if( typeof acf === 'undefined' ) return;
    if( typeof acf.getFieldObjects !== 'function' ) return;
    let openFieldObjects = [];
    try {
      for( const fieldObject of acf.getFieldObjects() ) {
        if( !fieldObject.isOpen() ) continue;
        // disable close function
        fieldObject.close = () => {};
        openFieldObjects.push( fieldObject.getKey() );
      }
    } catch(e) { console.warn(e) }
    this.addToStore('open-acf-field-objects', openFieldObjects);
  }

  /**
   * 
   * @param {mixed} obj 
   * @param  {...any} args 
   */
  getNested(obj, ...args) {
    return args.reduce((obj, level) => obj && obj[level], obj)
  }

  /**
   * Restores open field objects
   */
  reopenSavedAcfFieldObjects() {
    if( typeof acf === 'undefined' ) return;
    if( typeof acf.getFieldObjects !== 'function' ) return;
    let openObjects = this.getFromStore('open-acf-field-objects');
    if( !openObjects ) return;
    try {
      for( const key of openObjects ) {
        const fieldObject = acf.getFieldObject(key);
        const $settings = fieldObject.$el.children('.settings');
        // copied code from acf-field-group.js
        // open
        fieldObject.$el.addClass('open');
        $settings.css({display: 'block'});
        acf.doAction('open_field_object', fieldObject);
        fieldObject.trigger('openFieldObject');
        // action (show)
        acf.doAction('show', $settings);
      } 
    } catch(e) { console.warn(e) }
    // removes the property from store
    this.removeFromStore('open-acf-field-objects');
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