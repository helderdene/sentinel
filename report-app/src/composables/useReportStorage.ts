import type { StoredReport } from '@/types';
import { ref } from 'vue';

const STORAGE_KEY = 'irms-citizen-reports';
const MAX_REPORTS = 50;

export function useReportStorage() {
    const reports = ref<StoredReport[]>(loadReports());

    function loadReports(): StoredReport[] {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);

            return raw ? (JSON.parse(raw) as StoredReport[]) : [];
        } catch {
            return [];
        }
    }

    function persist(data: StoredReport[]): void {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        reports.value = data;
    }

    function getReports(): StoredReport[] {
        const fresh = loadReports();
        reports.value = fresh;

        return fresh;
    }

    function addReport(report: StoredReport): void {
        const current = loadReports();
        current.unshift(report);
        persist(current.slice(0, MAX_REPORTS));
    }

    function updateReportStatus(token: string, status: string): void {
        const current = loadReports();
        const idx = current.findIndex((r) => r.token === token);

        if (idx !== -1) {
            current[idx].status = status;
            persist(current);
        }
    }

    function removeReport(token: string): void {
        const current = loadReports();
        persist(current.filter((r) => r.token !== token));
    }

    return { reports, getReports, addReport, updateReportStatus, removeReport };
}
