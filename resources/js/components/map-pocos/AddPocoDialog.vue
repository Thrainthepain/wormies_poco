<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useMap } from '@/composables/useMap';
import { useSelectedMapSolarsystem } from '@/composables/useSelectedMapSolarsystem';
import { createPoco } from '@/map/actions/createPoco';
import { computed, ref, watch } from 'vue';

const open = defineModel<boolean>('open', { default: false });

const map = useMap();
const selectedMapSolarsystem = useSelectedMapSolarsystem();

const ownerAlias = ref('');
const reinforceExitStart = ref<number | null>(null);
const reinforceExitEnd = ref<number | null>(null);
const notes = ref('');
const submitting = ref(false);

// No corp directory to search against - suggest whatever occupier aliases are
// already known on this map, same free-text intent as MapSolarsystemDetails'
// occupier_alias.
const ownerSuggestions = computed(() => {
    const aliases = (map.value.map_solarsystems ?? []).map((s) => s.occupier_alias).filter((alias): alias is string => Boolean(alias));
    return [...new Set(aliases)];
});

watch(open, (isOpen) => {
    if (!isOpen) return;
    ownerAlias.value = '';
    reinforceExitStart.value = null;
    reinforceExitEnd.value = null;
    notes.value = '';
});

function submit(): void {
    const mapSolarsystemId = selectedMapSolarsystem.value?.id;
    if (!mapSolarsystemId) return;

    submitting.value = true;

    createPoco(
        mapSolarsystemId,
        {
            owner_alias: ownerAlias.value || undefined,
            reinforce_exit_start: reinforceExitStart.value,
            reinforce_exit_end: reinforceExitEnd.value,
            notes: notes.value || undefined,
        },
        () => {
            submitting.value = false;
            open.value = false;
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Report a customs office</DialogTitle>
                <DialogDescription>
                    For a POCO belonging to another corp that you've scouted here. Your own corp's offices are pulled in automatically.
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <div class="grid gap-1.5">
                    <Label for="poco-owner">Owner</Label>
                    <Input id="poco-owner" v-model="ownerAlias" list="poco-owner-suggestions" placeholder="Corp or alliance name" />
                    <datalist id="poco-owner-suggestions">
                        <option v-for="alias in ownerSuggestions" :key="alias" :value="alias" />
                    </datalist>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="poco-window-start">Reinforce exit start (UTC hour)</Label>
                        <Input id="poco-window-start" v-model.number="reinforceExitStart" type="number" min="0" max="23" placeholder="e.g. 18" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="poco-window-end">Reinforce exit end (UTC hour)</Label>
                        <Input id="poco-window-end" v-model.number="reinforceExitEnd" type="number" min="0" max="23" placeholder="e.g. 22" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="poco-notes">Notes</Label>
                    <Textarea id="poco-notes" v-model="notes" placeholder="Anything else worth flagging" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button :disabled="submitting || !selectedMapSolarsystem" @click="submit">Report</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
