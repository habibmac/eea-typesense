import Collections from './Components/Collections.vue';
import Server from './Components/Server.vue';
import Logs from './Components/Logs.vue';

export default [{
        path: '/',
        name: 'collections',
        component: Collections,
        meta: {
            active: 'collections'
        },
    },
    {
        path: '/server',
        name: 'server',
        component: Server
    },
    {
        path: '/logs',
        name: 'logs',
        component: Logs
    },
];