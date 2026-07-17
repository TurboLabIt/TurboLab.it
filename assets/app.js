// Bootstrap first: theme/app styles below must override it (same cascade as the old CDN <link>)
import 'bootstrap/dist/css/bootstrap.min.css';

// Font Awesome as CSS + self-hosted webfonts
import '@fortawesome/fontawesome-free/css/all.min.css';

import './styles/newspark/stellarnav.css';
import './styles/newspark/default.css';
import './styles/newspark/style.css';
import './styles/app.css';
import './styles/userbar.css';
import './styles/glow.css';
import './styles/footer.css';
import './styles/handstop.css';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import './js/scroll-to-selector'
import './js/prevent-change-url'
import './js/form-submit-to-path'
import './js/userbar';
import './js/visit-on-load';
import './js/chevron';
import './js/economics';
