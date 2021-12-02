
const $ = window.jQuery;

export default class Cookie {

  constructor() {

  }

  static getAll() {
    const cookies = {}
    if( !document.cookie ) return cookies;
    const rows = document.cookie.split('; ');
    for(const row of rows) {
      const [key, value] = row.split('=');
      cookies[key] = value;
    }
    return cookies;
  }

  static get( name, fallback = null ) {
    var v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
    return v ? v[2] : fallback;
  }

  static set(name, value, minutes = 1 ) {
    var d = new Date;
    d.setTime(d.getTime() + minutes * 1000 * 60);
    document.cookie = name + "=" + value + ";path=/;expires=" + d.toGMTString();
  }

  static delete( name ) { 
    Cookie.set(name, '', 0); 
  }
}

window.rahCookie = Cookie;