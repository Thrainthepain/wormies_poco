import { router } from '@inertiajs/vue3';

type NewPocoPayload = {
    owner_alias?: string;
    reinforce_exit_start?: number | null;
    reinforce_exit_end?: number | null;
    notes?: string;
};

export function createPoco(map_solarsystem_id: number, payload: NewPocoPayload, onSuccess?: () => void): void {
    router.post(`/map-solarsystems/${map_solarsystem_id}/pocos`, payload, {
        preserveScroll: true,
        preserveState: true,
        only: ['map_pocos'],
        onSuccess,
    });
}
