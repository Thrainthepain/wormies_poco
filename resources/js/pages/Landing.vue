<script setup lang="ts">
import CharactersView from '@/components/characters/CharactersView.vue';
import DiscordIcon from '@/components/icons/DiscordIcon.vue';
import Logo from '@/components/icons/Logo.vue';
import { AllianceLogo, CharacterImage, CorporationLogo } from '@/components/images';
import { buildKillmails, buildSignatures, CHARACTERS, MAP_CONNECTIONS, MAP_PILOTS, MAP_SOLARSYSTEMS } from '@/components/landing/fixtures';
import KillmailsView, { type TKillmailViewModel } from '@/components/map-killmails/KillmailsView.vue';
import SignaturesView from '@/components/signatures/SignaturesView.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TooltipProvider } from '@/components/ui/tooltip';
import Notifications from '@/components/user/Notifications.vue';
import { useStaticSolarsystems } from '@/composables/useStaticSolarsystems';
import useUser from '@/composables/useUser';
import SeoHead from '@/layouts/SeoHead.vue';
import MapReadonly from '@/map/components/MapReadonly.vue';
import { documentation, home } from '@/routes';
import Eve from '@/routes/eve';
import { UTCDate } from '@date-fns/utc';
import type { TKillmail } from '@/types/models';
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { format } from 'date-fns';
import {
    Activity,
    ArrowRight,
    Bell,
    BookOpen,
    Crosshair,
    Crown,
    Eye,
    Laptop,
    LayoutGrid,
    Monitor,
    MoreHorizontal,
    Pencil,
    Plus,
    Radar,
    Route,
    Save,
    ScanLine,
    Settings,
    ShieldCheck,
    Smartphone,
    Sparkles,
    Tablet,
    Telescope,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, type Component } from 'vue';

const { killmails } = defineProps<{
    killmails?: TKillmail[];
}>();

const currentYear = format(new UTCDate(), 'yyyy');
const user = useUser();
const page = usePage();

const isCompact = useMediaQuery('(max-width: 1279px)');

// The killmail and signature panels show relative timestamps. Render them only
// after mount so the server markup and the client's first paint always match.
const mounted = ref(false);
onMounted(() => {
    mounted.value = true;
});

// Built per-render so relative timestamps stay in sync between SSR and client.
const KILLMAILS = buildKillmails();

// The killmail feed is real: the latest J-space kills from the database,
// refreshed while the page is open. The demo fixtures only step in when the
// database has none (e.g. a fresh self-hosted instance).
const thirty_seconds_in_ms = 30_000;
usePoll(thirty_seconds_in_ms, { only: ['killmails'] });

const { resolveSolarsystem } = useStaticSolarsystems();

const killmailItems = computed<TKillmailViewModel[]>(() => {
    if (!killmails?.length) {
        return KILLMAILS;
    }
    return killmails.map((killmail) => ({
        killmail,
        solarsystem: resolveSolarsystem(killmail.solarsystem_id),
        alias: null,
    }));
});
const SIGNATURES = buildSignatures();

// Layout editor showcase. Mirrors the real floating toolbar + card grid.
// Four cards are always present (map, system info, signatures, notes); these
// eight more can be hidden via the card library.
const hiddenCardCount = 4;

// Access control showcase. Mirrors the real "entities with access" table and
// the Viewer / Member / Manager / Owner permission levels.
type AccessPermission = 'viewer' | 'member' | 'manager' | 'owner';

const permissionMeta: Record<AccessPermission, { label: string; icon: Component; class: string }> = {
    viewer: { label: 'Viewer', icon: Eye, class: 'text-sky-400' },
    member: { label: 'Member', icon: Pencil, class: 'text-phosphor' },
    manager: { label: 'Manager', icon: Settings, class: 'text-amber-hud' },
    owner: { label: 'Owner', icon: Crown, class: 'text-amber-hud' },
};

const accessEntities: Array<{
    id: number;
    name: string;
    type: 'character' | 'corporation' | 'alliance';
    permission: AccessPermission;
    expires: string | null;
}> = [
    { id: 2112000001, name: 'Karima Solette', type: 'character', permission: 'owner', expires: null },
    { id: 99000001, name: 'Hole Control', type: 'alliance', permission: 'manager', expires: null },
    { id: 98000001, name: 'Wandering Phoenix', type: 'corporation', permission: 'member', expires: null },
    { id: 2112000002, name: 'Tovan Khev', type: 'character', permission: 'viewer', expires: 'in 7 days' },
];

const seoData = {
    title: 'WormholeSystems - Live wormhole mapping and overwatch',
    description: 'Map and navigate wormhole space with your corp or alliance, with a live shared map, signature tracking, and full situational awareness.',
    keywords: 'wormhole, mapping, eve online, capsuleers, overview, navigation',
};

const secondaryFeatures = [
    {
        icon: Route,
        title: 'Smart routing',
        body: 'Finds the shortest way home through the chain, taking wormhole mass limits and any systems you have chosen to avoid into account.',
    },
    {
        icon: Sparkles,
        title: 'Intelligence',
        body: 'Keeps notes on your systems automatically, so nobody re-scans what your group already figured out.',
    },
    {
        icon: Crosshair,
        title: 'Threat analysis',
        body: 'Flags systems with recent kills, so you know what you might be jumping into.',
    },
    {
        icon: Bell,
        title: 'Discord alerts',
        body: 'Sends proximity and killmail alerts to your Discord, filtered however you like.',
    },
    {
        icon: Telescope,
        title: 'EVE Scout',
        body: 'Pulls in Thera and Turnur connections and keeps them current, so routing can use them too.',
    },
];

// Radar contacts for the hero's ambient sweep. Each carries its own angle
// from center (clockwise from north, in degrees) so its flash animation can
// be phase-locked to the moment the sweep line actually crosses it - see
// --sweep-duration below, shared between the sweep and every contact.
const radarContacts = [
    { top: 18, left: 78, angle: 42, hostile: false },
    { top: 62, left: 14, angle: 233, hostile: false },
    { top: 34, left: 90, angle: 68, hostile: true },
    { top: 78, left: 68, angle: 148, hostile: false },
    { top: 46, left: 6, angle: 285, hostile: false },
];
const sweepDurationSeconds = 5;

function contactDelay(angle: number): string {
    return `${(angle / 360) * sweepDurationSeconds}s`;
}

const vReveal = {
    mounted(el: HTMLElement, binding: { value?: string }) {
        if (typeof IntersectionObserver === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        el.classList.add('reveal', `reveal--${binding.value ?? 'up'}`);
        const io = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        el.classList.add('reveal-in');
                        io.unobserve(el);
                    }
                }
            },
            { threshold: 0.1, rootMargin: '0px 0px -6% 0px' },
        );
        io.observe(el);
    },
};
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <div class="overwatch relative isolate min-h-screen overflow-x-hidden">
            <SeoHead :title="seoData.title" :description="seoData.description" :keywords="seoData.keywords" />

            <div class="scanlines" aria-hidden="true" />
            <div class="crt-vignette" aria-hidden="true" />

            <!-- Nav -->
            <nav class="fixed inset-x-0 top-0 z-50 border-b border-wire bg-void/85 backdrop-blur-xl">
                <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-6 sm:px-10">
                    <div class="flex items-center gap-3">
                        <span class="brand-mark">
                            <Logo class="h-4 w-4" />
                        </span>
                        <span class="font-display text-[13px] font-bold tracking-[0.08em] text-ink uppercase">Wormhole Systems</span>
                    </div>
                    <div class="flex items-center gap-5">
                        <Link :href="documentation()" class="nav-link hidden items-center gap-2 sm:flex">
                            <BookOpen class="h-3.5 w-3.5" />
                            Docs
                        </Link>
                        <a :href="page.props.discord.invite" class="nav-link hidden items-center gap-2 sm:flex">
                            <DiscordIcon class="h-3.5 w-3.5" />
                            Discord
                        </a>
                        <Button v-if="!user" asChild size="sm" class="btn-phosphor">
                            <a :href="Eve.show().url">Sign In</a>
                        </Button>
                        <Button v-else asChild size="sm" class="btn-phosphor">
                            <Link :href="home()" prefetch>Go to maps</Link>
                        </Button>
                    </div>
                </div>
            </nav>

            <main class="relative pt-14">
                <!-- Hero -->
                <section class="hero-section relative overflow-hidden border-b border-wire">
                    <div class="radar-ambient" aria-hidden="true">
                        <div class="radar-ring r1" />
                        <div class="radar-ring r2" />
                        <div class="radar-ring r3" />
                        <div class="radar-ring r4" />
                        <div class="radar-sweep" :style="{ animationDuration: `${sweepDurationSeconds}s` }" />
                        <span
                            v-for="(contact, i) in radarContacts"
                            :key="i"
                            class="radar-blip"
                            :class="{ 'radar-blip--hostile': contact.hostile }"
                            :style="{ top: contact.top + '%', left: contact.left + '%', animationDelay: contactDelay(contact.angle), animationDuration: `${sweepDurationSeconds}s` }"
                        />
                    </div>

                    <div class="relative mx-auto max-w-7xl px-6 sm:px-10">
                        <div class="grid items-center gap-14 py-24 lg:grid-cols-[0.85fr_1.15fr] lg:py-28">
                            <div class="hero-intro">
                                <div class="hud-eyebrow">
                                    <ScanLine class="h-3 w-3" />
                                    Overview &middot; All systems nominal
                                </div>
                                <h1 class="mt-7 font-display text-5xl leading-[0.98] font-bold tracking-tight text-ink uppercase sm:text-6xl lg:text-6xl">
                                    Full <span class="accent-glow">situational awareness</span> of your chain
                                </h1>
                                <p class="mt-7 max-w-xl text-lg leading-8 text-muted">
                                    Every pilot, every signature, every hostile tackle, logged the instant it happens. One shared overview for your
                                    corp or alliance &mdash; fly solo or run the whole chain together.
                                </p>
                                <div class="mt-9 flex flex-wrap items-center gap-3">
                                    <template v-if="!user">
                                        <Button asChild size="lg" class="btn-phosphor">
                                            <a :href="Eve.show().url" class="group inline-flex items-center gap-2">
                                                Sign in with EVE
                                                <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                            </a>
                                        </Button>
                                        <Button asChild size="lg" variant="outline" class="btn-ghost-hud">
                                            <a :href="Eve.show({ query: { without_scopes: true } }).url"> Sign in without scopes </a>
                                        </Button>
                                    </template>
                                    <Button asChild size="lg" v-else class="btn-phosphor">
                                        <Link :href="home()" class="group inline-flex items-center gap-2" prefetch>
                                            Explore Maps
                                            <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                        </Link>
                                    </Button>
                                </div>
                                <p class="mt-8 font-mono text-[10px] tracking-wider text-faint uppercase">ESI-secure &middot; No client install &middot; Free to use</p>
                            </div>

                            <!-- The real map, framed as a HUD console. -->
                            <div class="hero-console">
                                <div class="hud-frame">
                                    <span class="hud-corner hud-corner--tl" /><span class="hud-corner hud-corner--tr" /><span
                                        class="hud-corner hud-corner--bl"
                                    /><span class="hud-corner hud-corner--br" />
                                    <div class="hud-frame-header">
                                        <span class="live-dot" />
                                        home.map &middot; J152820
                                        <span class="ml-auto flex items-center gap-1.5 font-mono text-[10px] tracking-wider text-faint uppercase">
                                            <Radar class="h-3 w-3" /> tracking
                                        </span>
                                    </div>
                                    <div class="relative h-[420px] w-full overflow-hidden sm:h-[460px] xl:h-[540px]">
                                        <MapReadonly
                                            :solarsystems="MAP_SOLARSYSTEMS"
                                            :connections="MAP_CONNECTIONS"
                                            :pilots="MAP_PILOTS"
                                            :home_solarsystem_id="1"
                                            :scale="isCompact ? 0.62 : 0.85"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- The feed: every section is a HUD readout, stacked. -->
                <div class="relative mx-auto max-w-6xl px-6 pb-24 sm:px-10">
                    <!-- 01: Shared mapping -->
                    <div class="hud-divider" aria-hidden="true" />
                    <div v-reveal class="hud-frame">
                        <div class="hud-frame-header">
                            <Users class="h-3.5 w-3.5" /> 01 &middot; Shared mapping
                        </div>
                        <div class="grid divide-y divide-wire lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                            <div class="p-8 sm:p-12">
                                <h2 class="section-title">Everyone on the same map, live</h2>
                                <p class="section-lead">
                                    When anyone moves, scans a connection, or updates a system, every pilot sees it right away. No pasting
                                    bookmarks into chat, no side spreadsheet to keep in sync.
                                </p>
                                <ul class="points">
                                    <li><span class="dot" /> See who is online, what they fly, and where they are</li>
                                    <li><span class="dot" /> Each pilot's route home, with the jump count</li>
                                    <li><span class="dot" /> Every change shows up for everyone instantly</li>
                                </ul>
                            </div>
                            <div class="flex flex-col">
                                <div class="cell-header">
                                    <span class="live-dot" />
                                    Pilots &middot; {{ CHARACTERS.length }}
                                </div>
                                <div class="flex-1 overflow-x-auto">
                                    <CharactersView :characters="CHARACTERS" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 02: Kill activity -->
                    <div class="hud-divider" aria-hidden="true" />
                    <div v-reveal class="hud-frame">
                        <div class="hud-frame-header">
                            <Activity class="h-3.5 w-3.5" /> 02 &middot; Kill activity
                            <span class="ml-auto font-mono text-[10px] tracking-wider text-faint uppercase">via zKillboard</span>
                        </div>
                        <div class="grid divide-y divide-wire lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                            <div class="p-8 sm:p-12">
                                <h2 class="section-title">See every kill in your chain</h2>
                                <p class="section-lead">
                                    Killmails from your systems show up on their own, straight from zKillboard, so you always know where the
                                    fighting is and how much got blown up.
                                </p>
                            </div>
                            <div class="p-8 sm:p-12">
                                <ul class="points mt-0">
                                    <li><span class="dot" /> Who died, who got the kill, how many were involved, and the ISK lost</li>
                                    <li><span class="dot" /> Filter to wormhole space, known space, or everything</li>
                                    <li><span class="dot" /> Click any kill to jump to that system on the map</li>
                                </ul>
                            </div>
                        </div>
                        <div class="border-t border-wire">
                            <div class="cell-header">
                                <span class="live-dot" />
                                Live &middot; Latest J-space kills &middot; {{ killmailItems.length }}
                            </div>
                            <div class="min-h-[13rem] overflow-x-auto py-1">
                                <KillmailsView v-if="mounted" :items="killmailItems" />
                            </div>
                        </div>
                    </div>

                    <!-- 03: Signatures -->
                    <div class="hud-divider" aria-hidden="true" />
                    <div v-reveal class="hud-frame">
                        <div class="hud-frame-header">
                            <Crosshair class="h-3.5 w-3.5" /> 03 &middot; Signatures
                        </div>
                        <div class="grid divide-y divide-wire lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                            <div class="flex flex-col lg:order-1">
                                <div class="cell-header">Signatures &middot; {{ SIGNATURES.length }}</div>
                                <div class="min-h-[18rem] flex-1 overflow-x-auto">
                                    <SignaturesView v-if="mounted" :signatures="SIGNATURES" :connections="MAP_CONNECTIONS" />
                                </div>
                            </div>
                            <div class="p-8 sm:p-12 lg:order-2">
                                <h2 class="section-title">Scanning is just copy and paste</h2>
                                <p class="section-lead">
                                    Copy your probe scanner results in game, paste them in, and the map sorts it all out. New signatures get
                                    added, the ones that are gone get removed, and wormhole types line up with their connections automatically.
                                </p>
                                <div class="paste-hint">
                                    <span class="kbd">Ctrl</span>
                                    <span class="plus">+</span>
                                    <span class="kbd">V</span>
                                    <span class="paste-text">Paste straight from the in-game probe scanner</span>
                                </div>
                                <ul class="points">
                                    <li><span class="dot" /> No formatting and no manual entry</li>
                                    <li><span class="dot" /> Old signatures and dead connections are cleaned up for you</li>
                                    <li><span class="dot" /> Mass and lifetime tracked for you, with end-of-life and critical warnings</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- 04: Customisable widget layout -->
                    <div class="hud-divider" aria-hidden="true" />
                    <div v-reveal class="hud-frame">
                        <div class="hud-frame-header">
                            <LayoutGrid class="h-3.5 w-3.5" /> 04 &middot; Customisable layout
                        </div>
                        <div class="grid divide-y divide-wire lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                            <div class="p-8 sm:p-12">
                                <h2 class="section-title">Build the map around you</h2>
                                <p class="section-lead">
                                    The map is a grid of cards you can drag, resize, and hide. Keep the systems you watch front and centre and
                                    switch off the panels you do not use. Layouts are saved per device, with a separate arrangement for each
                                    screen size.
                                </p>
                                <ul class="points">
                                    <li><span class="dot" /> Drag and resize any card, from the map to autopilot to killmails</li>
                                    <li><span class="dot" /> Four cards stay put; eight more can be hidden and brought back any time</li>
                                    <li><span class="dot" /> Responsive breakpoints from mobile to wide desktop, each with its own layout</li>
                                </ul>
                            </div>
                            <div class="flex flex-col">
                                <div class="cell-header">
                                    <span class="live-dot live-dot--amber" />
                                    Editing layout
                                    <span class="ml-auto font-mono text-[10px] tracking-wider text-faint uppercase">J152820</span>
                                </div>
                                <div class="relative flex-1 p-3 pb-20">
                                    <div class="wg-grid">
                                        <div class="wg-tile wg-map">Map</div>
                                        <div class="wg-tile">Signatures</div>
                                        <div class="wg-tile">Autopilot</div>
                                        <div class="wg-tile">Characters</div>
                                        <div class="wg-tile">Killmails</div>
                                    </div>
                                    <!-- Faithful replica of the real floating layout-editor toolbar -->
                                    <div class="le-toolbar">
                                        <span class="le-btn"><X class="size-4" /></span>
                                        <span class="le-sep" />
                                        <span class="le-seg">
                                            <span class="le-seg-item"><Smartphone class="size-4" /></span>
                                            <span class="le-seg-item"><Tablet class="size-4" /></span>
                                            <span class="le-seg-item"><Laptop class="size-4" /></span>
                                            <span class="le-seg-item is-active"><Monitor class="size-4" /> Large</span>
                                        </span>
                                        <span class="le-btn"><Plus class="size-4" /></span>
                                        <span class="le-sep" />
                                        <span class="le-btn relative">
                                            <LayoutGrid class="size-4" />
                                            <span class="le-badge">{{ hiddenCardCount }}</span>
                                        </span>
                                        <span class="le-sep" />
                                        <span class="le-save"><Save class="size-3.5" /> Save</span>
                                        <span class="le-btn"><MoreHorizontal class="size-4" /></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 05: Access control -->
                    <div class="hud-divider" aria-hidden="true" />
                    <div v-reveal class="hud-frame">
                        <div class="hud-frame-header">
                            <ShieldCheck class="h-3.5 w-3.5" /> 05 &middot; Access control
                        </div>
                        <div class="grid divide-y divide-wire lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                            <div class="flex flex-col lg:order-1">
                                <div class="cell-header">Access &middot; J152820</div>
                                <div class="flex-1 px-2 py-1">
                                    <Table>
                                        <TableHeader>
                                            <TableRow class="border-wire hover:bg-transparent">
                                                <TableHead class="w-10" />
                                                <TableHead>Name</TableHead>
                                                <TableHead>Type</TableHead>
                                                <TableHead>Access level</TableHead>
                                                <TableHead>Expires</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="entity in accessEntities" :key="entity.type + entity.id" class="border-wire hover:bg-panel-raised/60">
                                                <TableCell>
                                                    <CharacterImage
                                                        v-if="entity.type === 'character'"
                                                        :character_id="entity.id"
                                                        :character_name="entity.name"
                                                        class="size-8 rounded-sm"
                                                    />
                                                    <CorporationLogo
                                                        v-else-if="entity.type === 'corporation'"
                                                        :corporation_id="entity.id"
                                                        :corporation_name="entity.name"
                                                        class="size-8 rounded-sm"
                                                    />
                                                    <AllianceLogo v-else :alliance_id="entity.id" :alliance_name="entity.name" class="size-8 rounded-sm" />
                                                </TableCell>
                                                <TableCell class="font-medium whitespace-nowrap text-ink">{{ entity.name }}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" class="border-wire text-faint capitalize">{{ entity.type }}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <span v-if="entity.permission === 'owner'" class="access-pill access-pill--amber">
                                                        <Crown class="mr-1 size-3" />
                                                        Owner
                                                    </span>
                                                    <span v-else class="access-pill">
                                                        <component :is="permissionMeta[entity.permission].icon" class="size-4" :class="permissionMeta[entity.permission].class" />
                                                        {{ permissionMeta[entity.permission].label }}
                                                    </span>
                                                </TableCell>
                                                <TableCell class="text-faint whitespace-nowrap">
                                                    <span v-if="entity.permission === 'owner'" class="text-faint/50">&mdash;</span>
                                                    <span v-else>{{ entity.expires ?? 'Never' }}</span>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                            <div class="p-8 sm:p-12 lg:order-2">
                                <h2 class="section-title">Decide exactly who sees what</h2>
                                <p class="section-lead">
                                    Four levels of access, from view-only to full control. Viewers read the map, Members contribute signatures and
                                    connections, Managers handle access and settings, and the Owner runs the whole thing.
                                </p>
                                <ul class="points">
                                    <li><span class="dot" /> Grant access to a character, a corporation, or a whole alliance</li>
                                    <li><span class="dot" /> Viewer, Member, Manager, and Owner roles, each with clear limits</li>
                                    <li><span class="dot" /> Set an optional expiry for temporary or diplomatic access</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- 06: Everything else -->
                    <div class="hud-divider" aria-hidden="true" />
                    <div v-reveal class="hud-frame">
                        <div class="hud-frame-header">
                            <Sparkles class="h-3.5 w-3.5" /> 06 &middot; Everything else
                        </div>
                        <div class="p-8 sm:p-12">
                            <h2 class="section-title">Everything else you need to live in a wormhole</h2>
                            <p class="section-lead">The rest of the tools that make day-to-day wormhole life easier.</p>
                        </div>
                        <div class="grid gap-px border-t border-wire bg-wire sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="feature in secondaryFeatures" :key="feature.title" class="feature-cell group">
                                <div class="feat-icon">
                                    <component :is="feature.icon" class="h-4.5 w-4.5 text-muted transition-colors group-hover:text-phosphor" />
                                </div>
                                <h3 class="mt-5 font-display text-lg font-bold text-ink">{{ feature.title }}</h3>
                                <p class="mt-2.5 text-[15px] leading-7 text-muted">{{ feature.body }}</p>
                            </div>
                            <!-- Filler cell so the grid stays rectangular on wide screens. -->
                            <div class="feature-cell hidden lg:block" aria-hidden="true" />
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <section class="cta">
                    <div v-reveal class="relative mx-auto max-w-3xl px-6 text-center sm:px-10">
                        <div class="hud-eyebrow mx-auto justify-center">
                            <Radar class="h-3 w-3" />
                            Drop your first probe
                        </div>
                        <h2 class="cta-title">Run overwatch on the <span class="accent-glow">void</span></h2>
                        <p class="cta-lead">Set up a map for your corp, your alliance, or just yourself, and start mapping in minutes.</p>
                        <div class="mt-10 flex justify-center">
                            <Button asChild size="lg" class="btn-phosphor">
                                <a :href="Eve.show().url" class="group inline-flex items-center gap-2">
                                    Start exploring
                                    <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </a>
                            </Button>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="relative border-t border-wire bg-void/70 backdrop-blur-sm">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 py-12 sm:flex-row sm:px-10">
                    <div class="flex items-center gap-3">
                        <span class="brand-mark brand-mark--dim">
                            <Logo class="h-4 w-4" />
                        </span>
                        <span class="font-display text-sm font-bold tracking-[0.06em] text-faint uppercase">Wormhole Systems</span>
                    </div>
                    <nav class="flex items-center gap-2">
                        <Link :href="documentation()" class="nav-link inline-flex items-center gap-2 rounded-sm px-2 py-1.5">
                            <BookOpen class="h-4 w-4" />
                            Docs
                        </Link>
                        <a :href="page.props.discord.invite" class="nav-link inline-flex items-center gap-2 rounded-sm px-2 py-1.5">
                            <DiscordIcon class="h-4 w-4" />
                            Discord
                        </a>
                    </nav>
                    <p class="text-center font-mono text-[11px] text-faint sm:text-right">
                        &copy; {{ currentYear }} WormholeSystems. EVE Online and the EVE logo are trademarks of CCP hf.
                    </p>
                </div>
            </footer>
        </div>
    </TooltipProvider>
    <Notifications />
</template>

<style scoped>
.font-display {
    font-family: var(--font-display);
}

/* Overwatch: a fixed dark HUD world. Deliberately single-theme - a phosphor
   radar console has no "light mode" - so every color here is a literal, not
   a swap of the app's own light/dark tokens (those still govern the actual
   authenticated app beyond this page). */
.overwatch {
    --void: #040705;
    --panel: #0a120d;
    --panel-raised: #0f1a13;
    --phosphor: #3dffa0;
    --phosphor-dim: #1f6b48;
    --amber-hud: #f0b429;
    --danger-hud: #ff5c5c;
    --ink: #d8f5e4;
    --muted: #7c9c8a;
    --faint: #4a6656;
    --wire: rgba(61, 255, 160, 0.14);

    background: var(--void);
    color: var(--ink);
}

.overwatch :deep(.font-display) {
    font-family: var(--font-display);
}

.bg-void\/85 {
    background-color: color-mix(in srgb, var(--void) 85%, transparent);
}
.bg-void\/70 {
    background-color: color-mix(in srgb, var(--void) 70%, transparent);
}
.border-wire {
    border-color: var(--wire);
}
.divide-wire > :not([hidden]) ~ :not([hidden]) {
    border-color: var(--wire);
}
.bg-wire {
    background-color: var(--wire);
}
.text-ink {
    color: var(--ink);
}
.text-muted {
    color: var(--muted);
}
.text-faint {
    color: var(--faint);
}
.text-phosphor {
    color: var(--phosphor);
}
.text-amber-hud {
    color: var(--amber-hud);
}
.hover\:bg-panel-raised\/60:hover {
    background-color: color-mix(in srgb, var(--panel-raised) 60%, transparent);
}
.group:hover .group-hover\:text-phosphor {
    color: var(--phosphor);
}

/* CRT scanlines + edge vignette across the whole page. */
.scanlines {
    position: fixed;
    inset: 0;
    z-index: 30;
    pointer-events: none;
    background: repeating-linear-gradient(to bottom, transparent 0px, transparent 2px, rgb(0 0 0 / 0.16) 3px);
    mix-blend-mode: multiply;
    opacity: 0.5;
}

.crt-vignette {
    position: fixed;
    inset: 0;
    z-index: 29;
    pointer-events: none;
    background: radial-gradient(ellipse at 50% 30%, transparent 45%, rgb(0 0 0 / 0.55) 100%);
}

/* Nav + footer brand mark */
.brand-mark {
    display: flex;
    height: 1.6rem;
    width: 1.6rem;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--wire);
    border-radius: 3px;
    color: var(--phosphor);
    background: color-mix(in srgb, var(--phosphor) 6%, transparent);
}

.brand-mark--dim {
    color: var(--faint);
    background: transparent;
}

.nav-link {
    display: inline-flex;
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 10.5px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted);
    transition: color 0.2s ease;
}

.nav-link:hover {
    color: var(--phosphor);
}

/* Buttons */
.btn-phosphor {
    background: var(--phosphor) !important;
    color: #04140c !important;
    font-weight: 700;
    border-radius: 3px !important;
    box-shadow: 0 0 24px -6px color-mix(in srgb, var(--phosphor) 65%, transparent);
}

.btn-phosphor:hover {
    background: color-mix(in srgb, var(--phosphor) 88%, white) !important;
}

.btn-ghost-hud {
    border-color: var(--wire) !important;
    color: var(--muted) !important;
    background: transparent !important;
    border-radius: 3px !important;
}

.btn-ghost-hud:hover {
    color: var(--ink) !important;
    border-color: color-mix(in srgb, var(--phosphor) 40%, var(--wire)) !important;
}

/* Live status dot, reused throughout as the "this is real and updating" tell. */
.live-dot {
    display: inline-block;
    height: 6px;
    width: 6px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--phosphor);
    box-shadow: 0 0 6px 1px color-mix(in srgb, var(--phosphor) 80%, transparent);
    animation: live-pulse 2.2s ease-in-out infinite;
}

.live-dot--amber {
    background: var(--amber-hud);
    box-shadow: 0 0 6px 1px color-mix(in srgb, var(--amber-hud) 80%, transparent);
}

@keyframes live-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.45; }
}

/* HUD eyebrow chip */
.hud-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 11px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--amber-hud);
    border: 1px solid color-mix(in srgb, var(--amber-hud) 35%, transparent);
    padding: 5px 11px;
    width: fit-content;
    border-radius: 2px;
}

/* Hero */
.hero-section {
    position: relative;
    background: linear-gradient(180deg, color-mix(in srgb, var(--panel) 55%, var(--void)), var(--void) 65%);
}

.radar-ambient {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    /* Kept clear of the copy column - it's atmosphere, not something that
       should ever compete with or sit behind the headline/CTA text. */
    -webkit-mask-image: linear-gradient(100deg, transparent 42%, #000 66%);
    mask-image: linear-gradient(100deg, transparent 42%, #000 66%);
}

.radar-ring {
    position: absolute;
    left: 8%;
    top: 50%;
    border: 1px solid var(--wire);
    border-radius: 50%;
    transform: translateY(-50%);
}

.radar-ring.r1 { width: 46vw; height: 46vw; max-width: 620px; max-height: 620px; margin-left: -23vw; margin-top: -23vw; }
.radar-ring.r2 { width: 33vw; height: 33vw; max-width: 440px; max-height: 440px; margin-left: -16.5vw; margin-top: -16.5vw; }
.radar-ring.r3 { width: 20vw; height: 20vw; max-width: 270px; max-height: 270px; margin-left: -10vw; margin-top: -10vw; }
.radar-ring.r4 { width: 8vw; height: 8vw; max-width: 110px; max-height: 110px; margin-left: -4vw; margin-top: -4vw; border-color: color-mix(in srgb, var(--phosphor) 25%, transparent); }

.radar-sweep {
    position: absolute;
    left: 8%;
    top: 50%;
    width: 46vw;
    height: 46vw;
    max-width: 620px;
    max-height: 620px;
    margin-left: -23vw;
    margin-top: -23vw;
    border-radius: 50%;
    background: conic-gradient(from 0deg, color-mix(in srgb, var(--phosphor) 40%, transparent) 0deg, transparent 50deg, transparent 360deg);
    animation-name: sweep-rotate;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
    mix-blend-mode: screen;
    opacity: 0.7;
}

@keyframes sweep-rotate {
    to { transform: rotate(360deg); }
}

.radar-blip {
    position: absolute;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    box-shadow: 0 0 8px 2px currentColor;
    color: var(--amber-hud);
    animation-name: blip-flash;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
}

.radar-blip--hostile {
    color: var(--danger-hud);
}

@keyframes blip-flash {
    0% { opacity: 1; transform: scale(1.6); }
    6% { opacity: 0.95; transform: scale(1.15); }
    22% { opacity: 0.35; transform: scale(1); }
    100% { opacity: 0.18; transform: scale(0.85); }
}

.hero-intro {
    position: relative;
    z-index: 2;
    animation: rise 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.hero-console {
    position: relative;
    z-index: 2;
    animation: rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
}

.accent-glow {
    display: block;
    color: var(--phosphor);
    text-shadow: 0 0 26px color-mix(in srgb, var(--phosphor) 55%, transparent);
}

/* HUD frame: the card surface for every section + the hero console, with
   corner-bracket accents like a targeting readout. */
.hud-frame {
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 4px;
    border: 1px solid var(--wire);
    background: color-mix(in srgb, var(--panel) 88%, transparent);
    backdrop-filter: blur(4px);
}

.hud-corner {
    position: absolute;
    width: 14px;
    height: 14px;
    border-color: color-mix(in srgb, var(--phosphor) 55%, transparent);
    z-index: 3;
    pointer-events: none;
}

.hud-corner--tl { top: -1px; left: -1px; border-top: 2px solid; border-left: 2px solid; }
.hud-corner--tr { top: -1px; right: -1px; border-top: 2px solid; border-right: 2px solid; }
.hud-corner--bl { bottom: -1px; left: -1px; border-bottom: 2px solid; border-left: 2px solid; }
.hud-corner--br { bottom: -1px; right: -1px; border-bottom: 2px solid; border-right: 2px solid; }

.hud-frame-header {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 2.4rem;
    flex-shrink: 0;
    padding-inline: 1rem;
    border-bottom: 1px solid var(--wire);
    background: var(--panel-raised);
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 11px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--muted);
}

/* HUD divider between stacked sections: a slim phosphor hairline with a
   center tick, replacing the old chain-link connector. */
.hud-divider {
    position: relative;
    height: 3.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hud-divider::before {
    content: '';
    position: absolute;
    inset-inline: 0;
    top: 50%;
    height: 1px;
    background: var(--wire);
}

.hud-divider::after {
    content: '';
    position: relative;
    width: 8px;
    height: 8px;
    background: var(--void);
    border: 1.5px solid color-mix(in srgb, var(--phosphor) 45%, transparent);
    transform: rotate(45deg);
}

.cell-header {
    display: flex;
    height: 2.25rem;
    flex-shrink: 0;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 1px solid var(--wire);
    background: color-mix(in srgb, var(--panel-raised) 70%, transparent);
    padding-inline: 0.75rem;
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted);
}

/* Section copy */
.section-title {
    font-family: var(--font-display);
    font-size: clamp(1.65rem, 1.25rem + 1.6vw, 2.5rem);
    font-weight: 700;
    line-height: 1.1;
    letter-spacing: -0.01em;
    color: var(--ink);
    text-transform: uppercase;
}

.section-lead {
    margin-top: 1.25rem;
    max-width: 36rem;
    font-size: 1.0625rem;
    line-height: 1.7;
    color: var(--muted);
}

.points {
    margin-top: 2.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.points li {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    font-size: 1rem;
    line-height: 1.5;
    color: color-mix(in srgb, var(--ink) 90%, transparent);
}

.dot {
    margin-top: 0.55rem;
    height: 0.375rem;
    width: 0.375rem;
    flex-shrink: 0;
    background: var(--phosphor);
    box-shadow: 0 0 4px 1px color-mix(in srgb, var(--phosphor) 60%, transparent);
}

/* Paste hint chip */
.paste-hint {
    margin-top: 2rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px dashed var(--wire);
    background: color-mix(in srgb, var(--panel-raised) 50%, transparent);
    padding: 0.6rem 0.9rem;
    border-radius: 3px;
}

.paste-hint .kbd {
    border-radius: 3px;
    border: 1px solid var(--wire);
    border-bottom-width: 2px;
    background: var(--void);
    padding: 0.1rem 0.45rem;
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ink);
}

.paste-hint .plus {
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 0.75rem;
    color: var(--muted);
}

.paste-hint .paste-text {
    margin-left: 0.35rem;
    font-size: 0.85rem;
    color: var(--muted);
}

.feat-icon {
    display: flex;
    height: 2.5rem;
    width: 2.5rem;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--wire);
    border-radius: 3px;
    background: color-mix(in srgb, var(--panel-raised) 60%, transparent);
}

.feature-cell {
    padding: 1.75rem;
    background: color-mix(in srgb, var(--panel) 92%, transparent);
}

/* Layout editor showcase: card grid + floating toolbar replica */
.wg-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.wg-tile {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    min-height: 3.25rem;
    border: 1px dashed var(--wire);
    background: color-mix(in srgb, var(--panel-raised) 50%, transparent);
    padding: 0.5rem 0.6rem;
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    color: var(--muted);
}

.wg-tile::after {
    content: '';
    height: 0.5rem;
    width: 0.5rem;
    align-self: flex-end;
    border-right: 2px solid color-mix(in srgb, var(--phosphor) 40%, transparent);
    border-bottom: 2px solid color-mix(in srgb, var(--phosphor) 40%, transparent);
}

.wg-map {
    grid-column: 1 / -1;
    min-height: 5rem;
    border-style: solid;
    border-color: color-mix(in srgb, var(--phosphor) 30%, var(--wire));
    background: color-mix(in srgb, var(--panel-raised) 80%, transparent);
    color: var(--ink);
}

.le-toolbar {
    position: absolute;
    bottom: 0.85rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    max-width: calc(100% - 1.5rem);
    border-radius: 1rem;
    border: 1px solid var(--wire);
    background: color-mix(in srgb, var(--panel) 96%, transparent);
    padding: 0.35rem;
    box-shadow: 0 14px 34px -14px rgb(0 0 0 / 0.7);
    backdrop-filter: blur(8px);
}

.le-btn {
    position: relative;
    display: flex;
    height: 1.9rem;
    width: 1.9rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    color: var(--muted);
}

.le-sep {
    height: 1.4rem;
    width: 1px;
    flex-shrink: 0;
    background: var(--wire);
}

.le-seg {
    display: flex;
    align-items: center;
    gap: 0.1rem;
    border-radius: 0.7rem;
    border: 1px solid var(--wire);
    background: color-mix(in srgb, var(--panel-raised) 60%, transparent);
    padding: 0.15rem;
}

.le-seg-item {
    display: flex;
    height: 1.6rem;
    align-items: center;
    gap: 0.3rem;
    border-radius: 0.5rem;
    padding: 0 0.4rem;
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 0.7rem;
    color: var(--muted);
}

.le-seg-item.is-active {
    background: var(--void);
    color: var(--phosphor);
}

.le-badge {
    position: absolute;
    top: -0.25rem;
    right: -0.25rem;
    display: flex;
    height: 1rem;
    min-width: 1rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: var(--phosphor);
    padding: 0 0.2rem;
    font-family: var(--font-mono, ui-monospace, monospace);
    font-size: 10px;
    color: #04140c;
}

.le-save {
    display: flex;
    height: 1.9rem;
    flex-shrink: 0;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.6rem;
    background: var(--phosphor);
    padding: 0 0.7rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #04140c;
}

/* Access entities: access-level pill */
.access-pill {
    display: inline-flex;
    height: 2rem;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
    border-radius: 3px;
    border: 1px solid var(--wire);
    background: var(--void);
    padding: 0 0.6rem;
    font-size: 0.8rem;
    color: var(--ink);
}

.access-pill--amber {
    color: var(--amber-hud);
    border-color: color-mix(in srgb, var(--amber-hud) 35%, transparent);
    background: color-mix(in srgb, var(--amber-hud) 8%, transparent);
}

/* CTA */
.cta {
    position: relative;
    padding-block: 8rem;
}

.cta-title {
    margin-top: 1.5rem;
    font-family: var(--font-display);
    font-size: clamp(2.25rem, 1.6rem + 3vw, 3.75rem);
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.01em;
    color: var(--ink);
    text-transform: uppercase;
}

.cta-lead {
    margin-top: 1.5rem;
    margin-inline: auto;
    max-width: 34rem;
    font-size: 1.125rem;
    line-height: 1.7;
    color: var(--muted);
}

/* Entrance + scroll-reveal animations */
@keyframes rise {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

.reveal {
    opacity: 0;
    transition: opacity 0.7s ease, transform 0.75s cubic-bezier(0.22, 1, 0.36, 1);
}

.reveal--up {
    transform: translateY(26px);
}

.reveal-in {
    opacity: 1;
    transform: none;
}

.reveal .points li {
    opacity: 0;
    transform: translateY(10px);
}

.reveal-in .points li {
    opacity: 1;
    transform: none;
    transition: opacity 0.5s ease, transform 0.5s ease;
}

.reveal-in .points li:nth-child(1) { transition-delay: 0.18s; }
.reveal-in .points li:nth-child(2) { transition-delay: 0.3s; }
.reveal-in .points li:nth-child(3) { transition-delay: 0.42s; }

@media (prefers-reduced-motion: reduce) {
    .hero-intro,
    .hero-console,
    .radar-sweep,
    .radar-blip,
    .live-dot {
        animation: none;
    }
}
</style>
