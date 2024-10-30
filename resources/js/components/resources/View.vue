<template>
    <div>
        <header class="mb-6">
            <div class="flex items-center">
                <h1 class="flex-1" v-text="__(title)" />

                <dropdown-list class="rtl:ml-2 ltr:mr-2" v-if="!!this.$scopedSlots.twirldown">
                    <slot name="twirldown" :actionCompleted="actionCompleted" />
                </dropdown-list>

                <div class="btn-group rtl:ml-4 ltr:mr-4" v-if="canUseStructureTree && !treeIsDirty">
                    <button class="btn flex items-center px-4" @click="view = 'tree'" :class="{'active': view === 'tree'}" v-tooltip="__('Tree')">
                        <svg-icon name="light/structures" class="h-4 w-4"/>
                    </button>
                    <button class="btn flex items-center px-4" @click="view = 'list'" :class="{'active': view === 'list'}" v-tooltip="__('List')">
                        <svg-icon name="assets-mode-table" class="h-4 w-4" />
                    </button>
                </div>

                <template v-if="view === 'tree'">

                    <a
                        class="text-2xs text-blue rtl:ml-4 ltr:mr-4 underline"
                        v-if="treeIsDirty"
                        v-text="__('Discard changes')"
                        @click="cancelTreeProgress"
                    />

                    <button
                        class="btn rtl:ml-4 ltr:mr-4"
                        :class="{ 'disabled': !treeIsDirty, 'btn-danger': deletedEntries.length }"
                        :disabled="!treeIsDirty"
                        @click="saveTree"
                        v-text="__('Save Changes')"
                        v-tooltip="deletedEntries.length ? __n('An entry will be deleted|:count entries will be deleted', deletedEntries.length) : null" />

                </template>

                <div>
                    <a v-if="canCreate" class="btn-primary" :href="createUrl" v-text="createLabel" />
                </div>
            </div>
        </header>

        <runway-listing
            v-if="view !== 'tree'"
            :has-publish-states="hasPublishStates"
            :resource="handle"
            :initial-columns="columns"
            :filters="filters"
            :action-url="actionUrl"
            :primary-column="primaryColumn"
        ></runway-listing>

        <page-tree
            v-if="view === 'tree' && canUseStructureTree"
            ref="tree"
            :collections="[handle]"
            :blueprints="blueprints"
            :create-url="createUrl"
            :pages-url="structureUrl"
            :submit-url="submitUrl"
            :submit-parameters="{ deletedEntries, deleteLocalizationBehavior }"
            :max-depth="structureMaxDepth"
            :expects-root="structureExpectsRoot"
            :show-slugs="structureShowSlugs"
            :preferences-prefix="preferencesPrefix"
            @edit-page="editPage"
            @changed="markTreeDirty"
            @saved="markTreeClean"
            @canceled="markTreeClean"
        >
            <template #branch-options="{ branch, removeBranch, orphanChildren, depth }">
                <template v-if="depth < structureMaxDepth">
                    <h6 class="px-2" v-text="__('Create Child Entry')" v-if="blueprints.length > 1" />
                    <li class="divider" v-if="blueprints.length > 1" />
                    <dropdown-item
                        v-for="blueprint in blueprints"
                        :key="blueprint.handle"
                        @click="createEntry(blueprint.handle, branch.id)"
                        v-text="blueprints.length > 1 ? __(blueprint.title) : __('Create Child Entry')" />
                </template>
                <template v-if="branch.can_delete">
                    <li class="divider"></li>
                    <dropdown-item
                        :text="__('Delete')"
                        class="warning"
                        @click="deleteTreeBranch(branch, removeBranch, orphanChildren)" />
                </template>
            </template>
        </page-tree>
    </div>
</template>

<script>
import RunwayListing from './Listing.vue'
import HasActions from '../../../../vendor/statamic/cms/resources/js/components/publish/HasActions'
import PageTree from './PageTree.vue';

export default {
    mixins: [HasActions],

    components: {
        RunwayListing,
        PageTree,
    },

    props: {
        title: { type: String, required: true },
        handle: { type: String, required: true },
        canCreate: { type: Boolean, required: true },
        createUrl: { type: String, required: true },
        createLabel: { type: String, required: true },
        columns: { type: Array, required: true },
        filters: { type: Array, required: true },
        actionUrl: { type: String, required: true },
        primaryColumn: { type: String, required: true},
        hasPublishStates: { type: Boolean, required: true },
        canUseStructureTree: { type: Boolean, required: true },
        structureUrl: { type: String, required: true },
        submitUrl: { type: String, required: true },
        view: { type: String, default: null },
        deletedEntries: { type: Array, default: [] },
    },

    computed: {
        treeIsDirty() {
            return this.$dirty.has('page-tree');
        },
    },

    methods: {
        afterActionSuccessfullyCompleted(response) {
            if (!response.redirect) window.location.reload();
        },

        saveTree() {
            this.performTreeSaving()
        },

        performTreeSaving() {
            this.$refs.tree
                .save()
                .then(() => (this.deletedEntries = []))
                .catch(() => {});
        },

        markTreeDirty() {
            this.$dirty.add('page-tree');
        },

        markTreeClean() {
            this.$dirty.remove('page-tree');
        },
    },
}
</script>
