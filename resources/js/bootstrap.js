import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
if (xsrf) {
    window.axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(xsrf[1]);
}
