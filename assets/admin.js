(function () {function d(a){return a&&a.__esModule?{d:a.default}:{d:a}}var l=this;var f={};var g={};function m(a,c){if(!(a instanceof c))throw new TypeError("Cannot call a class as a function")}g=m;var h={};function j(e,r){for(var $=0;$<r.length;$++){var a=r[$];a.enumerable=a.enumerable||!1,a.configurable=!0,"value"in a&&(a.writable=!0),Object.defineProperty(e,a.key,a)}}function n(e,r,$){return r&&j(e.prototype,r),$&&j(e,$),e}h=n;var a=window.jQuery,o=function(){function e(){var $fcMS$$interop$default=d(g);$fcMS$$interop$default.d(this,e)}var $P8NW$$interop$default=d(h);return $P8NW$$interop$default.d(e,null,[{key:"get",value:function(e){var a=arguments.length>1&&void 0!==arguments[1]?arguments[1]:null,t=document.cookie.match("(^|;) ?"+e+"=([^;]*)(;|$)");return t?t[2]:a}},{key:"set",value:function(e,a){var t=arguments.length>2&&void 0!==arguments[2]?arguments[2]:1e4,r=new Date;r.setTime(r.getTime()+864e5*t),document.cookie=e+"="+a+";path=/;expires="+r.toGMTString()}},{key:"delete",value:function(a){e.set(a,"",0)}}]),e}();window.rahCookie=o;var b=l.jQuery,k=function(){function e(){var $fcMS$$interop$default=d(g);$fcMS$$interop$default.d(this,e),this.initTranslatableWysiwyg(),this.initTranslatablePostTitle(),this.initTranslatableTermName(),this.initLanguageTabs()}var $P8NW$$interop$default=d(h);return $P8NW$$interop$default.d(e,[{key:"initLanguageTabs",value:function(){var e=this;b(document).on("click",".acfml-tab",function(a){a.preventDefault();var t=b(a.target);t.blur();var i=t.attr("data-language");e.switchLanguage(t.parents(".acfml-translatable-field:first"),i)}),b(document).on("dblclick",".acfml-tab",function(a){a.preventDefault();var t=b(a.target).attr("data-language");e.switchLanguage(b(".acfml-translatable-field"),t)})}},{key:"switchLanguage",value:function(e,a){var t=e.find(".acf-input:first").find(".acfml-field"),i=e.find(".acfml-tab");i.removeClass("is-active"),i.filter("[data-language=".concat(a,"]")).addClass("is-active"),t.removeClass("is-visible"),t.filter("[data-name=".concat(a,"]")).addClass("is-visible")}},{key:"initTranslatableWysiwyg",value:function(){acf.addFilter("wysiwyg_tinymce_settings",function(e,a,t){var i=t.$el.parents(".acfml-translatable-field");if(!i.length)return e;var r=i.attr("data-name").split("_").join("-");return e.body_class+=" acf-wysiwyg--".concat(r),e})}},{key:"initTranslatablePostTitle",value:function(){acf.addAction("ready_field/key=field_acfml_post_title_".concat(acfml.defaultLanguage),function(e){b("#titlediv").remove(),acfml.isMobile||e.val()||e.$input().focus()}),acf.addAction("ready_field/key=field_acfml_slug",function(e){})}},{key:"initTranslatableTermName",value:function(){acf.addAction("ready_field/key=field_acfml_term_name",function(e){b(".form-field.term-name-wrap").remove()})}},{key:"addToStore",value:function(e,a){sessionStorage.setItem(this.getStorageKey(e),JSON.stringify(a))}},{key:"removeFromStore",value:function(e){sessionStorage.removeItem(this.getStorageKey(e))}},{key:"getFromStore",value:function(e){var a=sessionStorage.getItem(this.getStorageKey(e));return a?JSON.parse(a):a}},{key:"getStorageKey",value:function(e){return e+":"+window.location.pathname.replace(/^\/+/g,"").split("/").join("-")}}]),e}();f.default=k,new k;if(typeof exports==="object"&&typeof module!=="undefined"){module.exports=f}else if(typeof define==="function"&&define.amd){define(function(){return f})}})();