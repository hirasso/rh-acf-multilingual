
var jQuery = $ = global.jQuery;

export default class Cookie {

  constructor() {

  }

  static get( name, fallback = null ) {
    var v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
    return v ? v[2] : fallback;
  }

  static set(name, value, days = 10000 ) {
    var d = new Date;
    d.setTime(d.getTime() + 24*60*60*1000*days);
    document.cookie = name + "=" + value + ";path=/;expires=" + d.toGMTString();
  }

  static delete( name ) { 
    Cookie.set(name, '', 0); 
  }
}

window.rahCookie = Cookie;