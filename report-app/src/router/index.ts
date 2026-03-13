import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: () => import('@/views/HomeView.vue'),
        },
        {
            path: '/report/type',
            name: 'report-type',
            component: () => import('@/views/ReportTypeView.vue'),
        },
        {
            path: '/report/details',
            name: 'report-details',
            component: () => import('@/views/ReportDetailsView.vue'),
        },
        {
            path: '/report/confirm',
            name: 'report-confirm',
            component: () => import('@/views/ReportConfirmView.vue'),
        },
        {
            path: '/reports',
            name: 'my-reports',
            component: () => import('@/views/MyReportsView.vue'),
        },
        {
            path: '/track/:token',
            name: 'track-report',
            component: () => import('@/views/TrackReportView.vue'),
        },
        {
            path: '/about',
            name: 'about',
            component: () => import('@/views/AboutView.vue'),
        },
    ],
});

export default router;
