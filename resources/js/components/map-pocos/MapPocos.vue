<script setup lang="ts">
import AddPocoDialog from '@/components/map-pocos/AddPocoDialog.vue';
import Poco from '@/components/map-pocos/Poco.vue';
import MapPanel from '@/components/ui/map-panel/MapPanel.vue';
import MapPanelContent from '@/components/ui/map-panel/MapPanelContent.vue';
import MapPanelHeader from '@/components/ui/map-panel/MapPanelHeader.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useJumpCounts } from '@/composables/useJumpCounts';
import { useMap } from '@/composables/useMap';
import { useSelectedMapSolarsystem } from '@/composables/useSelectedMapSolarsystem';
import { useShowMap } from '@/composables/useShowMap';
import { useStaticSolarsystems } from '@/composables/useStaticSolarsystems';
import type { TPoco, TResolvedSolarsystem } from '@/pages/maps';
import { Deferred, usePoll } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import { ArrowDown, ArrowUp, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { map_pocos } = defineProps<{
    map_pocos?: TPoco[];
}>();

const five_minutes_in_ms = 5 * 60 * 1000;
usePoll(five_minutes_in_ms, { only: ['map_pocos'] });

const map = useMap();
const page = useShowMap();
const selectedMapSolarsystem = useSelectedMapSolarsystem();
const { resolveSolarsystem } = useStaticSolarsystems();

const fromId = computed(() => selectedMapSolarsystem.value?.solarsystem_id ?? null);
const targetIds = computed(() => {
    if (!map_pocos) return [] as number[];
    return [...new Set(map_pocos.map((p) => p.solarsystem_id))];
});

const { jumpsByTarget, routesByTarget } = useJumpCounts({
    fromId,
    targets: targetIds,
    mapConnections: computed(() => map.value.map_connections ?? []),
    mapSolarsystems: computed(() => map.value.map_solarsystems ?? []),
    ignoredSystems: computed(() => page.props.ignored_systems ?? []),
    includeEveScout: true,
});

function resolveRoute(solarsystemId: number): TResolvedSolarsystem[] | null {
    const routeResult = routesByTarget.value.get(solarsystemId);
    if (!routeResult) return null;
    return routeResult.route
        .map<TResolvedSolarsystem | null>((step, index) => {
            const solarsystem = resolveSolarsystem(step.id);
            if (!solarsystem) return null;
            return {
                ...solarsystem,
                connection_type: routeResult.route[index + 1]?.via ?? null,
            };
        })
        .filter((entry): entry is TResolvedSolarsystem => entry !== null);
}

type PocoSortColumn = 'jumps' | 'system' | 'owner';
type PocoSortDirection = 'asc' | 'desc';

const sortColumn = useLocalStorage<PocoSortColumn>('pocos-sort-column', 'jumps');
const sortDirection = useLocalStorage<PocoSortDirection>('pocos-sort-direction', 'asc');

const showAddDialog = ref(false);

type DecoratedPoco = TPoco & {
    jumps: number | null;
    route: TResolvedSolarsystem[] | null;
    ownerLabel: string;
};

const decorated = computed<DecoratedPoco[]>(() => {
    if (!map_pocos) return [];

    return map_pocos.map((poco) => {
        const jumps = fromId.value === poco.solarsystem_id ? 0 : (jumpsByTarget.value.get(poco.solarsystem_id) ?? null);
        const route = resolveRoute(poco.solarsystem_id);
        const ownerLabel =
            poco.source === 'esi' ? (poco.corporation_ticker ? `[${poco.corporation_ticker}]` : (poco.corporation_name ?? 'Unknown corp')) : (poco.owner_alias ?? 'Unknown owner');

        return { ...poco, jumps, route, ownerLabel };
    });
});

function compareJumps(a: DecoratedPoco, b: DecoratedPoco): number {
    if (a.jumps === null && b.jumps === null) return 0;
    if (a.jumps === null) return 1;
    if (b.jumps === null) return -1;
    return a.jumps - b.jumps;
}

function compareSystem(a: DecoratedPoco, b: DecoratedPoco): number {
    const aName = resolveSolarsystem(a.solarsystem_id).name ?? String(a.solarsystem_id);
    const bName = resolveSolarsystem(b.solarsystem_id).name ?? String(b.solarsystem_id);
    return aName.localeCompare(bName);
}

function compareOwner(a: DecoratedPoco, b: DecoratedPoco): number {
    return a.ownerLabel.localeCompare(b.ownerLabel);
}

function sortPocos(list: DecoratedPoco[]): DecoratedPoco[] {
    const direction = sortDirection.value === 'asc' ? 1 : -1;

    return [...list].sort((a, b) => {
        const cmp = sortColumn.value === 'jumps' ? compareJumps(a, b) : sortColumn.value === 'system' ? compareSystem(a, b) : compareOwner(a, b);

        return direction * cmp;
    });
}

const ownedPocos = computed(() => sortPocos(decorated.value.filter((poco) => poco.source === 'esi')));
const targetPocos = computed(() => sortPocos(decorated.value.filter((poco) => poco.source === 'manual')));

function handleSort(column: PocoSortColumn) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
}
</script>

<template>
    <MapPanel>
        <MapPanelHeader card-id="pocos">
            POCOs
            <span v-if="decorated.length" class="ml-1 text-amber-400">{{ decorated.length }}</span>
            <template #actions>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button variant="ghost" size="icon" class="size-6" @click="showAddDialog = true">
                            <Plus class="size-3.5" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Report a customs office</TooltipContent>
                </Tooltip>
            </template>
        </MapPanelHeader>
        <MapPanelContent>
            <div class="flex-1 overflow-x-hidden overflow-y-auto">
                <Deferred data="map_pocos">
                    <template v-if="decorated.length">
                        <div class="grid grid-cols-[1rem_auto_auto_2rem_auto] gap-x-2">
                            <div
                                class="col-span-full grid grid-cols-subgrid border-b border-border/30 bg-muted/20 px-3 py-1.5 font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
                            >
                                <span></span>
                                <button @click="handleSort('system')" class="flex items-center gap-1 hover:text-foreground">
                                    <span>System</span>
                                    <ArrowUp v-if="sortColumn === 'system' && sortDirection === 'asc'" class="size-3" />
                                    <ArrowDown v-if="sortColumn === 'system' && sortDirection === 'desc'" class="size-3" />
                                </button>
                                <button @click="handleSort('owner')" class="flex items-center gap-1 hover:text-foreground">
                                    <span>Owner</span>
                                    <ArrowUp v-if="sortColumn === 'owner' && sortDirection === 'asc'" class="size-3" />
                                    <ArrowDown v-if="sortColumn === 'owner' && sortDirection === 'desc'" class="size-3" />
                                </button>
                                <button @click="handleSort('jumps')" class="flex items-center justify-end gap-1 hover:text-foreground">
                                    <span>J</span>
                                    <ArrowUp v-if="sortColumn === 'jumps' && sortDirection === 'asc'" class="size-3" />
                                    <ArrowDown v-if="sortColumn === 'jumps' && sortDirection === 'desc'" class="size-3" />
                                </button>
                                <span class="text-right">Window</span>
                            </div>

                            <template v-if="ownedPocos.length">
                                <div class="col-span-full bg-muted/10 px-3 py-1 font-mono text-[9px] tracking-wider text-muted-foreground/70 uppercase">
                                    Owned ({{ ownedPocos.length }})
                                </div>
                                <TransitionGroup name="list">
                                    <Poco v-for="poco in ownedPocos" :key="poco.id" :poco="poco" :jumps="poco.jumps" :route="poco.route" :owner-label="poco.ownerLabel" />
                                </TransitionGroup>
                            </template>

                            <template v-if="targetPocos.length">
                                <div class="col-span-full bg-muted/10 px-3 py-1 font-mono text-[9px] tracking-wider text-muted-foreground/70 uppercase">
                                    Target List ({{ targetPocos.length }})
                                </div>
                                <TransitionGroup name="list">
                                    <Poco v-for="poco in targetPocos" :key="poco.id" :poco="poco" :jumps="poco.jumps" :route="poco.route" :owner-label="poco.ownerLabel" />
                                </TransitionGroup>
                            </template>
                        </div>
                    </template>
                    <div v-else class="flex h-full flex-col items-center justify-center gap-2 p-4">
                        <p class="font-mono text-[10px] tracking-wider text-muted-foreground/60 uppercase">No customs offices tracked</p>
                    </div>
                    <template #fallback>
                        <div class="flex h-full animate-pulse items-center justify-center gap-2 p-4">
                            <p class="font-mono text-[10px] tracking-wider text-muted-foreground/60 uppercase">Loading POCOs...</p>
                        </div>
                    </template>
                </Deferred>
            </div>
        </MapPanelContent>
    </MapPanel>
    <AddPocoDialog v-model:open="showAddDialog" />
</template>

<style scoped>
.list-move,
.list-enter-active,
.list-leave-active {
    transition: all 0.3s ease;
}

.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(20px);
}

.list-leave-active {
    position: absolute;
    opacity: 0;
    transition-duration: 0ms;
}
</style>
