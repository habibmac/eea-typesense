import routes from './routes';
import { createWebHashHistory, createRouter } from 'vue-router'
import galantisTypesense from './Bits/AppMixins';

const router = createRouter({
    history: createWebHashHistory(),
    routes
});

const framework = new galantisTypesense();

framework.app.config.globalProperties.appVars = window.galantisTypesenseAdmin;

window.galantisTypesenseApp = framework.app.use(router).mount('#galantis_typesense_app');

router.afterEach((to, from) => {
    jQuery('.galantis_typesense_menu_item').removeClass('active');
    let active = to.meta.active;
    if(active) {
        jQuery('.galantis_typesense_main-menu-items').find('li[data-key='+active+']').addClass('active');
    }
});

//update nag remove from admin, You can remove if you want to show notice on admin
jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
