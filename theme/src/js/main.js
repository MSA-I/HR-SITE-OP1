import { initMotionToggle, observeReveal, manageWillChange } from './lib/motion.js';
import { initCart } from './modules/cart.js';
import { initWishlist } from './modules/wishlist.js';
import { initBuyBar } from './modules/buy-bar.js';
import { initViewTransitions } from './modules/view-transitions.js';
import { initMegaMenu } from './modules/mega-menu.js';
import { initCollection } from './modules/collection.js';
import { initTabs } from './modules/tabs.js';
import { initByLight } from './by-light.js';

/*
 * No framework, no GSAP, no smooth-scroll library. IntersectionObserver + CSS +
 * View Transitions cover the whole brief, and the two libraries usually reached for
 * here (ScrollTrigger's pin/scrub, Lenis) are scroll hijacking, which the brief bans.
 */

initMotionToggle();
initCart();
initWishlist();
initBuyBar();
initViewTransitions();
initMegaMenu();
initCollection();
initTabs();
initByLight();

observeReveal('.reveal');
observeReveal('.stagger');
observeReveal('.rule--draw');
manageWillChange('.product-card');
