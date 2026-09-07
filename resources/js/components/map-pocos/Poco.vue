<script setup lang="ts">
import DestinationContextMenu from '@/components/autopilot/DestinationContextMenu.vue';
import RoutePopover from '@/components/autopilot/RoutePopover.vue';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { usePath } from '@/composables/usePath';
import { useStaticSolarsystems } from '@/composables/useStaticSolarsystems';
import { useMapSolarsystems } from '@/map/api';
import type { TPoco, TResolvedSolarsystem } from '@/pages/maps';
import { vElementHover } from '@vueuse/components';
import { useNow } from '@vueuse/core';
import { differenceInMinutes, format } from 'date-fns';
import { computed } from 'vue';

const { poco, jumps, route, ownerLabel } = defineProps<{
    poco: TPoco;
    jumps: number | null;
    route: TResolvedSolarsystem[] | null;
    ownerLabel: string;
}>();

const { map_solarsystems, setHoveredMapSolarsystem } = useMapSolarsystems();
const { resolveSolarsystem } = useStaticSolarsystems();
const { setPath } = usePath();

const map_solarsystem = computed(() => map_solarsystems.value.find((s) => s.solarsystem_id === poco.solarsystem_id));
const staticSolarsystem = computed(() => resolveSolarsystem(poco.solarsystem_id));

function onHover(hovered: boolean) {
    if (map_solarsystem.value) {
        setHoveredMapSolarsystem(map_solarsystem.value.id, hovered);
    }
    setPath(hovered ? route : null);
}

const now = useNow({ interval: 30_000 });

// reinforce_exit_start/end are recurring UTC hours-of-day (0-23), not one-off
// timestamps like a skyhook's theft window - find the *next* occurrence of
// that window, rolling to tomorrow once today's (or tonight's, if it wraps
// past midnight) has already passed.
const window = computed(() => {
    if (poco.reinforce_exit_start === null || poco.reinforce_exit_end === null) return null;

    const startHour = poco.reinforce_exit_start;
    const endHour = poco.reinforce_exit_end;
    const wraps = endHour <= startHour;

    const atUtcHour = (dayOffset: number, hour: number): Date =>
        new Date(Date.UTC(now.value.getUTCFullYear(), now.value.getUTCMonth(), now.value.getUTCDate() + dayOffset, hour, 0, 0));

    let start = atUtcHour(0, startHour);
    let end = wraps ? atUtcHour(1, endHour) : atUtcHour(0, endHour);

    if (now.value >= end) {
        start = atUtcHour(1, startHour);
        end = wraps ? atUtcHour(2, endHour) : atUtcHour(1, endHour);
    }

    return { start, end };
});

const isVulnerable = computed(() => (window.value ? now.value >= window.value.start && now.value < window.value.end : false));

function formatRelative(target: Date): string {
    const minutes = Math.abs(differenceInMinutes(target, now.value));
    if (minutes < 1) return '<1m';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (hours < 24) return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
    const days = Math.floor(hours / 24);
    return `${days}d ${hours % 24}h`;
}

const fifteen_minutes_in_ms = 15 * 60 * 1000;

const isAboutToEnd = computed(() => (window.value ? isVulnerable.value && window.value.end.getTime() - now.value.getTime() < fifteen_minutes_in_ms : false));

const statusTime = computed(() => (window.value ? formatRelative(isVulnerable.value ? window.value.end : window.value.start) : null));

const statusColor = computed(() => {
    if (!window.value) return 'bg-muted-foreground/40';
    if (!isVulnerable.value) return 'bg-amber-400';
    if (isAboutToEnd.value) return 'bg-red-400 animate-pulse';
    return 'bg-emerald-400 animate-pulse';
});

const statusTimeClass = computed(() => {
    if (!window.value) return 'text-muted-foreground/60';
    if (!isVulnerable.value) return 'text-amber-400';
    if (isAboutToEnd.value) return 'text-red-400 animate-pulse';
    return 'text-emerald-400';
});

const tooltipHeadline = computed(() => {
    if (!window.value) return 'No reinforcement window reported';
    return isVulnerable.value ? `Vulnerable for ${statusTime.value}` : `Vulnerable in ${statusTime.value}`;
});

const tooltipWindow = computed(() => (window.value ? `${format(window.value.start, 'HH:mm')} – ${format(window.value.end, 'HH:mm')} UTC daily` : null));

const jumpsLabel = computed(() => {
    if (jumps === null) return '—';
    if (jumps === 0) return 'here';
    return `${jumps}j`;
});

const jumpsClass = computed(() => {
    if (jumps === null) return 'text-muted-foreground/60';
    if (jumps < 8) return 'text-green-400';
    if (jumps < 15) return 'text-amber-400';
    return 'text-red-400';
});

const hasRoute = computed(() => route !== null && route.length > 1);
</script>

<template>
    <div class="col-span-full grid grid-cols-subgrid">
        <DestinationContextMenu :solarsystem_id="poco.solarsystem_id">
            <div v-element-hover="onHover" class="col-span-full grid grid-cols-subgrid items-center px-3 py-1.5 hover:bg-muted/20">
                <span class="size-2 rounded-full" :class="statusColor" />
                <div class="min-w-0 truncate">
                    <span class="truncate text-xs">{{ staticSolarsystem.name }}</span>
                    <span class="ml-1 truncate font-mono text-[10px] text-muted-foreground">{{ ownerLabel }}</span>
                </div>
                <RoutePopover v-if="hasRoute" :route="route ?? undefined">
                    <span class="cursor-pointer text-right font-mono text-[10px] tracking-wider uppercase hover:text-foreground" :class="jumpsClass">{{
                        jumpsLabel
                    }}</span>
                </RoutePopover>
                <span v-else class="text-right font-mono text-[10px] tracking-wider uppercase" :class="jumpsClass">{{ jumpsLabel }}</span>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <span class="cursor-help justify-self-end font-mono text-[10px] font-semibold tracking-wider uppercase" :class="statusTimeClass">{{
                            statusTime ?? '—'
                        }}</span>
                    </TooltipTrigger>
                    <TooltipContent class="flex flex-col gap-0.5">
                        <span>{{ tooltipHeadline }}</span>
                        <span v-if="tooltipWindow" class="font-mono text-[10px] text-muted-foreground">{{ tooltipWindow }}</span>
                        <span v-if="poco.reinforced_until" class="font-mono text-[10px] text-red-400">
                            Reported reinforced until {{ format(new Date(poco.reinforced_until), 'PP p') }}
                        </span>
                        <span v-if="poco.notes" class="max-w-48 text-[10px] text-muted-foreground">{{ poco.notes }}</span>
                    </TooltipContent>
                </Tooltip>
            </div>
        </DestinationContextMenu>
    </div>
</template>
