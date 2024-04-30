import routes from './routes';
import { createWebHashHistory, createRouter } from 'vue-router'
import galantisToolkit from './Bits/AppMixins';

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

const framework = new galantisToolkit();

framework.app.config.globalProperties.appVars = window.galantisToolkitAdmin;

window.galantisToolkitApp = framework.app.use(router).mount('#galantis_toolkit_app');

router.afterEach((to, from) => {
    jQuery('.galantis-top-menu li').removeClass('active');
    let active = to.meta.active;
    if(active) {
        jQuery('.galantis-top-menu').find('li').addClass('active');
    }
});
